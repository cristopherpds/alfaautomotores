<?php

use App\Enums\EstadoMensaje;
use App\Enums\EstadoPlantilla;
use App\Enums\TipoMensaje;
use App\Models\Auditoria;
use App\Models\Cliente;
use App\Models\Conversacion;
use App\Models\MensajeWhatsapp;
use App\Models\PlantillaWhatsapp;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/*
 * El webhook de la WhatsApp Cloud API: la puerta (token + firma) y la ingesta.
 */

beforeEach(function () {
    config()->set('services.whatsapp', [
        'token' => 'token-de-prueba',
        'phone_number_id' => '111222333',
        'waba_id' => '999888777',
        'app_secret' => 'secreto-de-la-app',
        'verify_token' => 'token-del-webhook',
        'graph_version' => 'v23.0',
    ]);

    Storage::fake('local');
});

/**
 * Un POST firmado como lo firma Meta.
 *
 * @param  array<string, mixed>  $payload
 */
function postDeMeta(array $payload, ?string $firma = null, string $token = 'token-del-webhook')
{
    $cuerpo = json_encode($payload);

    return test()->call('POST', '/webhooks/whatsapp/'.$token, [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => $firma ?? 'sha256='.hash_hmac('sha256', $cuerpo, 'secreto-de-la-app'),
    ], $cuerpo);
}

/**
 * El sobre de un evento `messages`.
 *
 * @param  array<string, mixed>  $valor
 * @return array<string, mixed>
 */
function eventoDeMensajes(array $valor): array
{
    return [
        'object' => 'whatsapp_business_account',
        'entry' => [[
            'id' => '999888777',
            'changes' => [[
                'field' => 'messages',
                'value' => [
                    'messaging_product' => 'whatsapp',
                    'metadata' => ['display_phone_number' => '59899000000', 'phone_number_id' => '111222333'],
                    ...$valor,
                ],
            ]],
        ]],
    ];
}

/**
 * Un mensaje de texto entrante.
 *
 * @return array<string, mixed>
 */
function textoEntrante(string $texto = 'Hola, ¿tienen la Hilux?', string $id = 'wamid.uno', string $de = '59899123456'): array
{
    return eventoDeMensajes([
        'contacts' => [['wa_id' => $de, 'profile' => ['name' => 'Ana']]],
        'messages' => [[
            'from' => $de,
            'id' => $id,
            'timestamp' => (string) now()->timestamp,
            'type' => 'text',
            'text' => ['body' => $texto],
        ]],
    ]);
}

test('meta verifies the webhook with the right token', function () {
    $this->get('/webhooks/whatsapp/token-del-webhook?hub.mode=subscribe&hub.verify_token=token-del-webhook&hub.challenge=12345')
        ->assertOk()
        ->assertSeeText('12345');

    $this->get('/webhooks/whatsapp/token-del-webhook?hub.mode=subscribe&hub.verify_token=otro&hub.challenge=12345')
        ->assertForbidden();
});

test('a wrong token in the url is a 404 for both verbs', function () {
    $this->get('/webhooks/whatsapp/otro?hub.mode=subscribe&hub.verify_token=token-del-webhook&hub.challenge=1')
        ->assertNotFound();

    postDeMeta(textoEntrante(), token: 'otro')->assertNotFound();

    expect(MensajeWhatsapp::count())->toBe(0);
});

test('a post with a bad signature is rejected', function () {
    postDeMeta(textoEntrante(), 'sha256=firma-falsa')->assertUnauthorized();

    expect(MensajeWhatsapp::count())->toBe(0);
});

test('an incoming text opens a conversation and counts as unread', function () {
    postDeMeta(textoEntrante())->assertOk()->assertJson(['received' => true]);

    $conversacion = Conversacion::sole();
    $mensaje = MensajeWhatsapp::sole();

    expect($conversacion->wa_id)->toBe('59899123456')
        ->and($conversacion->nombre_perfil)->toBe('Ana')
        ->and($conversacion->no_leidos)->toBe(1)
        ->and($conversacion->ventanaAbierta())->toBeTrue()
        ->and($mensaje->tipo)->toBe(TipoMensaje::Texto)
        ->and($mensaje->cuerpo)->toBe('Hola, ¿tienen la Hilux?')
        ->and($mensaje->estado)->toBe(EstadoMensaje::Recibido);
});

test('the same message delivered twice is stored once', function () {
    postDeMeta(textoEntrante());
    postDeMeta(textoEntrante());

    expect(MensajeWhatsapp::count())->toBe(1)
        ->and(Conversacion::sole()->no_leidos)->toBe(1);
});

test('a message for another number of the account is ignored', function () {
    $evento = textoEntrante();
    $evento['entry'][0]['changes'][0]['value']['metadata']['phone_number_id'] = 'otro-numero';

    postDeMeta($evento)->assertOk();

    expect(Conversacion::count())->toBe(0);
});

test('the conversation links to the client with the same uruguayan phone', function () {
    $cliente = Cliente::factory()->create(['celular' => '099 123 456']);

    postDeMeta(textoEntrante(de: '59899123456'));

    expect(Conversacion::sole()->cliente_id)->toBe($cliente->id);
});

