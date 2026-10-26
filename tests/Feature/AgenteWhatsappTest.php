<?php

use App\Ai\Agents\AsistenteWhatsapp;
use App\Ai\Transcriptor;
use App\Enums\DireccionMensaje;
use App\Enums\MotivoDerivacion;
use App\Enums\TipoMensaje;
use App\Jobs\ResponderConversacion;
use App\Models\Ajuste;
use App\Models\Conversacion;
use App\Models\MensajeWhatsapp;
use App\Services\Mensajero;
use App\Services\WhatsappCloud;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Prompts\TranscriptionPrompt;
use Laravel\Ai\Transcription;

/*
 * Un turno del agente de WhatsApp. El modelo va falseado: se prueba lo que
 * hace el código alrededor (cuándo contesta, qué le pasa, qué manda).
 */

beforeEach(function () {
    config()->set('services.whatsapp', [
        'token' => 'token-de-prueba',
        'phone_number_id' => '111222333',
        'waba_id' => '999888777',
        'app_secret' => '',
        'verify_token' => 'token-del-webhook',
        'graph_version' => 'v23.0',
    ]);

    Ajuste::guardar(Ajuste::AGENTE_IA, ['activo' => true, 'nombre' => 'Alfi']);
    Ajuste::guardarConexion(Ajuste::CONEXION_IA, [
        'api_key' => 'sk-or-prueba',
        'modelo' => 'google/gemini-2.5-flash',
        'modelo_transcripcion' => 'openai/whisper-1',
    ]);

    Storage::fake('local');
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.respuesta']]])]);
});

/**
 * Un mensaje del cliente, tal como lo deja el webhook.
 */
function entranteDelCliente(Conversacion $conversacion, string $texto, array $extra = []): MensajeWhatsapp
{
    return MensajeWhatsapp::factory()->for($conversacion)->create(['cuerpo' => $texto, ...$extra]);
}

/**
 * Lo que la IA mandó por WhatsApp.
 */
function respuestasDeLaIa(): array
{
    return MensajeWhatsapp::where('generado_por_ia', true)->pluck('cuerpo')->all();
}

test('an incoming message through the webhook gets an ai reply', function () {
    AsistenteWhatsapp::fake(['¡Hola! Soy Alfi, el asistente virtual de Alfa. Sí, tenemos una Hilux 2018. ¿Querés el link?']);

    $this->postJson('/webhooks/whatsapp/token-del-webhook', [
        'object' => 'whatsapp_business_account',
        'entry' => [['id' => '999888777', 'changes' => [[
            'field' => 'messages',
            'value' => [
                'metadata' => ['phone_number_id' => '111222333'],
                'contacts' => [['wa_id' => '59899123456', 'profile' => ['name' => 'Ana']]],
                'messages' => [['from' => '59899123456', 'id' => 'wamid.uno', 'timestamp' => (string) now()->timestamp, 'type' => 'text', 'text' => ['body' => 'Hay alguna hailux?']]],
            ],
        ]]]],
    ])->assertOk();

    AsistenteWhatsapp::assertPrompted(fn (AgentPrompt $prompt) => $prompt->contains('Hay alguna hailux?'));

    expect(respuestasDeLaIa())->toBe(['¡Hola! Soy Alfi, el asistente virtual de Alfa. Sí, tenemos una Hilux 2018. ¿Querés el link?']);

    Http::assertSent(fn ($request) => ($request['text']['body'] ?? null) === '¡Hola! Soy Alfi, el asistente virtual de Alfa. Sí, tenemos una Hilux 2018. ¿Querés el link?');
});

test('a burst of messages gets one reply with all of them', function () {
    AsistenteWhatsapp::fake(['Dale, te paso las opciones.']);

    $conversacion = Conversacion::factory()->create();
    $primero = entranteDelCliente($conversacion, 'hola');
    $segundo = entranteDelCliente($conversacion, 'busco una camioneta');

    // El job del primer mensaje llega tarde: ya entró otro, no hace nada.
    (new ResponderConversacion($conversacion->id, $primero->id))->handle(app(Mensajero::class), app(WhatsappCloud::class));
    AsistenteWhatsapp::assertNeverPrompted();

    (new ResponderConversacion($conversacion->id, $segundo->id))->handle(app(Mensajero::class), app(WhatsappCloud::class));

    AsistenteWhatsapp::assertPromptedTimes(1);
    AsistenteWhatsapp::assertPrompted(fn (AgentPrompt $prompt) => $prompt->contains('hola') && $prompt->contains('busco una camioneta'));
    expect(respuestasDeLaIa())->toHaveCount(1);
});

