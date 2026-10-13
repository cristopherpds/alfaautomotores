<?php

namespace App\Jobs;

use App\Ai\Agents\AsistenteWhatsapp;
use App\Ai\FormatoWhatsapp;
use App\Ai\Guardas;
use App\Ai\Prompt;
use App\Enums\DireccionMensaje;
use App\Enums\MotivoDerivacion;
use App\Enums\TipoMensaje;
use App\Models\Ajuste;
use App\Models\Auditoria;
use App\Models\Conversacion;
use App\Models\MensajeWhatsapp;
use App\Services\Mensajero;
use App\Services\WhatsappCloud;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Laravel\Ai\Ai;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Laravel\Ai\Files\Image;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Transcription;
use Throwable;

/**
 * Un turno del agente de IA: contestar lo que la persona escribió desde la
 * última respuesta.
 *
 * Lo despacha el webhook con una espera (`ESPERA_SEGUNDOS`) por cada mensaje
 * que entra. Si al correr ya entró uno más nuevo, este job no hace nada: el
 * del último contesta toda la ráfaga junta («coalesce» de nea-agent). Si
 * algo falla, la persona no recibe texto roto: silencio y la conversación
 * pasa al equipo con motivo `error`.
 */
class ResponderConversacion implements ShouldQueue
{
    use Queueable;

    /**
     * Cuánto se espera a que la persona termine de escribir.
     */
    public const ESPERA_SEGUNDOS = 8;

    /**
     * Mensajes anteriores que van como historial.
     */
    private const HISTORIAL = 30;

    /**
     * Segundos que se espera al modelo en cada intento, como nea-agent.
     */
    private const TIMEOUT_SEGUNDOS = 45;

    /**
     * Esperas antes de cada reintento ante una falla pasajera.
     *
     * @var list<int>
     */
    private const ESPERAS_REINTENTO = [2, 5];

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(public int $conversacionId, public int $hastaMensajeId) {}

    /**
     * Si el agente está en condiciones de contestar esta conversación.
     */
    public static function puedeContestar(Conversacion $conversacion): bool
    {
        $deprueba = Ajuste::numerosDePrueba();

        return Ajuste::agenteIa()['activo']
            && ($deprueba === [] || in_array($conversacion->wa_id, $deprueba, true))
            && filled(Ajuste::conexionIa()['api_key'])
            && app(WhatsappCloud::class)->configurado()
            && $conversacion->atiendeLaIa()
            && $conversacion->ventanaAbierta();
    }

    public function handle(Mensajero $mensajero, WhatsappCloud $whatsapp): void
    {
        $conversacion = Conversacion::find($this->conversacionId);

        if ($conversacion === null || ! self::puedeContestar($conversacion)) {
            return;
        }

        // Entró algo después: lo contesta el job de ese mensaje, todo junto.
        if ($this->ultimoEntrante($conversacion) > $this->hastaMensajeId) {
            return;
        }

        $candado = Cache::lock('wa-turno:'.$conversacion->id, $this->timeout);

        if (! $candado->get()) {
            $this->release(self::ESPERA_SEGUNDOS);

            return;
        }

        try {
            Auditoria::como(Auditoria::AGENTE_IA, fn () => $this->turno($conversacion, $mensajero, $whatsapp));
        } finally {
            $candado->release();
        }
    }

    private function turno(Conversacion $conversacion, Mensajero $mensajero, WhatsappCloud $whatsapp): void
    {
        $pendientes = $this->pendientes($conversacion);

        if ($pendientes->isEmpty()) {
            return;
        }

        $this->configurarProveedor();
        $this->transcribirAudios($pendientes);

        $veImagenes = self::modeloVeImagenes(Ajuste::conexionIa()['modelo']);
        $texto = $pendientes->map(fn (MensajeWhatsapp $mensaje): string => self::comoTexto($mensaje, $veImagenes))->implode("\n");

        if (Guardas::pideHumano($texto)) {
            $this->derivarConAviso($conversacion, $mensajero, MotivoDerivacion::Cliente, 'Dale, te paso con alguien del equipo. En un ratito te escriben por acá 🙌');

            return;
        }

        $alertaHostil = Guardas::rachaHostil($this->textosDelCliente($conversacion)) >= Guardas::RACHA_HOSTIL;

        $whatsapp->marcarLeido((string) $pendientes->last()->wa_message_id, escribiendo: true);

        try {
            $respuesta = $this->pensar($conversacion, $texto, $pendientes, $alertaHostil);
        } catch (Throwable $error) {
            Log::error('El agente de WhatsApp no pudo contestar', [
                'conversacion' => $conversacion->id,
                'error' => $error->getMessage(),
            ]);

            $conversacion->derivar(MotivoDerivacion::Error);

            return;
        }

        $conversacion->refresh();
        $final = FormatoWhatsapp::convertir((string) $respuesta->text);

        if (Guardas::respuestaInvalida($final, $this->salientesPrevios($conversacion)) !== null) {
            /* Si la herramienta ya derivó, igual se avisa; si no, mejor callar
               que mandar una nota interna o una repetición. */
            if ($conversacion->derivada_at !== null) {
                $final = 'Te paso con alguien del equipo, en un ratito te escriben por acá.';
            } else {
                Log::warning('Respuesta del agente descartada', ['conversacion' => $conversacion->id]);
                $conversacion->derivar(MotivoDerivacion::Error);

                return;
            }
        }

        try {
            $mensajero->texto($conversacion, $final, generadoPorIa: true);
        } catch (Throwable $error) {
            Log::error('No se pudo mandar la respuesta del agente', ['conversacion' => $conversacion->id, 'error' => $error->getMessage()]);
            $conversacion->refresh()->derivar(MotivoDerivacion::Error);

            return;
        }

        if ($alertaHostil && $conversacion->refresh()->derivada_at === null) {
            $conversacion->derivar(MotivoDerivacion::Hostilidad);
        }
    }