test('the phone translation covers local and foreign numbers', function () {
    expect(Cliente::celularesPosiblesDeWaId('59899123456'))->toBe(['59899123456', '099123456'])
        ->and(Cliente::celularesPosiblesDeWaId('5555999887766'))->toBe(['5555999887766']);
});

test('an incoming photo is downloaded to the private disk', function () {
    Http::fake([
        'graph.facebook.com/v23.0/media-123' => Http::response(['url' => 'https://lookaside.fbsbx.com/foto', 'mime_type' => 'image/jpeg']),
        'lookaside.fbsbx.com/*' => Http::response('jpg-binario', 200, ['Content-Type' => 'image/jpeg']),
    ]);

    postDeMeta(eventoDeMensajes([
        'contacts' => [['wa_id' => '59899123456', 'profile' => ['name' => 'Ana']]],
        'messages' => [[
            'from' => '59899123456',
            'id' => 'wamid.foto',
            'timestamp' => (string) now()->timestamp,
            'type' => 'image',
            'image' => ['id' => 'media-123', 'mime_type' => 'image/jpeg', 'caption' => 'Así está el auto'],
        ]],
    ]));

    $mensaje = MensajeWhatsapp::sole();

    expect($mensaje->tipo)->toBe(TipoMensaje::Imagen)
        ->and($mensaje->cuerpo)->toBe('Así está el auto')
        ->and($mensaje->media_ruta)->toStartWith('whatsapp/'.$mensaje->conversacion_id.'/')
        ->and($mensaje->media_ruta)->toEndWith('.jpg');

    expect(Storage::disk('local')->get($mensaje->media_ruta))->toBe('jpg-binario');

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer token-de-prueba'));
});

test('delivery states only move forward', function () {
    $mensaje = MensajeWhatsapp::factory()->saliente()->create(['wa_message_id' => 'wamid.saliente']);

    $estado = fn (string $estado) => postDeMeta(eventoDeMensajes([
        'statuses' => [['id' => 'wamid.saliente', 'status' => $estado, 'timestamp' => (string) now()->timestamp, 'recipient_id' => '59899123456']],
    ]));

    $estado('read');
    $estado('delivered');

    expect($mensaje->fresh()->estado)->toBe(EstadoMensaje::Leido);
});

test('a failed delivery keeps the reason', function () {
    $mensaje = MensajeWhatsapp::factory()->saliente()->create(['wa_message_id' => 'wamid.saliente']);

    postDeMeta(eventoDeMensajes([
        'statuses' => [[
            'id' => 'wamid.saliente',
            'status' => 'failed',
            'timestamp' => (string) now()->timestamp,
            'errors' => [['code' => 131026, 'title' => 'Message undeliverable', 'error_data' => ['details' => 'El número no tiene WhatsApp']]],
        ]],
    ]));

    expect($mensaje->fresh())
        ->estado->toBe(EstadoMensaje::Fallido)
        ->error->toBe('El número no tiene WhatsApp');
});

test('a reaction does not count as unread', function () {
    postDeMeta(eventoDeMensajes([
        'messages' => [[
            'from' => '59899123456',
            'id' => 'wamid.reaccion',
            'timestamp' => (string) now()->timestamp,
            'type' => 'reaction',
            'reaction' => ['message_id' => 'wamid.viejo', 'emoji' => '👍'],
        ]],
    ]));

    expect(Conversacion::sole()->no_leidos)->toBe(0)
        ->and(MensajeWhatsapp::sole()->cuerpo)->toBe('👍');
});

test('a template status update reaches the local copy', function () {
    $plantilla = PlantillaWhatsapp::factory()->create(['wa_template_id' => '555', 'estado' => EstadoPlantilla::Pendiente]);

    postDeMeta([
        'object' => 'whatsapp_business_account',
        'entry' => [['id' => '999888777', 'changes' => [[
            'field' => 'message_template_status_update',
            'value' => ['event' => 'REJECTED', 'message_template_id' => 555, 'reason' => 'INVALID_FORMAT'],
        ]]]],
    ]);

    expect($plantilla->fresh())
        ->estado->toBe(EstadoPlantilla::Rechazada)
        ->motivo_rechazo->toBe('INVALID_FORMAT');
});

test('the time from meta is stored in the app timezone', function () {
    $this->travelTo(now()->setTimezone(config('app.timezone'))->setTime(10, 0));

    postDeMeta(textoEntrante());

    expect(MensajeWhatsapp::sole()->wa_timestamp->format('H:i'))->toBe(now()->format('H:i'))
        ->and(Conversacion::sole()->ultimo_entrante_at->format('H:i'))->toBe(now()->format('H:i'));
});

test('what comes in through whatsapp is audited as the whatsapp client', function () {
    postDeMeta(textoEntrante());

    expect(Auditoria::where('tipo', 'conversacion')->sole()->usuario)->toBe(Auditoria::CLIENTE_WHATSAPP);
});