test('nothing is answered twice', function () {
    AsistenteWhatsapp::fake(['Respuesta']);

    $conversacion = Conversacion::factory()->create();
    $mensaje = entranteDelCliente($conversacion, 'hola');

    ResponderConversacion::dispatchSync($conversacion->id, $mensaje->id);
    ResponderConversacion::dispatchSync($conversacion->id, $mensaje->id);

    AsistenteWhatsapp::assertPromptedTimes(1);
});

test('the agent stays quiet when it is off, handed over or the window closed', function (Closure $preparar) {
    AsistenteWhatsapp::fake(['No debería salir']);

    $conversacion = Conversacion::factory()->create();
    $preparar($conversacion);
    $mensaje = entranteDelCliente($conversacion->fresh(), 'hola');

    ResponderConversacion::dispatchSync($conversacion->id, $mensaje->id);

    AsistenteWhatsapp::assertNeverPrompted();
    expect(respuestasDeLaIa())->toBe([]);
})->with([
    'agent switched off' => [fn (Conversacion $c) => Ajuste::guardar(Ajuste::AGENTE_IA, ['activo' => false])],
    'handed to the team' => [fn (Conversacion $c) => $c->derivar(MotivoDerivacion::Manual)],
    'ai off in this chat' => [fn (Conversacion $c) => $c->forceFill(['ia_activa' => false])->save()],
    'no openrouter key' => [fn (Conversacion $c) => Ajuste::guardar(Ajuste::CONEXION_IA, [])],
    'window closed' => [fn (Conversacion $c) => $c->forceFill(['ultimo_entrante_at' => now()->subDays(2)])->save()],
]);

test('asking for a person hands over at once without calling the model', function () {
    AsistenteWhatsapp::fake(['No debería salir']);

    $conversacion = Conversacion::factory()->create();
    $mensaje = entranteDelCliente($conversacion, 'quiero hablar con una persona por favor');

    ResponderConversacion::dispatchSync($conversacion->id, $mensaje->id);

    AsistenteWhatsapp::assertNeverPrompted();
    expect($conversacion->fresh())
        ->motivo_derivacion->toBe(MotivoDerivacion::Cliente)
        ->atiendeLaIa()->toBeFalse();
    expect(respuestasDeLaIa()[0])->toContain('te paso con alguien del equipo');
});

test('the third hostile message in a row closes and hands over', function () {
    AsistenteWhatsapp::fake(['Entiendo. Dejo la conversación con el equipo.']);

    $conversacion = Conversacion::factory()->create();
    entranteDelCliente($conversacion, 'son unos chantas');
    entranteDelCliente($conversacion, 'estafadores');
    $ultimo = entranteDelCliente($conversacion, 'basura de local');

    ResponderConversacion::dispatchSync($conversacion->id, $ultimo->id);

    AsistenteWhatsapp::assertPromptedTimes(1);
    expect($conversacion->fresh()->motivo_derivacion)->toBe(MotivoDerivacion::Hostilidad);
});

test('a model failure sends nothing and hands over with error', function () {
    AsistenteWhatsapp::fake(fn () => throw new RuntimeException('OpenRouter caído'));

    $conversacion = Conversacion::factory()->create();
    $mensaje = entranteDelCliente($conversacion, 'hola');

    ResponderConversacion::dispatchSync($conversacion->id, $mensaje->id);

    expect(respuestasDeLaIa())->toBe([])
        ->and($conversacion->fresh()->motivo_derivacion)->toBe(MotivoDerivacion::Error);
});

test('markdown from the model is turned into whatsapp format', function () {
    AsistenteWhatsapp::fake(["## Opciones\n**Toyota Hilux** 2018: [ver ficha](https://alfa.test/vehiculos/hilux)"]);

    $conversacion = Conversacion::factory()->create();
    $mensaje = entranteDelCliente($conversacion, 'hilux?');

    ResponderConversacion::dispatchSync($conversacion->id, $mensaje->id);

    expect(respuestasDeLaIa()[0])->toBe("*Opciones*\n*Toyota Hilux* 2018: ver ficha: https://alfa.test/vehiculos/hilux");
});

