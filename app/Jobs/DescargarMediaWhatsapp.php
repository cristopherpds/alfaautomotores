<?php

namespace App\Jobs;

use App\Exceptions\WhatsappException;
use App\Models\Conversacion;
use App\Models\MensajeWhatsapp;
use App\Services\WhatsappCloud;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Bajar el adjunto de un mensaje entrante al disco privado.
 *
 * Meta guarda los archivos poco tiempo y su URL vence en minutos, así que se
 * copian enseguida. Va aparte de la ingesta para que un archivo que no baja
 * reintente solo sin volver a procesar el webhook entero. Si el mensaje ya
 * no existe (se borró la conversación), el trabajo se descarta sin fallar.
 */
#[DeleteWhenMissingModels]
class DescargarMediaWhatsapp implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60];

    public function __construct(public MensajeWhatsapp $mensaje) {}

    public function handle(WhatsappCloud $whatsapp): void
    {
        if ($this->mensaje->media_id === null || $this->mensaje->media_ruta !== null) {
            return;
        }

        try {
            $archivo = $whatsapp->descargarMedia($this->mensaje->media_id);
        } catch (WhatsappException $error) {
            if ($this->attempts() >= $this->tries) {
                $this->mensaje->update(['error' => 'No se pudo bajar el adjunto: '.$error->getMessage()]);

                return;
            }

            throw $error;
        }

        $mime = $this->mensaje->media_mime ?? $archivo['mime'];
        /* Un documento trae su nombre: su extensión dice más que el mime
           (un .docx llega como `application/vnd…`). */
        $extension = strtolower(pathinfo((string) $this->mensaje->media_nombre, PATHINFO_EXTENSION));
        $extension = preg_match('/^[a-z0-9]{1,5}$/', $extension) === 1 ? $extension : self::extension($mime);
        $ruta = Conversacion::CARPETA.'/'.$this->mensaje->conversacion_id.'/'.Str::ulid().'.'.$extension;

        Storage::disk('local')->put($ruta, $archivo['contenido']);

        /* La conversación se borró mientras bajaba: `olvidar()` ya limpió la
           carpeta y este archivo quedaría huérfano. */
        if (! MensajeWhatsapp::whereKey($this->mensaje->getKey())->exists()) {
            Storage::disk('local')->delete($ruta);

            return;
        }

        $this->mensaje->update(['media_ruta' => $ruta, 'media_mime' => $mime]);
    }

    /**
     * La extensión de un mime de WhatsApp. `audio/ogg; codecs=opus` es la
     * nota de voz.
     */
    public static function extension(string $mime): string
    {
        $base = strtolower(trim(explode(';', $mime)[0]));

        return match ($base) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'audio/ogg' => 'ogg',
            'audio/mpeg' => 'mp3',
            'audio/mp4', 'audio/aac' => 'm4a',
            'audio/amr' => 'amr',
            'video/mp4' => 'mp4',
            'video/3gpp' => '3gp',
            'application/pdf' => 'pdf',
            default => 'bin',
        };
    }
}
