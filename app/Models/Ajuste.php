<?php

namespace App\Models;

use App\Concerns\Auditable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Un grupo de ajustes del sitio que se cambia desde el panel.
 *
 * Una fila por grupo (`clave`), con todos sus valores en `valor`. Lo que no
 * se guardó todavía sale de los valores por defecto de cada grupo, así el
 * sitio funciona igual en una base recién creada.
 *
 * Se lee en cada página pública (`ProvidesSiteInfo`), así que va cacheado y
 * el caché se limpia al guardar.
 *
 * @property int $id
 * @property string $clave
 * @property array<string, mixed> $valor
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['clave', 'valor'])]
class Ajuste extends Model
{
    use Auditable;

    /**
     * El botón flotante de WhatsApp del sitio público.
     */
    public const BOTON_WHATSAPP = 'boton_whatsapp';

    /**
     * Cómo se llama cada grupo en la auditoría.
     *
     * @var array<string, string>
     */
    private const NOMBRES = [
        self::BOTON_WHATSAPP => 'Botón de WhatsApp',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valor' => 'array',
        ];
    }

    /**
     * La clave del registro en la auditoría.
     */
    public function tipoDeAuditoria(): string
    {
        return 'ajuste';
    }

    /**
     * Cómo figura el ajuste en la auditoría.
     */
    public function etiquetaDeAuditoria(): string
    {
        return self::NOMBRES[$this->clave] ?? $this->clave;
    }

    /**
     * Los valores de un grupo: lo guardado sobre los valores por defecto.
     *
     * @param  array<string, mixed>  $porDefecto
     * @return array<string, mixed>
     */
    public static function valor(string $clave, array $porDefecto = []): array
    {
        $guardado = Cache::rememberForever(
            self::claveDeCache($clave),
            fn (): array => self::where('clave', $clave)->value('valor') ?? [],
        );

        return [...$porDefecto, ...(is_array($guardado) ? $guardado : [])];
    }

    /**
     * Guardar los valores de un grupo y limpiar su caché.
     *
     * @param  array<string, mixed>  $valor
     */
    public static function guardar(string $clave, array $valor): self
    {
        $ajuste = self::updateOrCreate(['clave' => $clave], ['valor' => $valor]);

        Cache::forget(self::claveDeCache($clave));

        return $ajuste;
    }

    /**
     * El botón de WhatsApp tal como se configura en el panel.
     *
     * Varios contactos, cada uno con su número y su horario, y uno marcado de
     * respaldo para cuando ninguno está en horario. Sin ajuste guardado queda
     * un solo contacto, siempre disponible, con el número de
     * `config('alfa.whatsapp')`.
     *
     * Una versión vieja del ajuste guardaba un único `numero`: se lee como un
     * solo contacto de respaldo, así nada se rompe hasta que se vuelva a
     * guardar desde el panel.
     *
     * @return array{activo: bool, titulo: string, subtitulo: string, contactos: list<array{nombre: string, detalle: string|null, numero: string, mensaje: string, respaldo: bool, horario: array{siempre: bool, dias: list<int>, desde: string, hasta: string}}>}
     */
    public static function botonWhatsapp(): array
    {
        $mensaje = 'Hola Alfa Automotores, quería hacer una consulta.';

        $valor = self::valor(self::BOTON_WHATSAPP, [
            'activo' => true,
            'titulo' => '¡Hola!',
            'subtitulo' => '¿Cómo podemos ayudarte?',
        ]);

        if (! isset($valor['contactos'])) {
            $valor['contactos'] = [
                self::contacto('Ventas', null, (string) ($valor['numero'] ?? config('alfa.whatsapp')), (string) ($valor['mensaje'] ?? $mensaje)),
            ];
        }

        /** @var array{activo: bool, titulo: string, subtitulo: string, contactos: list<array{nombre: string, detalle: string|null, numero: string, mensaje: string, respaldo: bool, horario: array{siempre: bool, dias: list<int>, desde: string, hasta: string}}>} */
        return [
            'activo' => (bool) $valor['activo'],
            'titulo' => (string) $valor['titulo'],
            'subtitulo' => (string) $valor['subtitulo'],
            'contactos' => array_values($valor['contactos']),
        ];
    }

    /**
     * Los contactos que se ofrecen en un momento dado.
     *
     * Los que están en horario, en el orden del panel. Si no hay ninguno, el
     * de respaldo, con `fueraDeHorario` en true para que la tarjeta lo diga.
     *
     * @return array{fueraDeHorario: bool, contactos: list<array{nombre: string, detalle: string|null, numero: string, mensaje: string}>}
     */
    public static function whatsappDisponible(CarbonInterface $ahora): array
    {
        $contactos = self::botonWhatsapp()['contactos'];

        $enHorario = array_values(array_filter(
            $contactos,
            fn (array $contacto): bool => self::estaEnHorario($contacto['horario'], $ahora),
        ));

        $fueraDeHorario = $enHorario === [];

        $elegidos = $fueraDeHorario
            ? array_values(array_filter($contactos, fn (array $contacto): bool => $contacto['respaldo']))
            : $enHorario;

        // Un ajuste mal armado sin respaldo no deja el sitio sin número.
        if ($elegidos === []) {
            $elegidos = array_slice($contactos, 0, 1);
        }

        return [
            'fueraDeHorario' => $fueraDeHorario,
            'contactos' => array_map(fn (array $contacto): array => [
                'nombre' => $contacto['nombre'],
                'detalle' => $contacto['detalle'],
                'numero' => $contacto['numero'],
                'mensaje' => $contacto['mensaje'],
            ], $elegidos),
        ];
    }

    /**
     * Si un horario incluye este momento.
     *
     * `dias` son los días ISO en que arranca la franja (1 lunes … 7 domingo).
     * Una franja que cruza la medianoche (18:00–08:30) sigue valiendo en la
     * madrugada del día siguiente.
     *
     * @param  array{siempre: bool, dias: list<int>, desde: string, hasta: string}  $horario
     */
    public static function estaEnHorario(array $horario, CarbonInterface $ahora): bool
    {
        if ($horario['siempre']) {
            return true;
        }

        $hora = $ahora->format('H:i');
        $hoy = $ahora->dayOfWeekIso;
        $ayer = $hoy === 1 ? 7 : $hoy - 1;
        $dias = array_map('intval', $horario['dias']);

        if ($horario['desde'] <= $horario['hasta']) {
            return in_array($hoy, $dias, true)
                && $hora >= $horario['desde']
                && $hora < $horario['hasta'];
        }

        return (in_array($hoy, $dias, true) && $hora >= $horario['desde'])
            || (in_array($ayer, $dias, true) && $hora < $horario['hasta']);
    }

    /**
     * Un contacto siempre disponible y de respaldo: el punto de partida.
     *
     * @return array{nombre: string, detalle: string|null, numero: string, mensaje: string, respaldo: bool, horario: array{siempre: bool, dias: list<int>, desde: string, hasta: string}}
     */
    private static function contacto(string $nombre, ?string $detalle, string $numero, string $mensaje): array
    {
        return [
            'nombre' => $nombre,
            'detalle' => $detalle,
            'numero' => $numero,
            'mensaje' => $mensaje,
            'respaldo' => true,
            'horario' => ['siempre' => true, 'dias' => [1, 2, 3, 4, 5, 6], 'desde' => '08:30', 'hasta' => '18:00'],
        ];
    }

    private static function claveDeCache(string $clave): string
    {
        return 'ajuste.'.$clave;
    }
}