test('a voice note is transcribed once and reaches the model as text', function () {
    AsistenteWhatsapp::fake(['Dale, ¿para qué día querés el turno?']);
    Transcription::fake(['quiero sacar turno para el service']);

    $conversacion = Conversacion::factory()->create();
    Storage::disk('local')->put('whatsapp/'.$conversacion->id.'/nota.ogg', 'ogg');
    $audio = entranteDelCliente($conversacion, '', [
        'tipo' => TipoMensaje::Audio,
        'cuerpo' => null,
        'media_ruta' => 'whatsapp/'.$conversacion->id.'/nota.ogg',
        'media_mime' => 'audio/ogg',
    ]);

    ResponderConversacion::dispatchSync($conversacion->id, $audio->id);

    expect($audio->fresh()->meta['transcripcion'])->toBe('quiero sacar turno para el service');
    AsistenteWhatsapp::assertPrompted(fn (AgentPrompt $prompt) => $prompt->contains('[Nota de voz, transcripta]: quiero sacar turno para el service'));
});

test('photos of the burst go to the model as attachments', function () {
    AsistenteWhatsapp::fake(['Qué lindo auto. ¿Qué año es?']);

    $conversacion = Conversacion::factory()->create();
    Storage::disk('local')->put('whatsapp/'.$conversacion->id.'/foto.jpg', 'jpg');
    $foto = entranteDelCliente($conversacion, 'así está', [
        'tipo' => TipoMensaje::Imagen,
        'media_ruta' => 'whatsapp/'.$conversacion->id.'/foto.jpg',
        'media_mime' => 'image/jpeg',
    ]);

    ResponderConversacion::dispatchSync($conversacion->id, $foto->id);

    AsistenteWhatsapp::assertPrompted(fn (AgentPrompt $prompt) => $prompt->attachments->count() === 1
        && $prompt->contains('[Foto adjunta] así está'));
});

test('the reply is stored as sent by the ai', function () {
    AsistenteWhatsapp::fake(['Hola, ¿en qué te ayudo?']);

    $conversacion = Conversacion::factory()->create();
    $mensaje = entranteDelCliente($conversacion, 'hola');

    ResponderConversacion::dispatchSync($conversacion->id, $mensaje->id);

    expect(MensajeWhatsapp::where('direccion', DireccionMensaje::Saliente)->sole())
        ->generado_por_ia->toBeTrue()
        ->wa_message_id->toBe('wamid.respuesta');
});

test('with test numbers loaded the agent only answers those', function () {
    AsistenteWhatsapp::fake(['Hola']);
    Ajuste::guardar(Ajuste::AGENTE_IA, ['activo' => true, 'nombre' => 'Alfi', 'numeros_prueba' => "099 123 456\n+55 55 99988 7766"]);

    $deprueba = Conversacion::factory()->create(['wa_id' => '59899123456']);
    $cliente = Conversacion::factory()->create(['wa_id' => '59899000111']);

    ResponderConversacion::dispatchSync($deprueba->id, entranteDelCliente($deprueba, 'hola')->id);
    ResponderConversacion::dispatchSync($cliente->id, entranteDelCliente($cliente, 'hola')->id);

    expect(Ajuste::numerosDePrueba())->toBe(['59899123456', '5555999887766']);
    AsistenteWhatsapp::assertPromptedTimes(1);
    expect(MensajeWhatsapp::where('conversacion_id', $cliente->id)->where('generado_por_ia', true)->count())->toBe(0);
});

test('a passing provider failure is retried', function () {
    Sleep::fake();
    $llamadas = 0;

    AsistenteWhatsapp::fake(function () use (&$llamadas) {
        if (++$llamadas === 1) {
            throw ProviderOverloadedException::forProvider('openrouter');
        }

        return 'Ya estoy, ¿en qué te ayudo?';
    });

    $conversacion = Conversacion::factory()->create();
    ResponderConversacion::dispatchSync($conversacion->id, entranteDelCliente($conversacion, 'hola')->id);

    expect($llamadas)->toBe(2)
        ->and(respuestasDeLaIa())->toBe(['Ya estoy, ¿en qué te ayudo?'])
        ->and($conversacion->fresh()->derivada_at)->toBeNull();
    Sleep::assertSleptTimes(1);
});

test('without credits there is no retry', function () {
    Sleep::fake();
    $llamadas = 0;

    AsistenteWhatsapp::fake(function () use (&$llamadas) {
        $llamadas++;

        throw InsufficientCreditsException::forProvider('openrouter');
    });

    $conversacion = Conversacion::factory()->create();
    ResponderConversacion::dispatchSync($conversacion->id, entranteDelCliente($conversacion, 'hola')->id);

    expect($llamadas)->toBe(1)
        ->and($conversacion->fresh()->motivo_derivacion)->toBe(MotivoDerivacion::Error);
});