    /**
     * Llamar al modelo, con los reintentos de nea-agent.
     *
     * Un corte de red, un proveedor saturado o un límite de pedidos se
     * reintentan con espera; sin crédito no tiene sentido y se corta
     * enseguida. Si el modelo rechaza las opciones de velocidad, se vuelve a
     * pedir sin ellas: bajar el tiempo no puede costar la respuesta.
     *
     * @param  Collection<int, MensajeWhatsapp>  $pendientes
     *
     * @throws Throwable
     */
    private function pensar(Conversacion $conversacion, string $texto, Collection $pendientes, bool $alertaHostil): AgentResponse
    {
        $modelo = Ajuste::conexionIa()['modelo'];
        $rapido = true;
        $intento = 0;

        while (true) {
            $intento++;

            try {
                return AsistenteWhatsapp::make(
                    conversacion: $conversacion,
                    sistema: Prompt::construir($conversacion->load('cliente'), Ajuste::agenteIa(), $alertaHostil),
                    historial: $this->historial($conversacion, (int) $pendientes->first()->id),
                    rapido: $rapido,
                )->prompt(
                    $texto,
                    attachments: $this->imagenes($pendientes),
                    provider: 'openrouter',
                    model: $modelo,
                    timeout: self::TIMEOUT_SEGUNDOS,
                );
            } catch (InsufficientCreditsException $error) {
                throw $error;
            } catch (ProviderConnectionException|ProviderOverloadedException|RateLimitedException $error) {
                if ($intento > count(self::ESPERAS_REINTENTO)) {
                    throw $error;
                }

                Log::warning('Reintentando el turno del agente', ['conversacion' => $conversacion->id, 'intento' => $intento, 'error' => $error->getMessage()]);
                Sleep::for(self::ESPERAS_REINTENTO[$intento - 1])->seconds();
            } catch (Throwable $error) {
                if (! $rapido) {
                    throw $error;
                }

                Log::warning('Reintentando sin las opciones de velocidad', ['conversacion' => $conversacion->id, 'error' => $error->getMessage()]);
                $rapido = false;
            }
        }
    }

    /**
     * La clave y el modelo salen del panel (Ajustes → Conexiones), no del
     * `.env`: se pisan en caliente antes de cada turno.
     */
    private function configurarProveedor(): void
    {
        config(['ai.providers.openrouter.key' => Ajuste::conexionIa()['api_key']]);
        Ai::forgetInstance('openrouter');
    }

    /**
     * Lo que la persona escribió desde la última respuesta.
     *
     * @return Collection<int, MensajeWhatsapp>
     */
    private function pendientes(Conversacion $conversacion): Collection
    {
        $ultimaSalida = (int) $conversacion->mensajes()
            ->where('direccion', DireccionMensaje::Saliente)
            ->reorder()
            ->max('id');

        return $conversacion->mensajes()
            ->where('direccion', DireccionMensaje::Entrante)
            ->where('tipo', '!=', TipoMensaje::Reaccion)
            ->where('id', '>', $ultimaSalida)
            ->get();
    }

    private function ultimoEntrante(Conversacion $conversacion): int
    {
        return (int) $conversacion->mensajes()
            ->where('direccion', DireccionMensaje::Entrante)
            ->where('tipo', '!=', TipoMensaje::Reaccion)
            ->reorder()
            ->max('id');
    }

    /**
     * Las notas de voz pasan a texto una sola vez: la transcripción queda en
     * `meta` y sirve para el historial de los turnos siguientes.
     *
     * @param  Collection<int, MensajeWhatsapp>  $pendientes
     */
    private function transcribirAudios(Collection $pendientes): void
    {
        foreach ($pendientes as $mensaje) {
            if ($mensaje->tipo !== TipoMensaje::Audio || $mensaje->media_ruta === null || isset($mensaje->meta['transcripcion'])) {
                continue;
            }

            try {
                $transcripcion = trim((string) Transcription::fromStorage($mensaje->media_ruta, 'local')
                    ->generate('openrouter', Ajuste::conexionIa()['modelo_transcripcion']));
            } catch (Throwable $error) {
                Log::warning('No se pudo transcribir una nota de voz', ['mensaje' => $mensaje->id, 'error' => $error->getMessage()]);
                $transcripcion = '';
            }

            $mensaje->meta = [...($mensaje->meta ?? []), 'transcripcion' => $transcripcion];
            $mensaje->save();
        }
    }

