<?php

use App\Enums\MotivoDerivacion;
use App\Enums\UserRole;
use App\Models\Conversacion;
use App\Models\MensajeWhatsapp;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

test('guests cannot read the notices', function () {
    $this->getJson(route('panel.whatsapp.avisos'))->assertUnauthorized();
});

test('the whole team reads the notices, like the inbox', function (UserRole $rol) {
    $this->actingAs(User::factory()->role($rol)->create())
        ->getJson(route('panel.whatsapp.avisos'))
        ->assertOk();
})->with([UserRole::Admin, UserRole::Vendedor, UserRole::Equipo]);

test('the counters add up unread messages and conversations waiting for a person', function () {
    Conversacion::factory()->create(['no_leidos' => 3]);
    Conversacion::factory()->create(['no_leidos' => 2, 'ia_activa' => false]);
    Conversacion::factory()->derivada()->create();
    // Archivadas no cuentan, ni para no leídos ni para atención humana.
    Conversacion::factory()->derivada()->create(['no_leidos' => 7, 'archivada_at' => now()]);

    $this->actingAs(User::factory()->create())
        ->getJson(route('panel.whatsapp.avisos'))
        ->assertOk()
        ->assertJsonPath('noLeidos', 5)
        ->assertJsonPath('atencionHumana', 2);
});

test('without a cursor it returns no events, only the current cursor', function () {
    $mensaje = MensajeWhatsapp::factory()->create();
    MensajeWhatsapp::factory()->saliente()->for($mensaje->conversacion)->create();
    $derivada = Conversacion::factory()->derivada()->create(['derivada_at' => now()->subMinute()]);

    $this->actingAs(User::factory()->create())
        ->getJson(route('panel.whatsapp.avisos'))
        ->assertOk()
        ->assertJsonPath('eventos', [])
        ->assertJsonPath('cursor.mensaje', $mensaje->id)
        ->assertJsonPath('cursor.derivacion', $derivada->derivada_at->getTimestamp());
});

test('with a cursor it returns the newest incoming message of each conversation', function () {
    $vieja = MensajeWhatsapp::factory()->create();
    $conversacion = Conversacion::factory()->create(['nombre_perfil' => 'Ana']);
    MensajeWhatsapp::factory()->for($conversacion)->create(['cuerpo' => 'Primero']);
    $ultimo = MensajeWhatsapp::factory()->for($conversacion)->create(['cuerpo' => str_repeat('Hola ', 40)]);
    // Lo que sale del concesionario no avisa.
    MensajeWhatsapp::factory()->saliente()->for($conversacion)->create();

    $respuesta = $this->actingAs(User::factory()->create())
        ->getJson(route('panel.whatsapp.avisos', ['mensaje' => $vieja->id, 'derivacion' => now()->getTimestamp()]))
        ->assertOk()
        ->assertJsonCount(1, 'eventos')
        ->assertJsonPath('eventos.0.conversacionId', $conversacion->id)
        ->assertJsonPath('eventos.0.nombre', 'Ana')
        ->assertJsonPath('eventos.0.tipo', 'mensaje')
        ->assertJsonPath('cursor.mensaje', $ultimo->id);

    expect($respuesta->json('eventos.0.preview'))
        ->toBe(Str::limit($ultimo->cuerpo, 80))
        ->and(mb_strlen($respuesta->json('eventos.0.preview')))->toBeLessThanOrEqual(83);
});

test('with an up to date cursor there is nothing new', function () {
    $mensaje = MensajeWhatsapp::factory()->create();

    $this->actingAs(User::factory()->create())
        ->getJson(route('panel.whatsapp.avisos', ['mensaje' => $mensaje->id, 'derivacion' => now()->getTimestamp()]))
        ->assertOk()
        ->assertJsonPath('eventos', []);
});

