<?php

namespace App\Http\Controllers\Panel;

use App\Enums\DireccionMensaje;
use App\Enums\MotivoDerivacion;
use App\Http\Controllers\Controller;
use App\Models\Conversacion;
use App\Models\MensajeWhatsapp;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Str;

/**
 * Los avisos de WhatsApp para todo el panel: contadores y novedades.
 *
 * Lo pide `useAvisosWhatsapp` cada pocos segundos desde cualquier página.
 * Con el cursor de la respuesta anterior devuelve lo que pasó desde
 * entonces: mensajes entrantes (uno por conversación, el último) y
 * derivaciones de la IA. Sin cursor sólo devuelve contadores y el cursor
 * actual, para no avisar de lo viejo al abrir el panel.
 *
 * El cursor de derivaciones es el par (`derivada_at`, `id`) de la última:
 * `derivada_at` tiene resolución de segundos y dos derivaciones en el mismo
 * segundo no pueden perderse. Los dos cursores también acotan por arriba lo
 * que se devuelve, así lo que entra mientras se arma la respuesta llega en
 * el pedido siguiente y no dos veces.
 *
 * Del mensaje viaja sólo una vista previa corta: el cuerpo completo se lee
 * en la bandeja.
 */
class AvisoWhatsappController extends Controller implements HasMiddleware
{
    /**
     * Cuántos avisos se devuelven como mucho por pedido.
     */
    private const MAX_EVENTOS = 5;

    /**
     * Largo de la vista previa del mensaje.
     */
    private const LARGO_VISTA_PREVIA = 80;

    /**
     * Get the middleware that should be assigned to the controller.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,'.Conversacion::class),
        ];
    }

    /**
     * Counters plus whatever happened since the given cursor.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $cursor = $request->validate([
            'mensaje' => ['nullable', 'integer', 'min:0'],
            'derivacion' => ['nullable', 'integer', 'min:0'],
            'derivacionId' => ['nullable', 'integer', 'min:0'],
        ]);

        $desdeMensaje = isset($cursor['mensaje']) ? (int) $cursor['mensaje'] : null;
        $desdeDerivacion = isset($cursor['derivacion']) ? (int) $cursor['derivacion'] : null;
        // Un cursor sin id (pestaña abierta antes del cambio): todo ese segundo ya se avisó.
        $desdeDerivacionId = isset($cursor['derivacionId']) ? (int) $cursor['derivacionId'] : PHP_INT_MAX;

        $ultimoMensaje = (int) MensajeWhatsapp::query()
            ->where('direccion', DireccionMensaje::Entrante)
            ->max('id');

        $ultimaDerivacion = Conversacion::query()
            ->whereNotNull('derivada_at')
            ->orderByDesc('derivada_at')
            ->orderByDesc('id')
            ->first(['id', 'derivada_at']);

        $eventos = [
            ...$desdeMensaje === null ? [] : $this->mensajesNuevos($desdeMensaje, $ultimoMensaje),
            ...$desdeDerivacion === null || $ultimaDerivacion === null ? [] : $this->derivacionesNuevas($desdeDerivacion, $desdeDerivacionId, $ultimaDerivacion),
        ];

        usort($eventos, fn (array $a, array $b): int => strcmp($b['at'], $a['at']));

        return response()->json([
            ...Conversacion::contadoresDeAvisos(),
            'cursor' => [
                'mensaje' => $ultimoMensaje,
                'derivacion' => $ultimaDerivacion?->derivada_at?->getTimestamp() ?? 0,
                'derivacionId' => $ultimaDerivacion === null ? 0 : $ultimaDerivacion->id,
            ],
            'eventos' => array_slice($eventos, 0, self::MAX_EVENTOS),
        ]);
    }

    /**
     * El último mensaje entrante de cada conversación que escribió después
     * del cursor y hasta el cursor nuevo.
     *
     * @return list<array{conversacionId: int, nombre: string, preview: string, tipo: string, at: string}>
     */
    private function mensajesNuevos(int $desde, int $hasta): array
    {
        $ultimos = MensajeWhatsapp::query()
            ->selectRaw('max(id) as id')
            ->where('direccion', DireccionMensaje::Entrante)
            ->where('id', '>', $desde)
            ->where('id', '<=', $hasta)
            ->groupBy('conversacion_id');

        return MensajeWhatsapp::query()
            ->with('conversacion.cliente')
            ->whereIn('id', $ultimos)
            ->latest('id')
            ->limit(self::MAX_EVENTOS)
            ->get()
            ->map(fn (MensajeWhatsapp $mensaje): array => [
                'conversacionId' => $mensaje->conversacion_id,
                'nombre' => $mensaje->conversacion->nombreVisible(),
                'preview' => Str::limit($mensaje->vistaPrevia(), self::LARGO_VISTA_PREVIA),
                'tipo' => 'mensaje',
                'at' => $mensaje->created_at?->toIso8601String() ?? now()->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Las conversaciones que la IA pasó al equipo después del cursor.
     *
     * Las que tomó alguien a mano no avisan: ya lo sabe quien la tomó.
     *
     * @return list<array{conversacionId: int, nombre: string, preview: string, tipo: string, at: string}>
     */
    private function derivacionesNuevas(int $desde, int $desdeId, Conversacion $hasta): array
    {
        // En la zona de la app: así se guardan las fechas en la base.
        $desdeAt = CarbonImmutable::createFromTimestamp($desde, config('app.timezone'));

        return Conversacion::query()
            ->with('cliente')
            ->where(fn ($query) => $query
                ->where('derivada_at', '>', $desdeAt)
                ->orWhere(fn ($query) => $query->where('derivada_at', $desdeAt)->where('id', '>', $desdeId)))
            ->where(fn ($query) => $query
                ->where('derivada_at', '<', $hasta->derivada_at)
                ->orWhere(fn ($query) => $query->where('derivada_at', $hasta->derivada_at)->where('id', '<=', $hasta->id)))
            ->where(fn ($query) => $query
                ->whereNull('motivo_derivacion')
                ->orWhere('motivo_derivacion', '!=', MotivoDerivacion::Manual))
            ->whereNull('archivada_at')
            ->latest('derivada_at')
            ->limit(self::MAX_EVENTOS)
            ->get()
            ->map(fn (Conversacion $conversacion): array => [
                'conversacionId' => $conversacion->id,
                'nombre' => $conversacion->nombreVisible(),
                'preview' => $conversacion->motivo_derivacion?->label() ?? __('Necesita atención humana'),
                'tipo' => 'derivacion',
                'at' => $conversacion->derivada_at?->toIso8601String() ?? now()->toIso8601String(),
            ])
            ->all();
    }
}