test('a model that rejects the speed options is asked again without them', function () {
    $llamadas = 0;

    AsistenteWhatsapp::fake(function () use (&$llamadas) {
        if (++$llamadas === 1) {
            throw new RuntimeException('Unsupported parameter: reasoning');
        }

        return 'Hola';
    });

    $conversacion = Conversacion::factory()->create();
    ResponderConversacion::dispatchSync($conversacion->id, entranteDelCliente($conversacion, 'hola')->id);

    expect(respuestasDeLaIa())->toBe(['Hola']);
    AsistenteWhatsapp::assertPrompted(fn (AgentPrompt $prompt) => $prompt->agent->rapido === false);
});

test('the agent asks openrouter for the fast path', function () {
    $agente = new AsistenteWhatsapp(Conversacion::factory()->create(), 'sistema');

    expect($agente->providerOptions('openrouter'))->toBe([
        'reasoning' => ['effort' => 'minimal'],
        'provider' => ['sort' => 'throughput'],
    ]);
});

test('photos are not sent to a model that cannot see them', function () {
    AsistenteWhatsapp::fake(['Recibí la foto; contame marca, modelo y año.']);
    Ajuste::guardarConexion(Ajuste::CONEXION_IA, ['modelo' => 'z-ai/glm-5.3-flash']);
    Cache::put('openrouter:modelos', [
        ['id' => 'z-ai/glm-5.3-flash', 'architecture' => ['input_modalities' => ['text']]],
    ], now()->addHour());

    $conversacion = Conversacion::factory()->create();
    Storage::disk('local')->put('whatsapp/'.$conversacion->id.'/foto.jpg', 'jpg');
    $foto = entranteDelCliente($conversacion, '', [
        'tipo' => TipoMensaje::Imagen,
        'cuerpo' => null,
        'media_ruta' => 'whatsapp/'.$conversacion->id.'/foto.jpg',
    ]);

    ResponderConversacion::dispatchSync($conversacion->id, $foto->id);

    AsistenteWhatsapp::assertPrompted(fn (AgentPrompt $prompt) => $prompt->attachments->isEmpty()
        && $prompt->contains('vos no podés ver'));
    expect(respuestasDeLaIa())->toHaveCount(1);
});

/**
 * Una nota de voz ya bajada al disco.
 */
function notaDeVoz(Conversacion $conversacion, array $extra = []): MensajeWhatsapp
{
    Storage::disk('local')->put('whatsapp/'.$conversacion->id.'/nota.ogg', 'ogg');

    return entranteDelCliente($conversacion, '', [
        'tipo' => TipoMensaje::Audio,
        'cuerpo' => null,
        'media_id' => 'media-1',
        'media_ruta' => 'whatsapp/'.$conversacion->id.'/nota.ogg',
        'media_mime' => 'audio/ogg; codecs=opus',
        ...$extra,
    ]);
}

test('the voice note goes to whisper with a mime openrouter accepts', function () {
    AsistenteWhatsapp::fake(['Dale.']);
    Transcription::fake(['hola']);

    $conversacion = Conversacion::factory()->create();
    $audio = notaDeVoz($conversacion, ['media_mime' => 'application/ogg']);

    ResponderConversacion::dispatchSync($conversacion->id, $audio->id);

    Transcription::assertGenerated(fn (TranscriptionPrompt $prompt) => $prompt->model === 'openai/whisper-1'
        && $prompt->audio->mimeType() === 'audio/ogg');
    expect($audio->fresh()->meta)->toMatchArray(['transcripcion' => 'hola', 'transcripcion_intentos' => 1])
        ->not->toHaveKey('transcripcion_fallida');
});

test('audio mimes are normalized for openrouter', function (?string $mime, string $ruta, string $esperado) {
    expect(Transcriptor::mimeDeAudio($mime, $ruta))->toBe($esperado);
})->with([
    ['application/ogg', 'nota.ogg', 'audio/ogg'],
    ['audio/ogg; codecs=opus', 'nota.ogg', 'audio/ogg'],
    ['audio/x-m4a', 'nota.m4a', 'audio/mp4'],
    [null, 'nota.mp3', 'audio/mpeg'],
    ['application/octet-stream', 'nota.ogg', 'audio/ogg'],
]);

