<?php

namespace App\Models;

use App\Concerns\Auditable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

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
     * La conexión con la WhatsApp Cloud API de Meta.
     */
    public const CONEXION_WHATSAPP = 'conexion_whatsapp';

    /**
     * La conexión con el proveedor de IA (OpenRouter).
     */
    public const CONEXION_IA = 'conexion_ia';

    /**
     * El perfil del agente de IA de WhatsApp: quién es y cómo habla.
     */
    public const AGENTE_IA = 'agente_ia';

    /**
     * Los campos de cada conexión que se guardan cifrados y nunca vuelven al
     * navegador: el panel sólo ve si están cargados y sus últimos caracteres.
     *
     * @var array<string, list<string>>
     */
    public const SECRETOS = [
        self::CONEXION_WHATSAPP => ['token', 'app_secret'],
        self::CONEXION_IA => ['api_key'],
    ];

    /**
     * Cómo se llama cada grupo en la auditoría.
     *
     * @var array<string, string>
     */
    private const NOMBRES = [
        self::BOTON_WHATSAPP => 'Botón de WhatsApp',
        self::CONEXION_WHATSAPP => 'Conexión de WhatsApp',
        self::CONEXION_IA => 'Conexión de IA',
        self::AGENTE_IA => 'Agente de WhatsApp',
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
     * La conexión con Meta: lo cargado en el panel y, campo por campo, el
     * `.env` (`config('services.whatsapp')`) para lo que no se cargó.
     *
     * @return array{token: string|null, phone_number_id: string|null, waba_id: string|null, app_secret: string|null, verify_token: string|null, graph_version: string}
     */
    public static function conexionWhatsapp(): array
    {
        /** @var array{token: string|null, phone_number_id: string|null, waba_id: string|null, app_secret: string|null, verify_token: string|null, graph_version: string} */
        return self::conexion(self::CONEXION_WHATSAPP, (array) config('services.whatsapp'), [
            'token', 'phone_number_id', 'waba_id', 'app_secret', 'verify_token', 'graph_version',
        ]);
    }

    /**
     * La conexión con la IA, con el mismo respaldo en `.env`.
     *
     * @return array{api_key: string|null, modelo: string, modelo_transcripcion: string}
     */
    public static function conexionIa(): array
    {
        /** @var array{api_key: string|null, modelo: string, modelo_transcripcion: string} */
        return self::conexion(self::CONEXION_IA, [
            'api_key' => config('ai.providers.openrouter.key'),
            'modelo' => config('services.openrouter.modelo'),
            'modelo_transcripcion' => config('services.openrouter.modelo_transcripcion'),
        ], ['api_key', 'modelo', 'modelo_transcripcion']);
    }

    /**
     * El perfil del agente de WhatsApp. Apagado hasta que alguien lo prenda
     * desde el panel: contestarle a un cliente de verdad es una decisión.
     *
     * @return array{activo: bool, nombre: string, tono: string|null, instrucciones: string|null, reglas_escalado: string|null, saludo: string|null}
     */
    public static function agenteIa(): array
    {
        /** @var array{activo: bool, nombre: string, tono: string|null, instrucciones: string|null, reglas_escalado: string|null, saludo: string|null} */
        return self::valor(self::AGENTE_IA, [
            'activo' => false,
            'nombre' => 'Alfi',
            'tono' => null,
            'instrucciones' => null,
            'reglas_escalado' => null,
            'saludo' => null,
        ]);
    }

    /**
     * Guardar una conexión. Un secreto que llega vacío no se toca: el panel
     * nunca lo muestra, así que vacío quiere decir «dejá el que está».
     *
     * @param  array<string, string|null>  $nuevos
     */
    public static function guardarConexion(string $clave, array $nuevos): self
    {
        $guardado = self::where('clave', $clave)->value('valor') ?? [];
        $secretos = self::SECRETOS[$clave] ?? [];

        foreach ($nuevos as $campo => $valor) {
            $valor = $valor === null ? null : trim($valor);

            if (in_array($campo, $secretos, true)) {
                if ($valor === null || $valor === '') {
                    continue;
                }

                $valor = Crypt::encryptString($valor);
            }

            $guardado[$campo] = $valor === '' ? null : $valor;
        }

        return self::guardar($clave, $guardado);
    }

    /**
     * Borrar un secreto guardado: vuelve a valer el del `.env`, si hay.
     */
    public static function olvidarSecreto(string $clave, string $campo): void
    {
        $guardado = self::where('clave', $clave)->value('valor') ?? [];
        unset($guardado[$campo]);

        self::guardar($clave, $guardado);
    }

    /**
     * Cómo se muestra un secreto en el panel: cargado o no, y sus últimos
     * cuatro caracteres.
     *
     * @return array{cargado: bool, final: string|null, origen: 'panel'|'env'|null}
     */
    public static function resumenDeSecreto(string $clave, string $campo, ?string $valorEfectivo): array
    {
        $enPanel = isset((self::valor($clave))[$campo]);

        return [
            'cargado' => filled($valorEfectivo),
            'final' => filled($valorEfectivo) ? mb_substr((string) $valorEfectivo, -4) : null,
            'origen' => filled($valorEfectivo) ? ($enPanel ? 'panel' : 'env') : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $respaldo
     * @param  list<string>  $campos
     * @return array<string, mixed>
     */
    private static function conexion(string $clave, array $respaldo, array $campos): array
    {
        $guardado = self::valor($clave);
        $secretos = self::SECRETOS[$clave] ?? [];
        $conexion = [];

        foreach ($campos as $campo) {
            $valor = $guardado[$campo] ?? null;

            if ($valor !== null && in_array($campo, $secretos, true)) {
                try {
                    $valor = Crypt::decryptString((string) $valor);
                } catch (DecryptException) {
                    // Cambió el APP_KEY: el secreto ya no se puede leer.
                    $valor = null;
                }
            }

            $conexion[$campo] = filled($valor) ? $valor : ($respaldo[$campo] ?? null);
        }

        return $conexion;
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
