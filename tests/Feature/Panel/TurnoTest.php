<?php

use App\Enums\EstadoTurno;
use App\Enums\OrigenTurno;
use App\Enums\UserRole;
use App\Models\Puesto;
use App\Models\Servicio;
use App\Models\Turno;
use App\Models\User;

/*
 * El calendario del panel y el alta manual. A diferencia de la web, el taller
 * puede cargar un turno para hoy mismo y entra ya confirmado.
 */

/**
 * Los datos del cliente para el alta desde el panel.
 *
 * @return array<string, mixed>
 */
function datosDelTurnoInterno(array $extra = []): array
{
    return [
        'nombre' => 'Luis',
        'apellido' => 'Gómez',
        'email' => 'luis@example.com',
        'celular' => '099 987 654',
        ...$extra,
    ];
}

test('the calendar lists the appointments of the visible range as events', function () {
    $lunes = abrirElTaller();

    $servicio = Servicio::factory()->create(['nombre' => 'Service completo']);
    $puesto = Puesto::query()->firstOrFail();

    $dentro = Turno::factory()
        ->for($servicio)->for($puesto)
        ->inicia($lunes->copy()->setTime(10, 0))
        ->create(['apellido' => 'Pérez']);

    Turno::factory()->for($servicio)->for($puesto)
        ->inicia($lunes->copy()->addMonths(3)->setTime(10, 0))
        ->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('panel.turnos.index', [
            'desde' => $lunes->copy()->startOfMonth()->toDateString(),
            'hasta' => $lunes->copy()->endOfMonth()->toDateString(),
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('panel/turnos/index')
            ->has('turnos', 1)
            ->where('turnos.0.id', (string) $dentro->id)
            ->where('turnos.0.title', 'Service completo · Pérez')
            ->where('turnos.0.classNames', ['turno--pendiente'])
            ->where('turnos.0.extendedProps.cliente', $dentro->cliente())
            ->where('puedeGestionar', true)
        );
});

test('the team role sees the calendar but cannot touch it', function () {
    $lunes = abrirElTaller();

    $servicio = Servicio::factory()->duracion(90)->create(['slug' => 'service']);
    $turno = Turno::factory()->for($servicio)->for(Puesto::query()->firstOrFail())->create();

    $equipo = User::factory()->role(UserRole::Equipo)->create();

    $this->actingAs($equipo)
        ->get(route('panel.turnos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('panel/turnos/index')
            ->where('puedeGestionar', false)
        );

    $this->actingAs($equipo)
        ->post(route('panel.turnos.store'), datosDelTurnoInterno([
            'servicio' => $servicio->slug,
            'fecha' => $lunes->toDateString(),
            'hora' => '10:00',
        ]))
        ->assertForbidden();

    $this->actingAs($equipo)
        ->patch(route('panel.turnos.estado', $turno), ['estado' => EstadoTurno::Confirmado->value])
        ->assertForbidden();

    $this->actingAs($equipo)
        ->delete(route('panel.turnos.destroy', $turno))
        ->assertForbidden();
});

test('guests are sent to the login', function () {
    $this->get(route('panel.turnos.index'))->assertRedirect(route('login'));
});

test('the shop books an appointment by hand, confirmed and for today', function () {
    abrirElTaller();

    $servicio = Servicio::factory()->duracion(90)->create(['slug' => 'service']);

    // El lunes congelado, a las nueve de la mañana: hoy mismo.
    $this->actingAs(User::factory()->role(UserRole::Vendedor)->create())
        ->from(route('panel.turnos.index'))
        ->post(route('panel.turnos.store'), datosDelTurnoInterno([
            'servicio' => $servicio->slug,
            'fecha' => '2026-09-14',
            'hora' => '14:00',
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('panel.turnos.index'));

    $turno = Turno::sole();

    expect($turno->estado)->toBe(EstadoTurno::Confirmado)
        ->and($turno->origen)->toBe(OrigenTurno::Panel)
        ->and($turno->schedule_id)->not->toBeNull();
});

test('a taken time cannot be booked twice by hand', function () {
    abrirElTaller(puestos: 1);

    $servicio = Servicio::factory()->duracion(90)->create(['slug' => 'service']);
    $vendedor = User::factory()->role(UserRole::Vendedor)->create();

    $datos = datosDelTurnoInterno([
        'servicio' => $servicio->slug,
        'fecha' => '2026-09-14',
        'hora' => '14:00',
    ]);

    $this->actingAs($vendedor)->post(route('panel.turnos.store'), $datos)->assertSessionHasNoErrors();
    $this->actingAs($vendedor)->post(route('panel.turnos.store'), $datos)->assertSessionHasErrors('hora');

    expect(Turno::count())->toBe(1);
});

test('confirming an appointment keeps its slot taken', function () {
    $lunes = abrirElTaller(puestos: 1);

    $servicio = Servicio::factory()->duracion(90)->create(['slug' => 'service']);

    $turno = Turno::reservar($servicio, $lunes->copy()->setTime(10, 0), [
        'nombre' => 'Ana', 'apellido' => 'Pérez', 'email' => 'a@b.com', 'celular' => '099',
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('panel.turnos.index'))
        ->patch(route('panel.turnos.estado', $turno), ['estado' => EstadoTurno::Confirmado->value])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('panel.turnos.index'));

    expect($turno->fresh()->estado)->toBe(EstadoTurno::Confirmado)
        ->and(Puesto::huecosDelDia($lunes, $servicio))->not->toContain('10:00');
});

test('cancelling from the panel puts the slot back on the site', function () {
    $lunes = abrirElTaller(puestos: 1);

    $servicio = Servicio::factory()->duracion(90)->create(['slug' => 'service']);

    $turno = Turno::reservar($servicio, $lunes->copy()->setTime(10, 0), [
        'nombre' => 'Ana', 'apellido' => 'Pérez', 'email' => 'a@b.com', 'celular' => '099',
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->patch(route('panel.turnos.estado', $turno), ['estado' => EstadoTurno::Cancelado->value])
        ->assertSessionHasNoErrors();

    expect($turno->fresh()->estado)->toBe(EstadoTurno::Cancelado)
        ->and($turno->fresh()->schedule_id)->toBeNull()
        ->and(Puesto::huecosDelDia($lunes, $servicio))->toContain('10:00');
});

test('deleting an appointment takes its slot back too', function () {
    $lunes = abrirElTaller(puestos: 1);

    $servicio = Servicio::factory()->duracion(90)->create(['slug' => 'service']);

    $turno = Turno::reservar($servicio, $lunes->copy()->setTime(10, 0), [
        'nombre' => 'Ana', 'apellido' => 'Pérez', 'email' => 'a@b.com', 'celular' => '099',
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('panel.turnos.destroy', $turno))
        ->assertSessionHasNoErrors();

    expect(Turno::count())->toBe(0)
        ->and(Puesto::huecosDelDia($lunes, $servicio))->toContain('10:00');
});

test('the panel offers the free slots of the chosen day', function () {
    $lunes = abrirElTaller();

    $servicio = Servicio::factory()->duracion(90)->create(['slug' => 'service']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('panel.turnos.index', ['servicio' => $servicio->slug, 'fecha' => $lunes->toDateString()]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('huecos', ['08:30', '10:00', '14:00', '15:30'])
            ->etc()
        );
});