    /**
     * Cómo ve el modelo un mensaje entrante. Los [corchetes] son del sistema.
     */
    public static function comoTexto(MensajeWhatsapp $mensaje, bool $veImagenes = true): string
    {
        $cuerpo = trim((string) $mensaje->cuerpo);

        $marca = match ($mensaje->tipo) {
            TipoMensaje::Texto, TipoMensaje::Interactivo => null,
            TipoMensaje::Audio => isset($mensaje->meta['transcripcion']) && $mensaje->meta['transcripcion'] !== ''
                ? '[Nota de voz, transcripta]: '.$mensaje->meta['transcripcion']
                : '[Nota de voz que no se pudo escuchar]',
            TipoMensaje::Imagen => match (true) {
                $mensaje->media_ruta === null => '[Foto que no se pudo abrir]',
                $veImagenes => '[Foto adjunta]',
                default => '[Foto que mandó y vos no podés ver: queda guardada para la cotización]',
            },
            TipoMensaje::Ubicacion => '[Ubicación]',
            TipoMensaje::Sticker => '[Sticker]',
            default => '['.$mensaje->tipo->etiqueta().' que no podés abrir]',
        };

        return trim(implode(' ', array_filter([$marca, $cuerpo !== '' ? $cuerpo : null])));
    }

    /**
     * Si el modelo acepta imágenes, según la lista de OpenRouter que trae
     * «Traer modelos» (guardada una hora). Sin la lista no se sabe y se
     * asume que sí: mandarle una foto a un modelo que no la acepta hace
     * fallar el turno entero, así que con la lista a mano no se arriesga.
     */
    public static function modeloVeImagenes(string $modelo): bool
    {
        $lista = Cache::get('openrouter:modelos');

        if (! is_array($lista)) {
            return true;
        }

        $datos = collect($lista)->firstWhere('id', $modelo);

        return ! is_array($datos)
            || in_array('image', (array) ($datos['architecture']['input_modalities'] ?? []), true);
    }

    /**
     * Las fotos de la ráfaga, para que el modelo las vea.
     *
     * @param  Collection<int, MensajeWhatsapp>  $pendientes
     * @return list<Image>
     */
    private function imagenes(Collection $pendientes): array
    {
        if (! self::modeloVeImagenes(Ajuste::conexionIa()['modelo'])) {
            return [];
        }

        return $pendientes
            ->filter(fn (MensajeWhatsapp $mensaje): bool => $mensaje->tipo === TipoMensaje::Imagen && $mensaje->media_ruta !== null)
            ->map(fn (MensajeWhatsapp $mensaje) => Image::fromStorage($mensaje->media_ruta, 'local'))
            ->values()
            ->all();
    }

    /**
     * La conversación anterior a la ráfaga, como mensajes de chat.
     *
     * @return list<Message>
     */
    private function historial(Conversacion $conversacion, int $antesDe): array
    {
        return $conversacion->mensajes()
            ->where('id', '<', $antesDe)
            ->where('tipo', '!=', TipoMensaje::Reaccion)
            ->reorder()
            ->latest('id')
            ->limit(self::HISTORIAL)
            ->get()
            ->reverse()
            ->map(fn (MensajeWhatsapp $mensaje): Message => $mensaje->esEntrante()
                ? new UserMessage(self::comoTexto($mensaje) ?: '[mensaje vacío]')
                : new AssistantMessage(trim((string) $mensaje->cuerpo) ?: '['.$mensaje->tipo->etiqueta().']'))
            ->values()
            ->all();
    }

    /**
     * Los textos de la persona, para contar la racha hostil.
     *
     * @return list<string>
     */
    private function textosDelCliente(Conversacion $conversacion): array
    {
        return $conversacion->mensajes()
            ->where('direccion', DireccionMensaje::Entrante)
            ->whereNotNull('cuerpo')
            ->reorder()
            ->latest('id')
            ->limit(10)
            ->pluck('cuerpo')
            ->reverse()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function salientesPrevios(Conversacion $conversacion): array
    {
        return $conversacion->mensajes()
            ->where('direccion', DireccionMensaje::Saliente)
            ->whereNotNull('cuerpo')
            ->reorder()
            ->latest('id')
            ->limit(20)
            ->pluck('cuerpo')
            ->all();
    }

    private function derivarConAviso(Conversacion $conversacion, Mensajero $mensajero, MotivoDerivacion $motivo, string $aviso): void
    {
        $conversacion->derivar($motivo);

        try {
            $mensajero->texto($conversacion, $aviso, generadoPorIa: true);
        } catch (Throwable $error) {
            Log::warning('No se pudo avisar la derivación', ['conversacion' => $conversacion->id, 'error' => $error->getMessage()]);
        }
    }
}
