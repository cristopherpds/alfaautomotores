<?php

use App\Enums\EstadoMensaje;
use App\Enums\MotivoDerivacion;
use App\Enums\TipoMensaje;
use App\Enums\UserRole;
use App\Models\Cliente;
use App\Models\Conversacion;
use App\Models\MensajeWhatsapp;
use App\Models\PlantillaWhatsapp;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

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

test('guests are sent to the login', function () {
    $this->get(route('panel.whatsapp.index'))->assertRedirect(route('login'));
});

test('the whole team sees the inbox, newest first', function () {
    Conversacion::factory()->create(['nombre_perfil' => 'Vieja', 'ultimo_mensaje_at' => now()->subDay()]);
    Conversacion::factory()->create(['nombre_perfil' => 'Nueva', 'ultimo_mensaje_at' => now()]);

    $this->actingAs(User::factory()->role(UserRole::Equipo)->create())
        ->get(route('panel.whatsapp.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('panel/whatsapp/index')
            ->has('conversaciones', 2)
            ->where('conversaciones.0.nombre', 'Nueva')
            ->where('conversacion', null)
        );
});

test('opening a conversation marks it read, also for the client', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['success' => true])]);

    $conversacion = Conversacion::factory()->create(['no_leidos' => 2]);
    MensajeWhatsapp::factory()->for($conversacion)->create(['wa_message_id' => 'wamid.ultimo']);

    $this->actingAs(User::factory()->create())
        ->get(route('panel.whatsapp.show', $conversacion))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('conversacion.id', $conversacion->id)
            ->has('conversacion.mensajes', 1)
            ->where('conversacion.ventanaAbierta', true)
            ->where('conversacion.nuncaEscribio', false)
        );

    expect($conversacion->fresh()->no_leidos)->toBe(0);

    Http::assertSent(fn ($request) => ($request['status'] ?? null) === 'read' && ($request['message_id'] ?? null) === 'wamid.ultimo');
});

test('a reply goes out through meta and is stored as sent', function () {
    Http::fake(['graph.facebook.com/v23.0/111222333/messages' => Http::response(['messages' => [['id' => 'wamid.respuesta']]])]);

    $conversacion = Conversacion::factory()->create();
    $usuario = User::factory()->create();

    $this->actingAs($usuario)
        ->post(route('panel.whatsapp.mensajes.store', $conversacion), ['cuerpo' => 'Sí, la tenemos. ¿Querés verla?'])
        ->assertSessionHasNoErrors();

    $mensaje = MensajeWhatsapp::sole();

    expect($mensaje)
        ->esEntrante()->toBeFalse()
        ->estado->toBe(EstadoMensaje::Enviado)
        ->wa_message_id->toBe('wamid.respuesta')
        ->enviado_por->toBe($usuario->id)
        ->generado_por_ia->toBeFalse();

    Http::assertSent(fn ($request) => $request['to'] === $conversacion->wa_id
        && $request['type'] === 'text'
        && $request['text']['body'] === 'Sí, la tenemos. ¿Querés verla?');
});

test('free text is blocked once the 24 hour window closed', function () {
    Http::fake();

    $conversacion = Conversacion::factory()->ventanaCerrada()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('panel.whatsapp.mensajes.store', $conversacion), ['cuerpo' => 'Hola'])
        ->assertSessionHasErrors('cuerpo');

    Http::assertNothingSent();
    expect(MensajeWhatsapp::count())->toBe(0);
});

test('a meta error leaves the message as failed with the reason', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => [
        'message' => '(#131026) Message undeliverable',
        'code' => 131026,
    ]], 400)]);

    $conversacion = Conversacion::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('panel.whatsapp.mensajes.store', $conversacion), ['cuerpo' => 'Hola'])
        ->assertSessionHasErrors(['cuerpo' => 'Ese número no puede recibir mensajes de WhatsApp.']);

    expect(MensajeWhatsapp::sole())
        ->estado->toBe(EstadoMensaje::Fallido)
        ->error->toBe('Ese número no puede recibir mensajes de WhatsApp.');
});

