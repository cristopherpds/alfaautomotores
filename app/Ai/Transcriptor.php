<?php

namespace App\Ai;

use App\Models\Ajuste;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Files\Audio;
use Laravel\Ai\Transcription;
use RuntimeException;
use Throwable;

/**
 * Pasar una nota de voz a texto, con el respaldo de nea-agent.
 *
 * Primero va al endpoint de voz a texto de OpenRouter (`audio/transcriptions`,
 * con `openai/whisper-1` por defecto). Si falla, el audio va adentro de un
 * mensaje de chat al modelo del agente, siempre que oiga audio (Gemini, por
 * ejemplo).
 *
 * El respaldo es un pedido HTTP directo: los adjuntos de laravel/ai para el
 * chat de OpenRouter sólo mapean imágenes y documentos, no `input_audio`.
 */
class Transcriptor
{
    /**
     * Segundos que se espera al proveedor en cada intento.
     */
    private const TIMEOUT_SEGUNDOS = 45;

    /**
     * Lo que se le pide al modelo de chat en el respaldo.
     */
    public const PEDIDO_RESPALDO = 'Transcribí literalmente esta nota de voz de un cliente, en el idioma en que habla. Devolvé sólo el texto, sin comillas ni comentarios. Si no se entiende nada, devolvé una línea vacía.';

    /**
     * El texto de la nota de voz, o null si no se pudo.
     */
    public static function transcribir(string $ruta, ?string $mime): ?string
    {
        $conexion = Ajuste::conexionIa();
        $mime = self::mimeDeAudio($mime, $ruta);

        try {
            $texto = trim((string) Transcription::of(Audio::fromStorage($ruta, 'local')->withMimeType($mime))
                ->timeout(self::TIMEOUT_SEGUNDOS)
                ->generate('openrouter', $conexion['modelo_transcripcion']));

            if ($texto !== '') {
                return $texto;
            }

            throw new RuntimeException('La transcripción volvió vacía.');
        } catch (Throwable $error) {
            Log::warning('No se pudo transcribir una nota de voz', ['ruta' => $ruta, 'modelo' => $conexion['modelo_transcripcion'], 'error' => $error->getMessage()]);
        }

        if (! self::modeloAcepta($conexion['modelo'], 'audio')) {
            return null;
        }

        try {
            return self::porChat($ruta, $mime, $conexion['modelo'], (string) $conexion['api_key']);
        } catch (Throwable $error) {
            Log::warning('Tampoco se pudo transcribir con el modelo del agente', ['ruta' => $ruta, 'modelo' => $conexion['modelo'], 'error' => $error->getMessage()]);

            return null;
        }
    }

    /**
     * El MIME de un audio como lo entiende OpenRouter.
     *
     * WhatsApp manda `audio/ogg; codecs=opus`, pero el disco detecta el
     * `.ogg` como `application/ogg`, que `audioFormat()` rechaza. Sin un MIME
     * útil se deduce por la extensión.
     */
    public static function mimeDeAudio(?string $mime, string $ruta = ''): string
    {
        $base = strtolower(trim(explode(';', (string) $mime)[0]));

        $normalizado = match ($base) {
            'application/ogg', 'audio/opus', 'video/ogg', 'audio/x-ogg' => 'audio/ogg',
            'audio/x-wav', 'audio/wave' => 'audio/wav',
            'audio/mp3', 'audio/x-mp3', 'audio/mpeg3' => 'audio/mpeg',
            'audio/x-m4a', 'audio/m4a' => 'audio/mp4',
            '', 'application/octet-stream' => null,
            default => $base,
        };

        if ($normalizado !== null) {
            return $normalizado;
        }

        return match (strtolower(pathinfo($ruta, PATHINFO_EXTENSION))) {
            'mp3' => 'audio/mpeg',
            'm4a', 'mp4' => 'audio/mp4',
            'wav' => 'audio/wav',
            'aac' => 'audio/aac',
            'flac' => 'audio/flac',
            'webm' => 'audio/webm',
            default => 'audio/ogg',
        };
    }

    /**
     * Si el modelo acepta un tipo de entrada (`image`, `audio`), según la
     * lista de OpenRouter que trae «Traer modelos» (guardada una hora). Sin
     * la lista no se sabe y se asume que sí: si no, el pedido falla y listo.
     */
    public static function modeloAcepta(string $modelo, string $entrada): bool
    {
        $lista = Cache::get('openrouter:modelos');

        if (! is_array($lista)) {
            return true;
        }

        $datos = collect($lista)->firstWhere('id', $modelo);

        return ! is_array($datos)
            || in_array($entrada, (array) ($datos['architecture']['input_modalities'] ?? []), true);
    }

    /**
     * El respaldo: el audio adentro de un mensaje de chat.
     *
     * @throws Throwable
     */
    private static function porChat(string $ruta, string $mime, string $modelo, string $clave): ?string
    {
        $formato = match ($mime) {
            'audio/mpeg' => 'mp3',
            'audio/mp4' => 'm4a',
            default => substr($mime, strlen('audio/')),
        };

        $url = rtrim((string) (config('ai.providers.openrouter.url') ?: 'https://openrouter.ai/api/v1'), '/');

        $respuesta = Http::withToken($clave)
            ->acceptJson()
            ->timeout(self::TIMEOUT_SEGUNDOS)
            ->post($url.'/chat/completions', [
                'model' => $modelo,
                'messages' => [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => self::PEDIDO_RESPALDO],
                        ['type' => 'input_audio', 'input_audio' => [
                            'data' => base64_encode((string) Storage::disk('local')->get($ruta)),
                            'format' => $formato,
                        ]],
                    ],
                ]],
            ])
            ->throw();

        $texto = trim((string) $respuesta->json('choices.0.message.content'));

        return $texto === '' ? null : $texto;
    }
}
