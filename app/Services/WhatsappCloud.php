<?php

namespace App\Services;

use App\Exceptions\WhatsappException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Cliente de la WhatsApp Cloud API de Meta (Graph).
 *
 * Todo lo que sale hacia Meta pasa por acá: el panel y el agente de IA mandan
 * por el mismo camino. Los envíos devuelven el `wamid` del mensaje, que es con
 * lo que después llegan sus estados por el webhook.
 */
class WhatsappCloud
{
    private const GRAPH = 'https://graph.facebook.com';

    /**
     * Si están las credenciales para hablar con Meta.
     */
    public function configurado(): bool
    {
        return filled(config('services.whatsapp.token')) && filled(config('services.whatsapp.phone_number_id'));
    }

    /**
     * Mandar un texto. Devuelve el `wamid`.
     *
     * @throws WhatsappException
     */
    public function enviarTexto(string $para, string $texto): string
    {
        return $this->enviar($para, 'text', ['body' => $texto, 'preview_url' => true]);
    }

    /**
     * Mandar una plantilla aprobada con sus variables del cuerpo.
     *
     * @param  list<string>  $variables
     *
     * @throws WhatsappException
     */
    public function enviarPlantilla(string $para, string $nombre, string $idioma, array $variables = []): string
    {
        $plantilla = ['name' => $nombre, 'language' => ['code' => $idioma]];

        if ($variables !== []) {
            $plantilla['components'] = [[
                'type' => 'body',
                'parameters' => array_map(fn (string $valor): array => ['type' => 'text', 'text' => $valor], $variables),
            ]];
        }

        return $this->enviar($para, 'template', $plantilla);
    }

    /**
     * Subir un archivo y mandarlo como imagen o documento.
     *
     * @param  'image'|'document'  $tipo
     *
     * @throws WhatsappException
     */
    public function enviarArchivo(string $para, string $tipo, string $contenido, string $mime, string $nombre, ?string $epigrafe = null): string
    {
        $subida = $this->llamar(fn (PendingRequest $http) => $http
            ->attach('file', $contenido, $nombre, ['Content-Type' => $mime])
            ->post($this->url($this->phoneNumberId().'/media'), [
                'messaging_product' => 'whatsapp',
                'type' => $mime,
            ]));

        $objeto = ['id' => (string) $subida->json('id')];

        if ($epigrafe !== null && $epigrafe !== '') {
            $objeto['caption'] = $epigrafe;
        }

        if ($tipo === 'document') {
            $objeto['filename'] = $nombre;
        }

        return $this->enviar($para, $tipo, $objeto);
    }

    /**
     * Marcar leído un mensaje entrante y, si se pide, mostrar «escribiendo…».
     *
     * Falla en silencio: no vale cortar un turno porque no se marcó un tilde.
     */
    public function marcarLeido(string $waMessageId, bool $escribiendo = false): void
    {
        $cuerpo = [
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $waMessageId,
        ];

        if ($escribiendo) {
            $cuerpo['typing_indicator'] = ['type' => 'text'];
        }

        try {
            $this->llamar(fn (PendingRequest $http) => $http->post($this->url($this->phoneNumberId().'/messages'), $cuerpo));
        } catch (WhatsappException) {
            // Ver arriba.
        }
    }

    /**
     * Bajar un adjunto entrante: primero la URL firmada, después el archivo.
     *
     * @return array{contenido: string, mime: string}
     *
     * @throws WhatsappException
     */
    public function descargarMedia(string $mediaId): array
    {
        $datos = $this->llamar(fn (PendingRequest $http) => $http->get($this->url($mediaId)));
        $archivo = $this->llamar(fn (PendingRequest $http) => $http->get((string) $datos->json('url')));

        return [
            'contenido' => $archivo->body(),
            'mime' => (string) ($datos->json('mime_type') ?? $archivo->header('Content-Type')),
        ];
    }

    /**
     * Las plantillas de la cuenta, todas las páginas.
     *
     * @return list<array<string, mixed>>
     *
     * @throws WhatsappException
     */
    public function plantillas(): array
    {
        $waba = (string) config('services.whatsapp.waba_id');

        if ($waba === '') {
            throw WhatsappException::sinConfigurar();
        }

        $plantillas = [];
        $url = $this->url($waba.'/message_templates').'?'.http_build_query([
            'fields' => 'id,name,language,status,category,components,rejected_reason',
            'limit' => 100,
        ]);

        while ($url !== null) {
            $pagina = $this->llamar(fn (PendingRequest $http) => $http->get($url));
            array_push($plantillas, ...(array) $pagina->json('data', []));
            $url = $pagina->json('paging.next');
        }

        return $plantillas;
    }

    /**
     * @param  array<string, mixed>  $contenido
     *
     * @throws WhatsappException
     */
    private function enviar(string $para, string $tipo, array $contenido): string
    {
        $respuesta = $this->llamar(fn (PendingRequest $http) => $http->post($this->url($this->phoneNumberId().'/messages'), [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $para,
            'type' => $tipo,
            $tipo => $contenido,
        ]));

        return (string) $respuesta->json('messages.0.id');
    }

    /**
     * @param  callable(PendingRequest): Response  $pedido
     *
     * @throws WhatsappException
     */
    private function llamar(callable $pedido): Response
    {
        if (! $this->configurado()) {
            throw WhatsappException::sinConfigurar();
        }

        try {
            $respuesta = $pedido(Http::withToken((string) config('services.whatsapp.token'))
                ->acceptJson()
                ->timeout(20)
                ->retry(2, 500, fn (\Throwable $error): bool => $error instanceof ConnectionException, throw: false));
        } catch (ConnectionException) {
            throw new WhatsappException('No se pudo conectar con WhatsApp. Probá de nuevo en un momento.');
        }

        if ($respuesta->failed()) {
            throw WhatsappException::desdeRespuesta($respuesta);
        }

        return $respuesta;
    }

    private function phoneNumberId(): string
    {
        return (string) config('services.whatsapp.phone_number_id');
    }

    private function url(string $ruta): string
    {
        return self::GRAPH.'/'.config('services.whatsapp.graph_version').'/'.$ruta;
    }
}