test('a photo is uploaded to meta and sent with its caption', function () {
    Http::fake([
        'graph.facebook.com/v23.0/111222333/media' => Http::response(['id' => 'media-subida']),
        'graph.facebook.com/v23.0/111222333/messages' => Http::response(['messages' => [['id' => 'wamid.foto']]]),
    ]);

    $conversacion = Conversacion::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('panel.whatsapp.mensajes.store', $conversacion), [
            'cuerpo' => 'Esta es la Hilux',
            'archivo' => UploadedFile::fake()->create('hilux.jpg', 100, 'image/jpeg'),
        ])
        ->assertSessionHasNoErrors();

    $mensaje = MensajeWhatsapp::sole();

    expect($mensaje->tipo)->toBe(TipoMensaje::Imagen);
    Storage::disk('local')->assertExists($mensaje->media_ruta);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/messages')
        && $request['image']['id'] === 'media-subida'
        && $request['image']['caption'] === 'Esta es la Hilux');
});

test('an approved template can be sent with the window closed', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.plantilla']]])]);

    $conversacion = Conversacion::factory()->ventanaCerrada()->create();
    $plantilla = PlantillaWhatsapp::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('panel.whatsapp.plantillas.enviar', $conversacion), [
            'plantilla' => $plantilla->id,
            'variables' => ['Ana'],
        ])
        ->assertSessionHasNoErrors();

    expect(MensajeWhatsapp::sole())
        ->tipo->toBe(TipoMensaje::Plantilla)
        ->cuerpo->toBe('Hola Ana, te escribimos de Alfa Automotores por tu consulta.');

    Http::assertSent(fn ($request) => $request['template']['name'] === $plantilla->nombre
        && $request['template']['components'][0]['parameters'][0]['text'] === 'Ana');
});

test('a template needs all of its variables', function () {
    Http::fake();

    $conversacion = Conversacion::factory()->ventanaCerrada()->create();
    $plantilla = PlantillaWhatsapp::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('panel.whatsapp.plantillas.enviar', $conversacion), ['plantilla' => $plantilla->id, 'variables' => []])
        ->assertSessionHasErrors('variables');

    Http::assertNothingSent();
});

test('taking a conversation silences the ai until it is given back', function () {
    $conversacion = Conversacion::factory()->create();
    $usuario = User::factory()->create();

    $this->actingAs($usuario)->patch(route('panel.whatsapp.ia', $conversacion), ['activa' => false]);

    expect($conversacion->fresh())
        ->atiendeLaIa()->toBeFalse()
        ->motivo_derivacion->toBe(MotivoDerivacion::Manual);

    $this->actingAs($usuario)->patch(route('panel.whatsapp.ia', $conversacion), ['activa' => true]);

    expect($conversacion->fresh())
        ->atiendeLaIa()->toBeTrue()
        ->derivada_at->toBeNull();
});

test('templates are synced from meta by whoever manages the account', function () {
    Http::fake(['graph.facebook.com/v23.0/999888777/message_templates*' => Http::response(['data' => [[
        'id' => '777',
        'name' => 'retomar_consulta',
        'language' => 'es',
        'status' => 'APPROVED',
        'category' => 'UTILITY',
        'components' => [['type' => 'BODY', 'text' => 'Hola {{1}}, ¿seguís interesado en el {{2}}?']],
    ]]])]);

    $this->actingAs(User::factory()->role(UserRole::Equipo)->create())
        ->post(route('panel.whatsapp.plantillas.sincronizar'))
        ->assertForbidden();

    $this->actingAs(User::factory()->role(UserRole::Vendedor)->create())
        ->post(route('panel.whatsapp.plantillas.sincronizar'))
        ->assertSessionHasNoErrors();

    expect(PlantillaWhatsapp::sole())
        ->nombre->toBe('retomar_consulta')
        ->variables->toBe(2);
});

test('attachments are served only inside their own conversation', function () {
    $conversacion = Conversacion::factory()->create();
    $otra = Conversacion::factory()->create();
    $ruta = 'whatsapp/'.$conversacion->id.'/foto.jpg';
    Storage::disk('local')->put($ruta, 'jpg');
    $mensaje = MensajeWhatsapp::factory()->for($conversacion)->create([
        'tipo' => TipoMensaje::Imagen, 'media_ruta' => $ruta, 'media_mime' => 'image/jpeg',
    ]);
    $usuario = User::factory()->create();

    $this->actingAs($usuario)->get(route('panel.whatsapp.media', [$conversacion, $mensaje]))->assertOk();
    $this->actingAs($usuario)->get(route('panel.whatsapp.media', [$otra, $mensaje]))->assertNotFound();
});