test('a failed transcription is not stored as text and is retried on the next turn', function () {
    AsistenteWhatsapp::fake(['Â¿Me lo escribÃ­s?', 'Dale, te paso turno.']);
    Transcription::fake(function () {
        throw new RuntimeException('Model google/gemini-2.5-flash does not exist');
    });
    // Sin respaldo: el modelo del agente no oye audio.
    Cache::put('openrouter:modelos', [['id' => 'google/gemini-2.5-flash', 'architecture' => ['input_modalities' => ['text', 'image']]]]);

    $conversacion = Conversacion::factory()->create();
    $audio = notaDeVoz($conversacion);

    ResponderConversacion::dispatchSync($conversacion->id, $audio->id);

    expect($audio->fresh()->meta)->toMatchArray(['transcripcion_fallida' => true, 'transcripcion_intentos' => 1])
        ->not->toHaveKey('transcripcion');
    AsistenteWhatsapp::assertPrompted(fn (AgentPrompt $prompt) => $prompt->contains('[Nota de voz que no se pudo escuchar]'));

    Transcription::fake(['quiero turno para el service']);
    $texto = entranteDelCliente($conversacion, 'Â¿me escuchaste?');

    ResponderConversacion::dispatchSync($conversacion->id, $texto->id);

    expect($audio->fresh()->meta)->toMatchArray(['transcripcion' => 'quiero turno para el service', 'transcripcion_intentos' => 2])
        ->not->toHaveKey('transcripcion_fallida');
});

test('a voice note stops being retried after the attempt limit', function () {
    AsistenteWhatsapp::fake(['Â¿Me lo escribÃ­s?']);
    Transcription::fake(['no deberÃ­a llamarse']);

    $conversacion = Conversacion::factory()->create();
    $audio = notaDeVoz($conversacion, ['meta' => ['transcripcion_fallida' => true, 'transcripcion_intentos' => ResponderConversacion::INTENTOS_TRANSCRIPCION]]);

    ResponderConversacion::dispatchSync($conversacion->id, $audio->id);

    Transcription::assertNothingGenerated();
});

test('when whisper fails the audio goes to the agent model if it hears audio', function () {
    AsistenteWhatsapp::fake(['Dale.']);
    Transcription::fake(function () {
        throw new RuntimeException('caÃ­do');
    });
    Http::fake([
        'graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.respuesta']]]),
        'openrouter.ai/api/v1/chat/completions' => Http::response(['choices' => [['message' => ['content' => ' quiero ver la hilux ']]]]),
    ]);

    $conversacion = Conversacion::factory()->create();
    $audio = notaDeVoz($conversacion, ['media_mime' => 'application/ogg']);

    ResponderConversacion::dispatchSync($conversacion->id, $audio->id);

    expect($audio->fresh()->meta['transcripcion'])->toBe('quiero ver la hilux');
    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/chat/completions')
        && $request['model'] === 'google/gemini-2.5-flash'
        && $request['messages'][0]['content'][1]['input_audio']['format'] === 'ogg'
        && $request->hasHeader('Authorization', 'Bearer sk-or-prueba'));
    AsistenteWhatsapp::assertPrompted(fn (AgentPrompt $prompt) => $prompt->contains('[Nota de voz, transcripta]: quiero ver la hilux'));
});

test('a voice note still downloading delays the turn instead of skipping it', function () {
    Queue::fake();
    AsistenteWhatsapp::fake(['no deberÃ­a contestar']);
    Transcription::fake(['hola']);

    $conversacion = Conversacion::factory()->create();
    $audio = notaDeVoz($conversacion, ['media_ruta' => null]);

    (new ResponderConversacion($conversacion->id, $audio->id))->handle(app(Mensajero::class), app(WhatsappCloud::class));

    Queue::assertPushed(ResponderConversacion::class, fn (ResponderConversacion $job) => $job->esperasDeMedia === 1
        && $job->hastaMensajeId === $audio->id
        && $job->delay !== null);
    AsistenteWhatsapp::assertNeverPrompted();
    Transcription::assertNothingGenerated();
});

test('after waiting enough for the download the turn goes on without the audio', function () {
    Queue::fake();
    AsistenteWhatsapp::fake(['Â¿Me lo escribÃ­s?']);

    $conversacion = Conversacion::factory()->create();
    $audio = notaDeVoz($conversacion, ['media_ruta' => null]);

    (new ResponderConversacion($conversacion->id, $audio->id, ResponderConversacion::ESPERAS_DE_MEDIA))
        ->handle(app(Mensajero::class), app(WhatsappCloud::class));

    Queue::assertNotPushed(ResponderConversacion::class);
    AsistenteWhatsapp::assertPrompted(fn (AgentPrompt $prompt) => $prompt->contains('[Nota de voz que no se pudo escuchar]'));
});
