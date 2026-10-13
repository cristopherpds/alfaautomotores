<?php

namespace App\Jobs;

use App\Enums\DireccionMensaje;
use App\Enums\EstadoMensaje;
use App\Enums\EstadoPlantilla;
use App\Enums\TipoMensaje;
use App\Models\Ajuste;
use App\Models\Conversacion;
use App\Models\MensajeWhatsapp;
use App\Models\PlantillaWhatsapp;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Un POST del webhook de Meta, procesado fuera del request.
 *
 * El controlador responde 200 enseguida (Meta reintenta y termina dando de
 * baja a los webhooks lentos o que fallan) y encola esto. Todo es idempotente:
 * un mensaje repetido no se duplica y un estado viejo no pisa uno nuevo.
 */
class ProcesarWebhookWhatsapp implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public array $payload) {}

    public function handle(): void
    {
        foreach ($this->payload['entry'] ?? [] as $entrada) {
            foreach ($entrada['changes'] ?? [] as $cambio) {
                $valor = (array) ($cambio['value'] ?? []);

                match ($cambio['field'] ?? null) {
                    'messages' => $this->mensajes($valor),
                    'message_template_status_update' => $this->estadoDePlantilla($valor),
                    default => null,
                };
            }
        }
    }

    /**
     * @param  array<string, mixed>  $valor
     */
    private function mensajes(array $valor): void
    {
        /* Otro número de la misma cuenta de Meta no es asunto de este local. */
        $numero = $valor['metadata']['phone_number_id'] ?? null;

        if ($numero !== null && (string) $numero !== (string) Ajuste::conexionWhatsapp()['phone_number_id']) {
            return;
        }

        $nombres = collect($valor['contacts'] ?? [])
            ->mapWithKeys(fn (array $contacto): array => [(string) $contacto['wa_id'] => $contacto['profile']['name'] ?? null]);

        foreach ($valor['messages'] ?? [] as $mensaje) {
            $this->entrante((array) $mensaje, $nombres->get((string) ($mensaje['from'] ?? '')));
        }

        foreach ($valor['statuses'] ?? [] as $estado) {
            $this->estado((array) $estado);
        }
    }

    /**
     * @param  array<string, mixed>  $mensaje
     */
    private function entrante(array $mensaje, ?string $nombrePerfil): void
    {
        $waMessageId = (string) ($mensaje['id'] ?? '');

        if ($waMessageId === '' || MensajeWhatsapp::where('wa_message_id', $waMessageId)->exists()) {
            return;
        }

        $tipoMeta = (string) ($mensaje['type'] ?? '');
        $tipo = TipoMensaje::desdeMeta($tipoMeta);
        $media = $tipo->claveDeMedia() !== null ? (array) ($mensaje[$tipo->claveDeMedia()] ?? []) : [];
        /* Meta manda segundos Unix. `createFromTimestamp()` los devuelve en
           UTC y la base guarda la hora sin zona: hay que pasarla a la hora de
           la app o queda corrida tres horas (y la ventana de 24 h con ella). */
        $cuando = isset($mensaje['timestamp'])
            ? Carbon::createFromTimestamp((int) $mensaje['timestamp'])->setTimezone(config('app.timezone'))
            : now();

        $creado = DB::transaction(function () use ($mensaje, $nombrePerfil, $waMessageId, $tipo, $tipoMeta, $media, $cuando): ?MensajeWhatsapp {
            $conversacion = Conversacion::delNumero((string) $mensaje['from'], $nombrePerfil);

            $registro = $conversacion->mensajes()->firstOrCreate(['wa_message_id' => $waMessageId], [
                'direccion' => DireccionMensaje::Entrante,
                'tipo' => $tipo,
                'cuerpo' => $this->cuerpo($mensaje, $tipoMeta),
                'media_id' => $media['id'] ?? null,
                'media_mime' => $media['mime_type'] ?? null,
                'media_nombre' => $media['filename'] ?? null,
                'estado' => EstadoMensaje::Recibido,
                'wa_timestamp' => $cuando,
                'meta' => array_filter([
                    'referral' => $mensaje['referral'] ?? null,
                    'context' => $mensaje['context'] ?? null,
                    'voz' => $media['voice'] ?? null,
                ]) ?: null,
            ]);

            if (! $registro->wasRecentlyCreated) {
                return null;
            }

            /* Una reacción no reabre nada ni cuenta como sin leer: es un 👍 a
               un mensaje viejo. Todo lo demás sí. */
            if ($tipo !== TipoMensaje::Reaccion) {
                $conversacion->forceFill([
                    'ultimo_entrante_at' => $cuando,
                    'ultimo_mensaje_at' => $cuando,
                    'no_leidos' => $conversacion->no_leidos + 1,
                    'archivada_at' => null,
                ])->saveQuietly();
            }

            $conversacion->vincularCliente();

            return $registro;
        });

        if ($creado === null) {
            return;
        }

        if ($creado->media_id !== null) {
            DescargarMediaWhatsapp::dispatch($creado);
        }

        /* Un turno del agente por mensaje, con espera: si entra otro antes de
           que corra, éste no hace nada y el del último contesta todo junto. */
        if ($tipo !== TipoMensaje::Reaccion && ResponderConversacion::puedeContestar($creado->conversacion)) {
            ResponderConversacion::dispatch($creado->conversacion_id, $creado->id)
                ->delay(now()->addSeconds(ResponderConversacion::ESPERA_SEGUNDOS));
        }
    }

    /**
     * El texto de un mensaje según su tipo. Los adjuntos traen su epígrafe.
     *
     * @param  array<string, mixed>  $mensaje
     */
    private function cuerpo(array $mensaje, string $tipoMeta): ?string
    {
        return match ($tipoMeta) {
            'text' => $mensaje['text']['body'] ?? null,
            'image', 'video', 'document' => $mensaje[$tipoMeta]['caption'] ?? null,
            'button' => $mensaje['button']['text'] ?? null,
            'interactive' => $mensaje['interactive']['button_reply']['title']
                ?? $mensaje['interactive']['list_reply']['title']
                ?? null,
            'reaction' => $mensaje['reaction']['emoji'] ?? null,
            'location' => trim(implode(' · ', array_filter([
                $mensaje['location']['name'] ?? null,
                $mensaje['location']['address'] ?? null,
                isset($mensaje['location']['latitude'])
                    ? 'https://maps.google.com/?q='.$mensaje['location']['latitude'].','.$mensaje['location']['longitude']
                    : null,
            ]))) ?: null,
            'contacts' => collect($mensaje['contacts'] ?? [])
                ->map(fn (array $contacto): string => trim(($contacto['name']['formatted_name'] ?? '').' '.($contacto['phones'][0]['phone'] ?? '')))
                ->implode("\n") ?: null,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $estado
     */
    private function estado(array $estado): void
    {
        $nuevo = EstadoMensaje::desdeMeta((string) ($estado['status'] ?? ''));
        $mensaje = MensajeWhatsapp::where('wa_message_id', (string) ($estado['id'] ?? ''))->first();

        if ($nuevo === null || $mensaje === null) {
            return;
        }

        $error = $estado['errors'][0] ?? null;

        $mensaje->avanzarA($nuevo, $error === null ? null : (string) (
            $error['error_data']['details'] ?? $error['message'] ?? $error['title'] ?? 'Error de entrega'
        ));
    }

    /**
     * @param  array<string, mixed>  $valor
     */
    private function estadoDePlantilla(array $valor): void
    {
        $plantilla = PlantillaWhatsapp::query()
            ->when(
                isset($valor['message_template_id']),
                fn ($query) => $query->where('wa_template_id', (string) $valor['message_template_id']),
                fn ($query) => $query->where('nombre', $valor['message_template_name'] ?? '')
                    ->where('idioma', $valor['message_template_language'] ?? ''),
            )
            ->first();

        $plantilla?->update([
            'estado' => EstadoPlantilla::desdeMeta((string) ($valor['event'] ?? '')),
            'motivo_rechazo' => ($valor['reason'] ?? 'NONE') === 'NONE' ? null : $valor['reason'],
        ]);
    }
}
