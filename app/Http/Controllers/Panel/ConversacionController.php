<?php

namespace App\Http\Controllers\Panel;

use App\Enums\DireccionMensaje;
use App\Enums\MotivoDerivacion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Whatsapp\EliminarConversacionRequest;
use App\Models\Cliente;
use App\Models\Conversacion;
use App\Models\MensajeWhatsapp;
use App\Models\PlantillaWhatsapp;
use App\Models\Turno;
use App\Services\WhatsappCloud;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * La bandeja de WhatsApp: la lista de conversaciones y el hilo abierto.
 *
 * `index` y `show` pintan la misma página; la diferencia es si hay una
 * conversación abierta. La página se refresca sola con `usePoll` pidiendo
 * sólo `conversaciones` y `conversacion`: no hay websockets.
 */
class ConversacionController extends Controller implements HasMiddleware
{
    private const POR_PAGINA = 60;

    /**
     * Los últimos mensajes que se pintan en el hilo.
     */
    private const MENSAJES = 200;

    /**
     * Get the middleware that should be assigned to the controller.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,'.Conversacion::class, only: ['index', 'abrir']),
            new Middleware('can:view,cliente', only: ['abrir']),
            new Middleware('can:view,conversacion', only: ['show', 'media']),
            new Middleware('can:update,conversacion', only: ['ia', 'archivar']),
            new Middleware('can:delete,conversacion', only: ['destroy']),
        ];
    }

    /**
     * Show the inbox with no conversation open.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('panel/whatsapp/index', [
            ...$this->bandeja($request),
            'conversacion' => null,
        ]);
    }

    /**
     * Show the inbox with one conversation open.
     */
    public function show(Request $request, Conversacion $conversacion, WhatsappCloud $whatsapp): Response
    {
        /* Abrirla la da por leída, también del lado del cliente (los dos
           tildes azules). Sólo si había algo sin leer: el polling vuelve a
           pedir esta ruta cada pocos segundos. */
        if ($conversacion->no_leidos > 0) {
            $conversacion->forceFill(['no_leidos' => 0])->saveQuietly();

            $ultimo = $conversacion->mensajes()
                ->where('direccion', DireccionMensaje::Entrante)
                ->whereNotNull('wa_message_id')
                ->reorder()
                ->latest('id')
                ->first();

            if ($ultimo !== null) {
                $whatsapp->marcarLeido((string) $ultimo->wa_message_id);
            }
        }

        $conversacion->load('cliente');

        return Inertia::render('panel/whatsapp/index', [
            ...$this->bandeja($request),
            'conversacion' => $this->detalle($request, $conversacion),
        ]);
    }

    /**
     * Open a client's conversation, creating it if they never wrote.
     *
     * Así el equipo contacta desde la cotización, la ficha o el turno sin
     * salir a un `wa.me`: lo que se manda queda en la bandeja.
     */
    public function abrir(Cliente $cliente): RedirectResponse
    {
        return to_route('panel.whatsapp.show', Conversacion::paraCliente($cliente));
    }

    /**
     * Turn the AI on or off for one conversation.
     *
     * Apagarla es tomarla: queda derivada al equipo. Prenderla la devuelve a
     * la IA aunque la hubiera derivado ella misma.
     */
    public function ia(Request $request, Conversacion $conversacion): RedirectResponse
    {
        $activa = $request->validate(['activa' => ['required', 'boolean']])['activa'];

        if ($activa) {
            $conversacion->devolverALaIa();
        } else {
            $conversacion->forceFill(['ia_activa' => false])->save();

            if ($conversacion->derivada_at === null) {
                $conversacion->derivar(MotivoDerivacion::Manual);
            }
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $activa
            ? __('La IA vuelve a contestar esta conversación.')
            : __('Tomaste la conversación: la IA no contesta hasta que la devuelvas.')]);