test('only managers delete a conversation, and its files go with it', function () {
    $conversacion = Conversacion::factory()->create();
    $ruta = 'whatsapp/'.$conversacion->id.'/foto.jpg';
    Storage::disk('local')->put($ruta, 'jpg');

    $this->actingAs(User::factory()->role(UserRole::Equipo)->create())
        ->delete(route('panel.whatsapp.destroy', $conversacion))
        ->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('panel.whatsapp.destroy', $conversacion))
        ->assertRedirect(route('panel.whatsapp.index'));

    expect(Conversacion::count())->toBe(0);
    Storage::disk('local')->assertMissing($ruta);
});

test('the polling reload only sends the inbox and the open conversation', function () {
    $conversacion = Conversacion::factory()->create();
    MensajeWhatsapp::factory()->for($conversacion)->create(['cuerpo' => 'hola']);

    $this->actingAs(User::factory()->create())
        ->get(route('panel.whatsapp.show', $conversacion))
        ->assertInertia(fn ($page) => $page
            ->has('filtros')
            ->reloadOnly(['conversaciones', 'conversacion'], fn ($recarga) => $recarga
                ->has('conversaciones', 1)
                ->where('conversacion.id', $conversacion->id)
                ->missing('filtros')
                ->missing('whatsappConfigurado')
            )
        );
});

test('writing to a client opens the chat already linked to their file', function () {
    $cliente = Cliente::factory()->create(['celular' => '099 123 456']);
    $conversacion = Conversacion::factory()->create(['wa_id' => '59899999999']);
    $conversacion->cliente()->associate($cliente)->saveQuietly();

    $this->actingAs(User::factory()->role(UserRole::Equipo)->create())
        ->post(route('panel.whatsapp.abrir', $cliente))
        ->assertRedirect(route('panel.whatsapp.show', $conversacion));

    expect(Conversacion::count())->toBe(1);
});

test('writing to a client finds the chat of their number and links it', function () {
    $cliente = Cliente::factory()->create(['celular' => '099 123 456']);
    $conversacion = Conversacion::factory()->create(['wa_id' => '59899123456']);

    $this->actingAs(User::factory()->create())
        ->post(route('panel.whatsapp.abrir', $cliente))
        ->assertRedirect(route('panel.whatsapp.show', $conversacion));

    expect($conversacion->fresh()->cliente_id)->toBe($cliente->id);
});

test('writing to a client who never wrote starts an empty chat', function () {
    $cliente = Cliente::factory()->create(['celular' => '099 123 456']);

    $this->actingAs(User::factory()->create())
        ->post(route('panel.whatsapp.abrir', $cliente));

    $conversacion = Conversacion::sole();

    expect($conversacion->wa_id)->toBe('59899123456')
        ->and($conversacion->cliente_id)->toBe($cliente->id)
        ->and($conversacion->ventanaAbierta())->toBeFalse();

    $this->actingAs(User::factory()->create())
        ->get(route('panel.whatsapp.show', $conversacion))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('conversacion.id', $conversacion->id)
            ->where('conversacion.nuncaEscribio', true));
});

test('guests cannot open a client chat', function () {
    $cliente = Cliente::factory()->create();

    $this->post(route('panel.whatsapp.abrir', $cliente))->assertRedirect(route('login'));

    expect(Conversacion::count())->toBe(0);
});

test('a file phone turns into a WhatsApp number', function (string $celular, string $waId) {
    expect(Cliente::waIdDelCelular($celular))->toBe($waId);
})->with([
    'local con cero' => ['099 123 456', '59899123456'],
    'local sin cero' => ['99123456', '59899123456'],
    'ya con 598' => ['+598 99 123 456', '59899123456'],
    'de Brasil' => ['5553999112233', '5553999112233'],
]);
