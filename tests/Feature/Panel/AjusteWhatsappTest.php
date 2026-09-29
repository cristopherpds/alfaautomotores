<?php

use App\Enums\UserRole;
use App\Models\Ajuste;
use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Un contacto del botón, para no repetirlo en cada test.
 *
 * @param  array<string, mixed>  $reemplazos
 * @return array<string, mixed>
 */
function contactoDeWhatsapp(array $reemplazos = []): array
{
    return [
        'nombre' => 'Ventas',
        'detalle' => 'Autos usados',
        'numero' => '59899111111',
        'mensaje' => 'Hola, vengo de la web.',
        'respaldo' => false,
        'horario' => ['siempre' => true, 'dias' => [], 'desde' => '08:30', 'hasta' => '18:00'],
        ...$reemplazos,
    ];
}

/**
 * El ajuste completo, con los contactos que se le pasen.
 *
 * @param  list<array<string, mixed>>|null  $contactos
 * @param  array<string, mixed>  $reemplazos
 * @return array<string, mixed>
 */
function ajusteDeWhatsapp(?array $contactos = null, array $reemplazos = []): array
{
    return [
        'activo' => true,
        'titulo' => '¡Hola!',
        'subtitulo' => '¿Cómo podemos ayudarte?',
        'contactos' => $contactos ?? [contactoDeWhatsapp(['respaldo' => true])],
        ...$reemplazos,
    ];
}

/**
 * Ventas de lunes a sábado en horario de local y la guardia a la noche,
 * que cruza la medianoche. La guardia es el respaldo.
 *
 * @return list<array<string, mixed>>
 */
function ventasYGuardia(): array
{
    return [
        contactoDeWhatsapp([
            'nombre' => 'Ventas',
            'numero' => '59899111111',
            'horario' => ['siempre' => false, 'dias' => [1, 2, 3, 4, 5, 6], 'desde' => '08:30', 'hasta' => '18:00'],
        ]),
        contactoDeWhatsapp([
            'nombre' => 'Taller',
            'numero' => '59899222222',
            'horario' => ['siempre' => false, 'dias' => [1, 2, 3, 4, 5], 'desde' => '08:30', 'hasta' => '12:00'],
        ]),
        contactoDeWhatsapp([
            'nombre' => 'Guardia',
            'numero' => '59899333333',
            'respaldo' => true,
            'horario' => ['siempre' => false, 'dias' => [1, 2, 3, 4, 5, 6, 7], 'desde' => '20:00', 'hasta' => '08:00'],
        ]),
    ];
}

test('only admins can open and save the WhatsApp settings', function (UserRole $role, int $estado) {
    $usuario = User::factory()->role($role)->create();

    $this->actingAs($usuario)->get(route('panel.ajustes.whatsapp'))->assertStatus($estado);
    $this->actingAs($usuario)
        ->put(route('panel.ajustes.whatsapp.update'), ajusteDeWhatsapp())
        ->assertStatus($estado === 200 ? 302 : 403);
})->with([
    'admin' => [UserRole::Admin, 200],
    'vendedor' => [UserRole::Vendedor, 403],
    'equipo' => [UserRole::Equipo, 403],
]);

test('without saved settings the site offers one contact with the configured number', function () {
    config()->set('alfa.whatsapp', '59899000000');

    $this->get(route('home'))
        ->assertInertia(fn ($page) => $page
            ->where('site.whatsapp', '59899000000')
            ->where('site.botonWhatsapp.activo', true)
            ->where('site.botonWhatsapp.titulo', '¡Hola!')
            ->where('site.botonWhatsapp.fueraDeHorario', false)
            ->has('site.botonWhatsapp.contactos', 1)
        );
});