        return back();
    }

    /**
     * Archive or unarchive a conversation. A new message brings it back.
     */
    public function archivar(Request $request, Conversacion $conversacion): RedirectResponse
    {
        $archivar = $request->validate(['archivada' => ['required', 'boolean']])['archivada'];

        $conversacion->forceFill(['archivada_at' => $archivar ? now() : null])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => $archivar ? __('Conversación archivada.') : __('Conversación de vuelta en la bandeja.')]);

        return back();
    }

    /**
     * Delete a conversation with its messages and files, and optionally the
     * linked client with their other conversations.
     */
    public function destroy(EliminarConversacionRequest $request, Conversacion $conversacion): RedirectResponse
    {
        $tambienElCliente = $request->tambienElCliente();
        $teniaCliente = $conversacion->cliente_id !== null;

        $conversacion->olvidar($tambienElCliente);

        Inertia::flash('toast', ['type' => 'success', 'message' => $tambienElCliente && $teniaCliente
            ? __('Conversación y cliente eliminados.')
            : __('Conversación eliminada.')]);

        return to_route('panel.whatsapp.index');
    }

    /**
     * Serve one of the private attachments.
     */
    public function media(Conversacion $conversacion, MensajeWhatsapp $mensaje): StreamedResponse
    {
        abort_unless($mensaje->conversacion_id === $conversacion->id, 404);
        abort_if($mensaje->media_ruta === null || ! Storage::disk('local')->exists($mensaje->media_ruta), 404);

        return Storage::disk('local')->response(
            $mensaje->media_ruta,
            $mensaje->media_nombre,
            ['Content-Type' => (string) $mensaje->media_mime],
        );
    }

    /**
     * La lista de la izquierda, con sus filtros.
     *
     * @return array<string, mixed>
     */
    private function bandeja(Request $request): array
    {
        $filtros = [
            'busqueda' => $request->string('busqueda')->trim()->value(),
            'filtro' => in_array($request->query('filtro'), ['sin_leer', 'humano', 'archivadas'], true) ? $request->query('filtro') : '',
        ];

        return [
            'conversaciones' => Conversacion::deLaBandeja($filtros)
                ->limit(self::POR_PAGINA)
                ->get()
                ->map(fn (Conversacion $conversacion): array => [
                    'id' => $conversacion->id,
                    'nombre' => $conversacion->nombreVisible(),
                    'telefono' => '+'.$conversacion->wa_id,
                    'vistaPrevia' => $conversacion->ultimoMensaje?->vistaPrevia(),
                    'ultimoSaliente' => $conversacion->ultimoMensaje !== null && ! $conversacion->ultimoMensaje->esEntrante(),
                    'ultimoMensajeAt' => $conversacion->ultimo_mensaje_at?->toIso8601String(),
                    'noLeidos' => $conversacion->no_leidos,
                    'atiendeLaIa' => $conversacion->atiendeLaIa(),
                    'derivada' => $conversacion->derivada_at !== null,
                ])
                ->all(),
            'atencionHumana' => Conversacion::queAtiendeElEquipo()->count(),
            'filtros' => $filtros,
            'whatsappConfigurado' => app(WhatsappCloud::class)->configurado(),
        ];
    }

    /**
     * El hilo abierto y el panel del contacto.
     *
     * @return array<string, mixed>
     */
    private function detalle(Request $request, Conversacion $conversacion): array
    {
        $mensajes = $conversacion->mensajes()
            ->with('autor')
            ->reorder()
            ->latest('id')
            ->limit(self::MENSAJES)
            ->get()
            ->reverse()
            ->values();

        $cliente = $conversacion->cliente;

        return [
            'id' => $conversacion->id,
            'nombre' => $conversacion->nombreVisible(),
            'nombrePerfil' => $conversacion->nombre_perfil,
            'telefono' => '+'.$conversacion->wa_id,
            'iaActiva' => $conversacion->ia_activa,
            'derivada' => $conversacion->derivada_at !== null,
            'motivoDerivacion' => $conversacion->motivo_derivacion?->label(),
            'archivada' => $conversacion->archivada_at !== null,
            'ventanaAbierta' => $conversacion->ventanaAbierta(),
            'ventanaCierraAt' => $conversacion->ventanaCierraAt()?->toIso8601String(),
            'nuncaEscribio' => $conversacion->ultimo_entrante_at === null,
            'ficha' => $conversacion->ficha ?? [],
            'cliente' => $cliente === null ? null : [
                'id' => $cliente->id,
                'nombre' => $cliente->nombreCompleto(),
                'email' => $cliente->email,
                'turnos' => $cliente->turnos()
                    ->with('servicio')
                    ->limit(3)
                    ->get()
                    ->map(fn (Turno $turno): array => [
                        'id' => $turno->id,
                        'servicio' => $turno->servicio->nombre,
                        'iniciaAt' => $turno->inicia_at->toIso8601String(),
                        'estado' => $turno->estado->label(),
                    ])
                    ->all(),
            ],
            'mensajes' => $mensajes->map(fn (MensajeWhatsapp $mensaje): array => [
                'id' => $mensaje->id,
                'entrante' => $mensaje->esEntrante(),
                'tipo' => $mensaje->tipo->value,
                'tipoLabel' => $mensaje->tipo->etiqueta(),
                'cuerpo' => $mensaje->cuerpo,
                'estado' => $mensaje->estado->value,
                'error' => $mensaje->error,
                'generadoPorIa' => $mensaje->generado_por_ia,
                'autor' => $mensaje->autor?->name,
                'plantilla' => $mensaje->plantilla,
                'media' => $mensaje->media_ruta === null ? null : [
                    'url' => route('panel.whatsapp.media', [$conversacion, $mensaje]),
                    'mime' => $mensaje->media_mime,
                    'nombre' => $mensaje->media_nombre,
                ],
                'mediaPendiente' => $mensaje->media_id !== null && $mensaje->media_ruta === null,
                'transcripcion' => filled($mensaje->meta['transcripcion'] ?? null) ? (string) $mensaje->meta['transcripcion'] : null,
                'transcripcionFallida' => ($mensaje->meta['transcripcion_fallida'] ?? false) === true
                    || ($mensaje->meta['transcripcion'] ?? null) === '',
                'enviadoAt' => ($mensaje->wa_timestamp ?? $mensaje->created_at)?->toIso8601String(),
            ])->all(),
            'plantillas' => $conversacion->ventanaAbierta() ? [] : PlantillaWhatsapp::aprobadas()
                ->get()
                ->map(fn (PlantillaWhatsapp $plantilla): array => [
                    'id' => $plantilla->id,
                    'nombre' => $plantilla->nombre,
                    'idioma' => $plantilla->idioma,
                    'cuerpo' => $plantilla->cuerpo,
                    'variables' => $plantilla->variables,
                ])
                ->all(),
            'can' => [
                'delete' => $request->user()->can('delete', $conversacion),
                'administrar' => $request->user()->can('administrar', Conversacion::class),
            ],
            'puedeBorrarCliente' => $cliente !== null && $request->user()->can('delete', $cliente),
        ];
    }
}
