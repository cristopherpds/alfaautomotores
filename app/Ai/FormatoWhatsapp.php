<?php

namespace App\Ai;

/**
 * Markdown → formato de WhatsApp, justo antes de mandar.
 *
 * Portado de `app/formato.py` de nea-agent. Los modelos escriben Markdown
 * aunque el prompt pida WhatsApp (`**negritas**`, `## títulos`, tablas,
 * `[texto](url)`) y WhatsApp no lo interpreta: el cliente vería los
 * asteriscos dobles tal cual. Pedírselo mejor al modelo sale bien nueve
 * veces y la décima no, así que se arregla acá, de forma determinista.
 *
 * No se tocan las URL ni los correos (un `_` adentro es parte del enlace), ni
 * el formato que ya es de WhatsApp (`*negrita*`, `_cursiva_`, `~tachado~`),
 * ni las viñetas.
 */
class FormatoWhatsapp
{
    /**
     * El texto del modelo, listo para WhatsApp. Idempotente.
     */
    public static function convertir(string $texto): string
    {
        if (trim($texto) === '') {
            return '';
        }

        $s = str_replace(["\r\n", "\r"], "\n", $texto);
        $protegidos = [];
        $proteger = function (string $valor) use (&$protegidos): string {
            $protegidos[] = $valor;

            return "\u{E000}".(count($protegidos) - 1)."\u{E001}";
        };

        // [texto](url) → «texto: url» (o sólo la url si el texto es la url).
        $s = (string) preg_replace_callback('/!?\[([^\]\n]*)\]\(\s*<?([^\s()<>]+)>?\s*\)/u', function (array $m) use ($proteger): string {
            $textoDelEnlace = trim($m[1]);
            $destino = $m[2];

            if ($textoDelEnlace === '' || self::sinEsquema($textoDelEnlace) === self::sinEsquema($destino)) {
                return $proteger($destino);
            }

            return $textoDelEnlace.': '.$proteger($destino);
        }, $s);
        $s = (string) preg_replace_callback('/<((?:https?:\/\/|www\.)[^\s>]+)>/iu', fn (array $m): string => $proteger($m[1]), $s);
        $s = (string) preg_replace_callback('/(?:https?:\/\/|www\.)[^\s<>"\'`\x{E000}\x{E001}]+/iu', function (array $m) use ($proteger): string {
            $url = rtrim($m[0], '.,;:!?¡¿*_~"\'');
            $cola = substr($m[0], strlen($url));

            return $proteger($url).$cola;
        }, $s);
        $s = (string) preg_replace_callback('/[\w.+-]+@[\w-]+(?:\.[\w-]+)+/u', fn (array $m): string => $proteger($m[0]), $s);

        // Código: se va el cerco, se queda el contenido.
        $s = (string) preg_replace('/```([^`\n]+)```/u', '$1', $s);
        $s = (string) preg_replace('/^[ \t]*```[^\n]*\n?/mu', '', $s);
        $s = (string) preg_replace('/`([^`\n]+)`/u', '$1', $s);
        $s = str_replace('`', '', $s);

        $s = self::tablas($s);
        $s = (string) preg_replace('/^[ \t]*([-*_])(?:[ \t]*\1){2,}[ \t]*$/mu', '', $s);

        $s = (string) preg_replace('/\*\*\*(?!\s)((?:(?!\*\*\*)[^\n])+?)(?<!\s)\*\*\*/u', '*_$1_*', $s);
        $s = (string) preg_replace('/\*\*(?!\s)((?:(?!\*\*)[^\n])+?)(?<!\s)\*\*/u', '*$1*', $s);
        $s = (string) preg_replace('/(?<!\w)__(?!\s)((?:(?!__)[^\n])+?)(?<!\s)__(?!\w)/u', '_$1_', $s);
        $s = (string) preg_replace('/~~(?!\s)((?:(?!~~)[^\n])+?)(?<!\s)~~/u', '~$1~', $s);
        $s = str_replace('**', '', $s);

        // Un título de Markdown pasa a negrita de WhatsApp.
        $s = (string) preg_replace_callback('/^[ \t]{0,3}#{1,6}[ \t]+(.+?)(?:[ \t]+#+)?[ \t]*$/mu', function (array $m): string {
            $titulo = trim($m[1], ' *_');

            return $titulo === '' ? '' : '*'.$titulo.'*';
        }, $s);

        $s = trim((string) preg_replace('/\n(?:[ \t]*\n){2,}/u', "\n\n", $s));

        return (string) preg_replace_callback('/\x{E000}(\d+)\x{E001}/u', fn (array $m): string => $protegidos[(int) $m[1]], $s);
    }

    /**
     * Las tablas de Markdown pasan a renglones «Encabezado: valor».
     */
    private static function tablas(string $s): string
    {
        $lineas = explode("\n", $s);
        $salida = [];
        $i = 0;

        while ($i < count($lineas)) {
            $esTabla = str_contains($lineas[$i], '|')
                && isset($lineas[$i + 1])
                && preg_match('/^\s*\|?\s*:?-+:?\s*(?:\|\s*:?-+:?\s*)*\|?\s*$/u', $lineas[$i + 1]) === 1;

            if (! $esTabla) {
                $salida[] = $lineas[$i];
                $i++;

                continue;
            }

            $encabezado = self::celdas($lineas[$i]);
            $i += 2;

            while ($i < count($lineas) && str_contains($lineas[$i], '|')) {
                $celdas = self::celdas($lineas[$i]);
                $partes = [];

                foreach ($celdas as $indice => $celda) {
                    if ($celda === '') {
                        continue;
                    }

                    $titulo = $encabezado[$indice] ?? '';
                    $partes[] = $titulo !== '' && $indice > 0 ? $titulo.': '.$celda : $celda;
                }

                $salida[] = '- '.implode(' · ', $partes);
                $i++;
            }
        }

        return implode("\n", $salida);
    }

    /**
     * @return list<string>
     */
    private static function celdas(string $linea): array
    {
        return array_map('trim', explode('|', trim(trim($linea), '|')));
    }

    private static function sinEsquema(string $url): string
    {
        return rtrim((string) preg_replace('/^(?:https?:\/\/)?(?:www\.)?/i', '', $url), '/');
    }
}