test('an old single-number setting still works as one backup contact', function () {
    Ajuste::guardar(Ajuste::BOTON_WHATSAPP, [
        'activo' => true, 'numero' => '59899555555', 'mensaje' => 'Hola', 'saludo' => 'x', 'subtitulo' => 'y',
    ]);

    $this->get(route('home'))
        ->assertInertia(fn ($page) => $page
            ->where('site.whatsapp', '59899555555')
            ->where('site.botonWhatsapp.contactos.0.numero', '59899555555')
        );
});

test('during shop hours the card lists everyone on duty, in the panel order', function () {
    $this->travelTo(Carbon::parse('2026-09-28 10:00')); // lunes
    Ajuste::guardar(Ajuste::BOTON_WHATSAPP, ajusteDeWhatsapp(ventasYGuardia()));

    $this->get(route('home'))
        ->assertInertia(fn ($page) => $page
            ->where('site.botonWhatsapp.fueraDeHorario', false)
            ->has('site.botonWhatsapp.contactos', 2)
            ->where('site.botonWhatsapp.contactos.0.nombre', 'Ventas')
            ->where('site.botonWhatsapp.contactos.1.nombre', 'Taller')
            // Los links sueltos del sitio van al primero disponible.
            ->where('site.whatsapp', '59899111111')
        );
});

test('from a given hour a different number takes over', function () {
    Ajuste::guardar(Ajuste::BOTON_WHATSAPP, ajusteDeWhatsapp(ventasYGuardia()));

    // Lunes 14:00: el taller ya cerró, ventas sigue.
    $this->travelTo(Carbon::parse('2026-09-28 14:00'));
    $this->get(route('home'))->assertInertia(fn ($page) => $page
        ->has('site.botonWhatsapp.contactos', 1)
        ->where('site.botonWhatsapp.contactos.0.nombre', 'Ventas'));

    // Lunes 22:00 y martes 06:00: la guardia, que cruza la medianoche.
    foreach (['2026-09-28 22:00', '2026-09-29 06:00'] as $momento) {
        $this->travelTo(Carbon::parse($momento));
        $this->get(route('home'))->assertInertia(fn ($page) => $page
            ->has('site.botonWhatsapp.contactos', 1)
            ->where('site.botonWhatsapp.contactos.0.nombre', 'Guardia')
            ->where('site.whatsapp', '59899333333'));
    }
});

test('when nobody is on duty the backup shows up and the card says so', function () {
    // Lunes 19:00: ventas cerró y la guardia arranca a las 20.
    $this->travelTo(Carbon::parse('2026-09-28 19:00'));
    Ajuste::guardar(Ajuste::BOTON_WHATSAPP, ajusteDeWhatsapp(ventasYGuardia()));

    $this->get(route('home'))
        ->assertInertia(fn ($page) => $page
            ->where('site.botonWhatsapp.fueraDeHorario', true)
            ->has('site.botonWhatsapp.contactos', 1)
            ->where('site.botonWhatsapp.contactos.0.nombre', 'Guardia')
        );
});

test('several contacts that share days are saved from the panel', function () {
    // El caso que se escapó: `distinct` con comodín comparaba los días de
    // todos los contactos entre sí y rechazaba dos que atienden el lunes.
    $this->actingAs(User::factory()->admin()->create())
        ->put(route('panel.ajustes.whatsapp.update'), ajusteDeWhatsapp(ventasYGuardia()))
        ->assertSessionHasNoErrors();

    $contactos = Ajuste::botonWhatsapp()['contactos'];

    expect($contactos)->toHaveCount(3)
        ->and(array_column($contactos, 'nombre'))->toBe(['Ventas', 'Taller', 'Guardia'])
        ->and($contactos[0]['horario']['dias'])->toBe([1, 2, 3, 4, 5, 6])
        ->and($contactos[2]['respaldo'])->toBeTrue();
});

