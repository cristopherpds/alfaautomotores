<?php

use App\Enums\AccionAuditoria;
use App\Enums\EstadoMensaje;
use App\Enums\EstadoTurno;
use App\Enums\MotivoDerivacion;
use App\Enums\TipoMensaje;
use App\Enums\UserRole;
use App\Jobs\DescargarMediaWhatsapp;
use App\Jobs\ResponderConversacion;
use App\Models\Auditoria;
use App\Models\Cliente;
use App\Models\Conversacion;
use App\Models\Cotizacion;
use App\Models\MensajeWhatsapp;
use App\Models\PlantillaWhatsapp;
use App\Models\Turno;
use App\Models\User;
use App\Services\WhatsappCloud;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Support\Facades\DB;
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

test('a voice note left with an empty transcription by the old code shows as failed', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['success' => true])]);

    $conversacion = Conversacion::factory()->create();
    MensajeWhatsapp::factory()->for($conversacion)->create([
        'tipo' => TipoMensaje::Audio,
        'cuerpo' => null,
        'meta' => ['transcripcion' => ''],
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('panel.whatsapp.show', $conversacion))
        ->assertInertia(fn ($page) => $page
            ->where('conversacion.mensajes.0.transcripcion', null)
            ->where('conversacion.mensajes.0.transcripcionFallida', true)
        );
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
        ->delete(route('panel.whatsapp.destroy', $conversacion), ['alcance' => 'hilo'])
        ->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('panel.whatsapp.destroy', $conversacion), ['alcance' => 'hilo'])
        ->assertRedirect(route('panel.whatsapp.index'));

    expect(Conversacion::count())->toBe(0);
    Storage::disk('local')->assertMissing($ruta);
});

test('deleting needs to say whether the client goes too', function (?string $alcance) {
    $conversacion = Conversacion::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('panel.whatsapp.destroy', $conversacion), array_filter(['alcance' => $alcance]))
        ->assertSessionHasErrors('alcance');

    expect(Conversacion::count())->toBe(1);
})->with([null, 'todo']);

test('deleting only the thread forgets the chat but keeps the client', function () {
    $cliente = Cliente::factory()->create();
    $conversacion = Conversacion::factory()->create(['ficha' => ['vehiculo' => 'Hilux']]);
    $conversacion->cliente()->associate($cliente)->saveQuietly();
    $otra = Conversacion::factory()->create();
    $otra->cliente()->associate($cliente)->saveQuietly();
    MensajeWhatsapp::factory()->for($conversacion)->count(2)->create();
    $cotizacion = Cotizacion::factory()->porWhatsapp($conversacion)->create(['cliente_id' => $cliente->id]);
    Storage::disk('local')->put('whatsapp/'.$conversacion->id.'/audio.ogg', 'ogg');

    $this->actingAs(User::factory()->role(UserRole::Vendedor)->create())
        ->delete(route('panel.whatsapp.destroy', $conversacion), ['alcance' => 'hilo'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('panel.whatsapp.index'));

    expect(Conversacion::find($conversacion->id))->toBeNull()
        ->and(MensajeWhatsapp::where('conversacion_id', $conversacion->id)->count())->toBe(0)
        ->and($otra->fresh())->not->toBeNull()
        ->and($cliente->fresh())->not->toBeNull()
        ->and($cotizacion->fresh()->conversacion_id)->toBeNull();
    Storage::disk('local')->assertMissing('whatsapp/'.$conversacion->id.'/audio.ogg');
});

test('deleting the contact also deletes the client and all of their chats', function () {
    $cliente = Cliente::factory()->create();
    $conversacion = Conversacion::factory()->create();
    $conversacion->cliente()->associate($cliente)->saveQuietly();
    $otra = Conversacion::factory()->create();
    $otra->cliente()->associate($cliente)->saveQuietly();
    $ajena = Conversacion::factory()->create();
    $pasado = Turno::factory()->for($cliente, 'ficha')->estado(EstadoTurno::Completado)->inicia(now()->subWeek())->create();
    $cotizacion = Cotizacion::factory()->create(['cliente_id' => $cliente->id]);
    Storage::disk('local')->put('whatsapp/'.$conversacion->id.'/foto.jpg', 'jpg');
    Storage::disk('local')->put('whatsapp/'.$otra->id.'/doc.pdf', 'pdf');
    Storage::disk('local')->put('whatsapp/'.$ajena->id.'/foto.jpg', 'jpg');

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('panel.whatsapp.destroy', $conversacion), ['alcance' => 'contacto'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('panel.whatsapp.index'));

    expect(Cliente::find($cliente->id))->toBeNull()
        ->and(Conversacion::pluck('id')->all())->toBe([$ajena->id])
        ->and($pasado->fresh()->cliente_id)->toBeNull()
        ->and($cotizacion->fresh()->cliente_id)->toBeNull();
    Storage::disk('local')->assertMissing('whatsapp/'.$conversacion->id.'/foto.jpg');
    Storage::disk('local')->assertMissing('whatsapp/'.$otra->id.'/doc.pdf');
    Storage::disk('local')->assertExists('whatsapp/'.$ajena->id.'/foto.jpg');
});

test('a seller cannot delete the client along with the chat', function () {
    $cliente = Cliente::factory()->create();
    $conversacion = Conversacion::factory()->create();
    $conversacion->cliente()->associate($cliente)->saveQuietly();
    $vendedor = User::factory()->role(UserRole::Vendedor)->create();

    $this->actingAs($vendedor)
        ->get(route('panel.whatsapp.show', $conversacion))
        ->assertInertia(fn ($page) => $page
            ->where('conversacion.can.delete', true)
            ->where('conversacion.puedeBorrarCliente', false)
        );

    $this->actingAs($vendedor)
        ->delete(route('panel.whatsapp.destroy', $conversacion), ['alcance' => 'contacto'])
        ->assertForbidden();

    expect($cliente->fresh())->not->toBeNull()
        ->and($conversacion->fresh())->not->toBeNull();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('panel.whatsapp.show', $conversacion))
        ->assertInertia(fn ($page) => $page->where('conversacion.puedeBorrarCliente', true));
});

