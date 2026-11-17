<?php

namespace App\Services;

use App\Enums\DireccionMensaje;
use App\Enums\EstadoMensaje;
use App\Enums\TipoMensaje;
use App\Exceptions\WhatsappException;
use App\Models\Conversacion;
use App\Models\MensajeWhatsapp;
use App\Models\PlantillaWhatsapp;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * El único camino de salida hacia WhatsApp: lo usan el panel y el agente.
 *
 * Cada envío deja primero el mensaje como `pendiente` en la conversación y
 * después habla con Meta, así lo que falla también queda a la vista (como
 * `fallido`, con el motivo) en vez de perderse.
 */
class Mensajero
{
    public function __construct(private WhatsappCloud $whatsapp) {}

    /**
     * @throws WhatsappException
     */
    public function texto(Conversacion $conversacion, string $texto, ?User $autor = null, bool $generadoPorIa = false): MensajeWhatsapp
    {
        $this->exigirVentana($conversacion);

        $mensaje = $this->borrador($conversacion, TipoMensaje::Texto, $texto, $autor, $generadoPorIa);

        return $this->despachar($mensaje, fn (): string => $this->whatsapp->enviarTexto($conversacion->wa_id, $texto));
    }

    /**
     * Una foto o un documento, con el texto como epígrafe.
     *
     * @throws WhatsappException
     */
    public function archivo(Conversacion $conversacion, UploadedFile $archivo, ?string $epigrafe, User $autor): MensajeWhatsapp
    {
        $this->exigirVentana($conversacion);

        $esImagen = in_array($archivo->getMimeType(), ['image/jpeg', 'image/png'], true);
        $nombre = $archivo->getClientOriginalName();
        $ruta = $archivo->storeAs(
            Conversacion::CARPETA.'/'.$conversacion->id,
            Str::ulid().'.'.strtolower($archivo->getClientOriginalExtension()),
            'local',
        );

        $mensaje = $this->borrador($conversacion, $esImagen ? TipoMensaje::Imagen : TipoMensaje::Documento, $epigrafe, $autor);
        $mensaje->update([
            'media_ruta' => $ruta,
            'media_mime' => $archivo->getMimeType(),
            'media_nombre' => $nombre,
        ]);

        return $this->despachar($mensaje, fn (): string => $this->whatsapp->enviarArchivo(
            $conversacion->wa_id,
            $esImagen ? 'image' : 'document',
            (string) Storage::disk('local')->get((string) $ruta),
            (string) $archivo->getMimeType(),
            $nombre,
            $epigrafe,
        ));
    }

    /**
     * Una plantilla aprobada: lo único que se puede mandar con la ventana
     * cerrada.
     *
     * @param  list<string>  $variables
     *
     * @throws WhatsappException
     */
    public function plantilla(Conversacion $conversacion, PlantillaWhatsapp $plantilla, array $variables, User $autor): MensajeWhatsapp
    {
        $mensaje = $this->borrador($conversacion, TipoMensaje::Plantilla, $plantilla->renderizar($variables), $autor);
        $mensaje->update(['plantilla' => $plantilla->nombre]);

        return $this->despachar($mensaje, fn (): string => $this->whatsapp->enviarPlantilla(
            $conversacion->wa_id,
            $plantilla->nombre,
            $plantilla->idioma,
            $variables,
        ));
    }

    /**
     * @throws WhatsappException
     */
    private function exigirVentana(Conversacion $conversacion): void
    {
        if (! $conversacion->ventanaAbierta()) {
            throw new WhatsappException('Ventana cerrada', WhatsappException::VENTANA_CERRADA);
        }
    }

    private function borrador(Conversacion $conversacion, TipoMensaje $tipo, ?string $cuerpo, ?User $autor, bool $generadoPorIa = false): MensajeWhatsapp
    {
        $mensaje = $conversacion->mensajes()->create([
            'direccion' => DireccionMensaje::Saliente,
            'tipo' => $tipo,
            'cuerpo' => $cuerpo,
            'estado' => EstadoMensaje::Pendiente,
            'enviado_por' => $autor?->id,
            'generado_por_ia' => $generadoPorIa,
        ]);

        $conversacion->forceFill(['ultimo_mensaje_at' => now()])->saveQuietly();

        return $mensaje;
    }

    /**
     * @param  callable(): string  $envio  devuelve el `wamid`
     *
     * @throws WhatsappException
     */
    private function despachar(MensajeWhatsapp $mensaje, callable $envio): MensajeWhatsapp
    {
        try {
            $wamid = $envio();
        } catch (WhatsappException $error) {
            $mensaje->update(['estado' => EstadoMensaje::Fallido, 'error' => $error->paraElPanel()]);

            throw $error;
        }

        $mensaje->update(['wa_message_id' => $wamid, 'estado' => EstadoMensaje::Enviado]);

        return $mensaje;
    }
}