test('a day repeated inside one contact is cleaned up', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put(route('panel.ajustes.whatsapp.update'), ajusteDeWhatsapp([
            contactoDeWhatsapp([
                'respaldo' => true,
                'horario' => ['siempre' => false, 'dias' => [1, 1, 2], 'desde' => '08:30', 'hasta' => '18:00'],
            ]),
        ]))
        ->assertSessionHasNoErrors();

    expect(Ajuste::botonWhatsapp()['contactos'][0]['horario']['dias'])->toBe([1, 2]);
});

test('saved settings reach every public page', function (string $ruta) {
    $this->actingAs(User::factory()->admin()->create())
        ->put(route('panel.ajustes.whatsapp.update'), ajusteDeWhatsapp(null, ['titulo' => '¡Buenas!']))
        ->assertSessionHasNoErrors();

    $this->get(route($ruta))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('site.whatsapp', '59899111111')
            ->where('site.botonWhatsapp.titulo', '¡Buenas!')
            ->where('site.botonWhatsapp.contactos.0.mensaje', 'Hola, vengo de la web.')
        );
})->with(['home', 'catalogo', 'taller']);

test('the button can be turned off', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put(route('panel.ajustes.whatsapp.update'), ajusteDeWhatsapp(null, ['activo' => false]))
        ->assertSessionHasNoErrors();

    $this->get(route('home'))
        ->assertInertia(fn ($page) => $page->where('site.botonWhatsapp.activo', false));
});

test('numbers are cleaned up and a short one is rejected', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->put(route('panel.ajustes.whatsapp.update'), ajusteDeWhatsapp([
            contactoDeWhatsapp(['numero' => '+598 99 123 456', 'respaldo' => true]),
        ]))
        ->assertSessionHasNoErrors();

    expect(Ajuste::botonWhatsapp()['contactos'][0]['numero'])->toBe('59899123456');

    $this->actingAs($admin)
        ->from(route('panel.ajustes.whatsapp'))
        ->put(route('panel.ajustes.whatsapp.update'), ajusteDeWhatsapp([
            contactoDeWhatsapp(['numero' => '099 12', 'respaldo' => true]),
        ]))
        ->assertSessionHasErrors('contactos.0.numero');
});

test('there has to be exactly one backup contact', function (array $respaldos) {
    $contactos = array_map(
        fn (bool $respaldo, int $indice) => contactoDeWhatsapp(['respaldo' => $respaldo, 'numero' => '5989900000'.$indice]),
        $respaldos,
        array_keys($respaldos),
    );

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('panel.ajustes.whatsapp'))
        ->put(route('panel.ajustes.whatsapp.update'), ajusteDeWhatsapp($contactos))
        ->assertSessionHasErrors('contactos');
})->with([
    'ninguno' => [[false, false]],
    'dos' => [[true, true]],
]);

test('a scheduled contact needs at least one day', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->from(route('panel.ajustes.whatsapp'))
        ->put(route('panel.ajustes.whatsapp.update'), ajusteDeWhatsapp([
            contactoDeWhatsapp([
                'respaldo' => true,
                'horario' => ['siempre' => false, 'dias' => [], 'desde' => '08:30', 'hasta' => '18:00'],
            ]),
        ]))
        ->assertSessionHasErrors('contactos.0.horario.dias');
});

test('changing the button is recorded in the audit log', function () {
    $admin = User::factory()->admin()->create(['name' => 'Dueño']);

    $this->actingAs($admin)->put(route('panel.ajustes.whatsapp.update'), ajusteDeWhatsapp());
    $this->actingAs($admin)->put(route('panel.ajustes.whatsapp.update'), ajusteDeWhatsapp(null, ['titulo' => 'Otro']));

    $entradas = Auditoria::where('tipo', 'ajuste')->orderBy('id')->get();

    expect($entradas)->toHaveCount(2)
        ->and($entradas->pluck('etiqueta')->unique()->all())->toBe(['Botón de WhatsApp'])
        ->and($entradas->pluck('usuario')->unique()->all())->toBe(['Dueño']);
});
