<?php

namespace App\Ai;

/**
 * Las reglas que no se le dejan al modelo, portadas de nea-agent.
 *
 * Contar entre turnos y reconocer un pedido de humano es justo lo que un LLM
 * hace de forma poco confiable: a veces cuenta y a veces sigue vendiendo.
 * Acá es determinista y el modelo sólo pone la redacción.
 */
class Guardas
{
    /**
     * Mensajes hostiles seguidos que cierran la conversación con una línea
     * digna y la pasan al equipo.
     */
    public const RACHA_HOSTIL = 3;

    /**
     * Agresión dirigida y desprecio, en registro rioplatense. Conservador a
     * propósito: «la puta madre» o «qué garrón» solos no cuentan, así habla
     * Uruguay. Un falso positivo cuesta poco: el equipo devuelve la
     * conversación a la IA con un clic.
     *
     * @var list<string>
     */
    private const HOSTILES = [
        '/\b(and[aá]|vay[aá]n?se)\s+a\s+la\s+(mierda|concha|puta)/iu',
        '/\b(la\s+concha\s+de\s+(tu|su)\s+madre|hij[oa]s?\s+de\s+(puta|mil\s+putas))\b/iu',
        '/\b(pelotud[oa]s?|bolud[oa]s?\s+de\s+mierda|imb[eé]cil(es)?|est[uú]pid[oa]s?|idiotas?|tarad[oa]s?|forr[oa]s?|nabos?)\b/iu',
        '/\b(estafador(es)?|estafas?|chorr[oa]s?|ladr[oó]n(es)?|fraudes?|rat[ae]s?)\b/iu',
        '/\b(basura|porquer[ií]a|mierdas?|cagada)\b/iu',
        '/\b(puro\s+verso|pura\s+mentira|son\s+unos\s+chantas|chantas?)\b/iu',
    ];

    /**
     * Pedidos explícitos de una persona: van a la primera, sin preguntar.
     *
     * @var list<string>
     */
    private const PIDE_HUMANO = [
        '/\b(hablar|habl[aá]rlo|comunicarme|atenderme)\s+(con\s+)?(una?\s+)?(persona|humano|asesor|vendedor|encargado|alguien\s+(real|del\s+local))\b/iu',
        '/\b(quiero|necesito|pasame|p[aá]same\s+con|me\s+pas[aá]s\s+con)\s+(una?\s+)?(persona|humano|asesor|vendedor|operador)\b/iu',
        '/\b(atenci[oó]n\s+humana|no\s+quiero\s+(hablar\s+con\s+)?(un\s+)?(bot|robot|m[aá]quina|ia))\b/iu',
    ];

    public static function esHostil(string $texto): bool
    {
        foreach (self::HOSTILES as $patron) {
            if (preg_match($patron, $texto) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cuántos mensajes hostiles seguidos hay al final. Uno tranquilo corta.
     *
     * @param  list<string>  $textosDelCliente  del más viejo al más nuevo
     */
    public static function rachaHostil(array $textosDelCliente): int
    {
        $racha = 0;

        foreach (array_reverse($textosDelCliente) as $texto) {
            if (! self::esHostil($texto)) {
                break;
            }

            $racha++;
        }

        return $racha;
    }

    public static function pideHumano(string $texto): bool
    {
        foreach (self::PIDE_HUMANO as $patron) {
            if (preg_match($patron, $texto) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Por qué una respuesta no puede salir, o null si puede.
     *
     * Dos fallos que el cliente sí ve y que ningún prompt evita del todo:
     * repetirle palabra por palabra un mensaje que ya tiene, y mandarle una
     * nota interna entre paréntesis («(registro actualizado)»).
     *
     * @param  list<string>  $previos  lo que ya se le mandó
     */
    public static function respuestaInvalida(string $texto, array $previos): ?string
    {
        $plano = trim((string) preg_replace('/\s+/u', ' ', $texto));

        if ($plano === '') {
            return 'vacía';
        }

        if (preg_match('/^[(\[].*[)\]]$/su', $plano) === 1) {
            return 'era una nota interna entre paréntesis';
        }

        if (mb_strlen($plano) >= 40) {
            foreach ($previos as $previo) {
                if ($plano === trim((string) preg_replace('/\s+/u', ' ', $previo))) {
                    return 'repetía palabra por palabra un mensaje anterior';
                }
            }
        }

        return null;
    }
}