test('deleting the contact of a chat without a client just deletes the chat', function () {
    $conversacion = Conversacion::factory()->create();

    $this->actingAs(User::factory()->role(UserRole::Vendedor)->create())
        ->delete(route('panel.whatsapp.destroy', $conversacion), ['alcance' => 'contacto'])
        ->assertRedirect(route('panel.whatsapp.index'))
        ->assertInertiaFlash('toast.message', 'Conversación eliminada.');

    expect(Conversacion::count())->toBe(0);
});

test('deleting is blocked while the client has an upcoming appointment', function (EstadoTurno $estado, string $alcance) {
    $cliente = Cliente::factory()->create();
    $conversacion = Conversacion::factory()->create();
    $conversacion->cliente()->associate($cliente)->saveQuietly();
    Turno::factory()->for($cliente, 'ficha')->estado($estado)->inicia(now()->addDays(2)->setTime(10, 30))->create();
    Storage::disk('local')->put('whatsapp/'.$conversacion->id.'/foto.jpg', 'jpg');

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('panel.whatsapp.destroy', $conversacion), ['alcance' => $alcance])
        ->assertSessionHasErrors(['alcance' => 'Tiene un turno el '.now()->addDays(2)->format('d/m/Y').' a las 10:30. Cancelalo antes de eliminar.']);

    expect($conversacion->fresh())->not->toBeNull()
        ->and($cliente->fresh())->not->toBeNull();
    Storage::disk('local')->assertExists('whatsapp/'.$conversacion->id.'/foto.jpg');
})->with([EstadoTurno::Pendiente, EstadoTurno::Confirmado])->with(['hilo', 'contacto']);

test('a cancelled or past appointment does not block deleting', function () {
    $cliente = Cliente::factory()->create();
    $conversacion = Conversacion::factory()->create();
    $conversacion->cliente()->associate($cliente)->saveQuietly();
    Turno::factory()->for($cliente, 'ficha')->estado(EstadoTurno::Cancelado)->inicia(now()->addDays(2))->create();
    Turno::factory()->for($cliente, 'ficha')->estado(EstadoTurno::Confirmado)->inicia(now()->subDays(2))->create();

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('panel.whatsapp.destroy', $conversacion), ['alcance' => 'contacto'])
        ->assertSessionHasNoErrors();

    expect(Cliente::count())->toBe(0)->and(Conversacion::count())->toBe(0);
});

test('the audit log still records what was deleted', function () {
    $cliente = Cliente::factory()->create();
    $conversacion = Conversacion::factory()->create();
    $conversacion->cliente()->associate($cliente)->saveQuietly();

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('panel.whatsapp.destroy', $conversacion), ['alcance' => 'contacto']);

    expect(Auditoria::where('accion', AccionAuditoria::Eliminado)->pluck('tipo')->sort()->values()->all())
        ->toBe(['cliente', 'conversacion']);
});

test('pending queued jobs of the deleted chat are dropped', function () {
    config()->set('queue.default', 'database');

    $conversacion = Conversacion::factory()->create();
    $mensaje = MensajeWhatsapp::factory()->for($conversacion)->create(['media_id' => 'media-1']);
    $ajena = Conversacion::factory()->create();
    $mensajeAjeno = MensajeWhatsapp::factory()->for($ajena)->create(['media_id' => 'media-2']);

    ResponderConversacion::dispatch($conversacion->id, $mensaje->id);
    DescargarMediaWhatsapp::dispatch($mensaje);
    ResponderConversacion::dispatch($ajena->id, $mensajeAjeno->id);
    DescargarMediaWhatsapp::dispatch($mensajeAjeno);

    expect(DB::table('jobs')->count())->toBe(4);

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('panel.whatsapp.destroy', $conversacion), ['alcance' => 'hilo'])
        ->assertSessionHasNoErrors();

    $comandos = DB::table('jobs')->pluck('payload')->map(fn (string $payload) => json_decode($payload, true)['data']['command']);

    expect($comandos)->toHaveCount(2)
        ->and($comandos->contains(fn (string $comando) => str_contains($comando, '"conversacionId";i:'.$ajena->id.';')))->toBeTrue()
        ->and($comandos->contains(fn (string $comando) => str_contains($comando, 'MensajeWhatsapp";s:2:"id";i:'.$mensajeAjeno->id.';')))->toBeTrue();
});