test('handoffs by the AI after the cursor are events, manual ones are not', function () {
    $cursor = now()->subMinutes(10)->getTimestamp();

    Conversacion::factory()->derivada()->create(['derivada_at' => now()->subHour()]);
    $derivada = Conversacion::factory()->derivada(MotivoDerivacion::Cliente)->create(['nombre_perfil' => 'Beto']);
    Conversacion::factory()->derivada(MotivoDerivacion::Manual)->create();

    $this->actingAs(User::factory()->create())
        ->getJson(route('panel.whatsapp.avisos', ['mensaje' => 0, 'derivacion' => $cursor]))
        ->assertOk()
        ->assertJsonCount(1, 'eventos')
        ->assertJsonPath('eventos.0.conversacionId', $derivada->id)
        ->assertJsonPath('eventos.0.nombre', 'Beto')
        ->assertJsonPath('eventos.0.tipo', 'derivacion')
        ->assertJsonPath('eventos.0.preview', MotivoDerivacion::Cliente->label());
});

test('two handoffs in the same second are both notified', function () {
    $this->freezeSecond();
    $usuario = User::factory()->create();
    $primera = Conversacion::factory()->derivada(MotivoDerivacion::Cliente)->create(['derivada_at' => now()]);

    $cursor = $this->actingAs($usuario)
        ->getJson(route('panel.whatsapp.avisos'))
        ->assertJsonPath('cursor.derivacionId', $primera->id)
        ->json('cursor');

    $segunda = Conversacion::factory()->derivada(MotivoDerivacion::Cliente)->create(['derivada_at' => now()]);

    $siguiente = $this->actingAs($usuario)
        ->getJson(route('panel.whatsapp.avisos', $cursor))
        ->assertOk()
        ->assertJsonCount(1, 'eventos')
        ->assertJsonPath('eventos.0.conversacionId', $segunda->id)
        ->assertJsonPath('cursor.derivacionId', $segunda->id)
        ->json('cursor');

    $this->actingAs($usuario)
        ->getJson(route('panel.whatsapp.avisos', $siguiente))
        ->assertJsonPath('eventos', []);
});

test('a cursor without the handoff id does not repeat that second', function () {
    $this->freezeSecond();
    Conversacion::factory()->derivada(MotivoDerivacion::Cliente)->create(['derivada_at' => now()]);

    $this->actingAs(User::factory()->create())
        ->getJson(route('panel.whatsapp.avisos', ['mensaje' => 0, 'derivacion' => now()->getTimestamp()]))
        ->assertJsonPath('eventos', []);
});

test('a message that arrives while the response is built waits for the next poll', function () {
    $vieja = MensajeWhatsapp::factory()->create();
    $nueva = MensajeWhatsapp::factory()->create();
    $tardia = null;

    DB::listen(function ($query) use (&$tardia) {
        if ($tardia === null && str_contains($query->sql, 'max("id")')) {
            $tardia = MensajeWhatsapp::factory()->create();
        }
    });

    $this->actingAs(User::factory()->create())
        ->getJson(route('panel.whatsapp.avisos', ['mensaje' => $vieja->id, 'derivacion' => now()->getTimestamp()]))
        ->assertJsonCount(1, 'eventos')
        ->assertJsonPath('eventos.0.conversacionId', $nueva->conversacion_id)
        ->assertJsonPath('cursor.mensaje', $nueva->id);

    expect($tardia)->not->toBeNull();
});

test('the cursor must be numeric', function () {
    $this->actingAs(User::factory()->create())
        ->getJson(route('panel.whatsapp.avisos', ['mensaje' => 'abc']))
        ->assertUnprocessable();
});

test('the panel shares the counters for the first render of the sidebar', function () {
    Conversacion::factory()->derivada()->create(['no_leidos' => 4]);

    $this->actingAs(User::factory()->role(UserRole::Equipo)->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('whatsapp.noLeidos', 4)
            ->where('whatsapp.atencionHumana', 1)
        );
});

test('guests do not get the counters', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('whatsapp', null));
});
