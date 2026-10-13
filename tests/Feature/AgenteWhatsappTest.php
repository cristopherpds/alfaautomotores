<?php

use App\Ai\Agents\AsistenteWhatsapp;
use App\Enums\DireccionMensaje;
use App\Enums\MotivoDerivacion;
use App\Enums\TipoMensaje;
use App\Jobs\ResponderConversacion;
use App\Models\Ajuste;
use App\Models\Conversacion;
use App\Models\MensajeWhatsapp;
use App\Services\Mensajero;
use App\Services\WhatsappCloud;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Prompts\AgentPrompt;
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
        'modelo_transcripcion' => 'google/gemini-2.5-flash',
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