test('a file downloaded after its chat was deleted is not left behind', function () {
    $conversacion = Conversacion::factory()->create();
    $mensaje = MensajeWhatsapp::factory()->for($conversacion)->create(['media_id' => 'media-123', 'media_mime' => 'image/jpeg']);

    Http::fake([
        'graph.facebook.com/v23.0/media-123' => Http::response(['url' => 'https://lookaside.fbsbx.com/foto', 'mime_type' => 'image/jpeg']),
        'lookaside.fbsbx.com/*' => function () use ($conversacion) {
            $conversacion->olvidar(false);

            return Http::response('jpg-binario', 200, ['Content-Type' => 'image/jpeg']);
        },
    ]);

    (new DescargarMediaWhatsapp($mensaje))->handle(app(WhatsappCloud::class));

    expect(Storage::disk('local')->allFiles('whatsapp/'.$conversacion->id))->toBe([])
        ->and(MensajeWhatsapp::find($mensaje->id))->toBeNull();
});

test('the jobs of a deleted chat do nothing if they still run', function () {
    $conversacion = Conversacion::factory()->create();
    $mensaje = MensajeWhatsapp::factory()->for($conversacion)->create(['media_id' => 'media-1']);
    $trabajo = new DescargarMediaWhatsapp($mensaje);
    $serializado = serialize($trabajo);

    $conversacion->olvidar(false);

    Http::fake();
    ResponderConversacion::dispatchSync($conversacion->id, $mensaje->id);

    expect(fn () => unserialize($serializado))->toThrow(ModelNotFoundException::class)
        ->and((new ReflectionClass(DescargarMediaWhatsapp::class))->getAttributes(DeleteWhenMissingModels::class))->toHaveCount(1);
    Http::assertNothingSent();
});

test('a search with only zeros does not match every number', function () {
    Conversacion::factory()->create(['wa_id' => '59899123456', 'nombre_perfil' => 'Ana']);
    Conversacion::factory()->create(['wa_id' => '59898765432', 'nombre_perfil' => 'Beto']);

    $this->actingAs(User::factory()->create())
        ->get(route('panel.whatsapp.index', ['busqueda' => '000']))
        ->assertInertia(fn ($page) => $page->has('conversaciones', 0));
});

test('a one character inbox search is ignored', function () {
    Conversacion::factory()->create(['nombre_perfil' => 'Ana']);
    Conversacion::factory()->create(['nombre_perfil' => 'Beto']);

    $this->actingAs(User::factory()->create())
        ->get(route('panel.whatsapp.index', ['busqueda' => 'z']))
        ->assertInertia(fn ($page) => $page->has('conversaciones', 2));
});

test('all shows what the ai handles and human attention what waits for a person', function () {
    $ia = Conversacion::factory()->create(['nombre_perfil' => 'La IA']);
    $derivada = Conversacion::factory()->derivada()->create(['nombre_perfil' => 'Derivada']);
    $tomada = Conversacion::factory()->create(['nombre_perfil' => 'Tomada', 'ia_activa' => false]);
    Conversacion::factory()->derivada()->create(['archivada_at' => now()]);
    $usuario = User::factory()->create();

    $this->actingAs($usuario)
        ->get(route('panel.whatsapp.index'))
        ->assertInertia(fn ($page) => $page
            ->has('conversaciones', 1)
            ->where('conversaciones.0.id', $ia->id)
            ->where('atencionHumana', 2)
        );

    $this->actingAs($usuario)
        ->get(route('panel.whatsapp.index', ['filtro' => 'humano']))
        ->assertInertia(fn ($page) => $page
            ->has('conversaciones', 2)
            ->where('conversaciones', fn ($filas) => collect($filas)->pluck('id')->sort()->values()->all() === collect([$derivada->id, $tomada->id])->sort()->values()->all())
            ->where('atencionHumana', 2)
        );

    $this->actingAs($usuario)->patch(route('panel.whatsapp.ia', $derivada), ['activa' => true]);

    $this->actingAs($usuario)
        ->get(route('panel.whatsapp.index'))
        ->assertInertia(fn ($page) => $page
            ->has('conversaciones', 2)
            ->where('atencionHumana', 1)
        );
});

test('the polling reload only sends the inbox and the open conversation', function () {
    $conversacion = Conversacion::factory()->create();
    MensajeWhatsapp::factory()->for($conversacion)->create(['cuerpo' => 'hola']);

    $this->actingAs(User::factory()->create())
        ->get(route('panel.whatsapp.show', $conversacion))
        ->assertInertia(fn ($page) => $page
            ->has('filtros')
            ->reloadOnly(['conversaciones', 'atencionHumana', 'conversacion'], fn ($recarga) => $recarga
                ->has('conversaciones', 1)
                ->where('atencionHumana', 0)
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
