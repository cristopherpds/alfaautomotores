<?php

use App\Enums\EstadoTurno;
use App\Enums\OrigenTurno;
use App\Models\Puesto;
use App\Models\Servicio;
use App\Models\Turno;

/*
 * La reserva desde la web. El turno entra pendiente y ya ocupa el horario: la
 * confirmación se hace después, por WhatsApp.
 */

/**
 * Los datos del cliente, para no repetirlos en cada test.
 *
 * El nombre no puede chocar con los helpers de otros archivos: Pest carga todo
 * en un mismo proceso.
 *
 * @return array<string, mixed>
 */
function datosDelTurno(array $extra = []): array
{
    return [
        'nombre' => 'Ana',
        'apellido' => 'Pérez',
        'email' => 'ana@example.com',
        'celular' => '099 123 456',
        ...$extra,
    ];
}

test('a visitor books a slot and it comes in as pending', function () {
    $lunes = abrirElTaller();

    $servicio = Servicio::factory()->duracion(90)->create(['slug' => 'service']);

    $this->from(route('taller'))
        ->post(route('taller.turnos.store'), datosDelTurno([
            'servicio' => $servicio->slug,
            'fecha' => $lunes->toDateString(),
            'hora' => '10:00',
            'vehiculo_marca' => 'Fiat',
            'vehiculo_modelo' => 'Cronos',
            'vehiculo_anio' => 2019,
            'comentario' => 'Hace un ruido al frenar.',
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('taller'));

    $turno = Turno::sole();

    expect($turno->estado)->toBe(EstadoTurno::Pendiente)
        ->and($turno->origen)->toBe(OrigenTurno::Web)
        ->and($turno->inicia_at->format('Y-m-d H:i'))->toBe($lunes->format('Y-m-d').' 10:00')
        // El fin sale de la duración del servicio, no del formulario.
        ->and($turno->termina_at->format('H:i'))->toBe('11:30')
        ->and($turno->vehiculo())->toBe('Fiat Cronos 2019')
        // La cita espejo es lo que hace que el horario deje de ofrecerse.
        ->and($turno->schedule_id)->not->toBeNull();
});

test('the shop fills up before the slot disappears', function () {
    $lunes = abrirElTaller(puestos: 2);

    $servicio = Servicio::factory()->duracion(90)->create(['slug' => 'service']);

    $reservar = fn () => $this->post(route('taller.turnos.store'), datosDelTurno([
        'servicio' => $servicio->slug,
        'fecha' => $lunes->toDateString(),
        'hora' => '10:00',
    ]));

    // Dos puestos: dos autos a las diez.
    $reservar()->assertSessionHasNoErrors();
    $reservar()->assertSessionHasNoErrors();

    expect(Puesto::huecosDelDia($lunes, $servicio))->not->toContain('10:00');

    $reservar()->assertSessionHasErrors('hora');

    expect(Turno::count())->toBe(2);
});

test('cancelling a booking gives the slot back', function () {
    $lunes = abrirElTaller(puestos: 1);

    $servicio = Servicio::factory()->duracion(90)->create(['slug' => 'service']);

    $this->post(route('taller.turnos.store'), datosDelTurno([
        'servicio' => $servicio->slug,
        'fecha' => $lunes->toDateString(),
        'hora' => '10:00',
    ]))->assertSessionHasNoErrors();

    expect(Puesto::huecosDelDia($lunes, $servicio))->not->toContain('10:00');

    Turno::sole()->cancelar();

    expect(Puesto::huecosDelDia($lunes, $servicio))->toContain('10:00')
        ->and(Turno::sole()->estado)->toBe(EstadoTurno::Cancelado)
        ->and(Turno::sole()->schedule_id)->toBeNull();
});

test('the client fields are required', function (string $campo) {
    $lunes = abrirElTaller();

    $servicio = Servicio::factory()->duracion(90)->create(['slug' => 'service']);

    $datos = datosDelTurno([
        'servicio' => $servicio->slug,
        'fecha' => $lunes->toDateString(),
        'hora' => '10:00',
    ]);

    unset($datos[$campo]);

    $this->post(route('taller.turnos.store'), $datos)->assertSessionHasErrors($campo);

    expect(Turno::count())->toBe(0);
})->with(['nombre', 'apellido', 'email', 'celular']);

test('a booking outside the window is rejected', function (string $fecha) {
    abrirElTaller();

    $servicio = Servicio::factory()->duracion(90)->create(['slug' => 'service']);

    $this->post(route('taller.turnos.store'), datosDelTurno([
        'servicio' => $servicio->slug,
        'fecha' => $fecha,
        'hora' => '10:00',
    ]))->assertSessionHasErrors('fecha');

    expect(Turno::count())->toBe(0);
})->with([
    'hoy mismo' => '2026-09-14',
    'ayer' => '2026-09-13',
    'pasado el mes' => '2026-10-20',
]);

test('a time the shop does not offer is rejected', function (string $hora) {
    $lunes = abrirElTaller();

    $servicio = Servicio::factory()->duracion(90)->create(['slug' => 'service']);

    $this->post(route('taller.turnos.store'), datosDelTurno([
        'servicio' => $servicio->slug,
        'fecha' => $lunes->toDateString(),
        'hora' => $hora,
    ]))->assertSessionHasErrors('hora');

    expect(Turno::count())->toBe(0);
})->with([
    'antes de abrir' => '07:00',
    'en el corte del mediodía' => '12:30',
    'a una hora que no arranca ningún turno' => '10:15',
]);

test('sundays are not bookable', function () {
    $lunes = abrirElTaller();

    $servicio = Servicio::factory()->duracion(90)->create(['slug' => 'service']);

    $this->post(route('taller.turnos.store'), datosDelTurno([
        'servicio' => $servicio->slug,
        'fecha' => $lunes->copy()->addDays(6)->toDateString(),
        'hora' => '10:00',
    ]))->assertSessionHasErrors('hora');

    expect(Turno::count())->toBe(0);
});

test('a job quoted at the shop cannot be booked online', function () {
    $lunes = abrirElTaller();

    $servicio = Servicio::factory()->duracion(60)->noAgendable()->create(['slug' => 'correa']);

    $this->post(route('taller.turnos.store'), datosDelTurno([
        'servicio' => $servicio->slug,
        'fecha' => $lunes->toDateString(),
        'hora' => '10:00',
    ]))->assertSessionHasErrors('servicio');

    expect(Turno::count())->toBe(0);
});

test('an inactive service cannot be booked', function () {
    $lunes = abrirElTaller();

    $servicio = Servicio::factory()->duracion(60)->inactivo()->create(['slug' => 'viejo']);

    $this->post(route('taller.turnos.store'), datosDelTurno([
        'servicio' => $servicio->slug,
        'fecha' => $lunes->toDateString(),
        'hora' => '10:00',
    ]))->assertSessionHasErrors('servicio');

    expect(Turno::count())->toBe(0);
});

/*
 * La matrícula es opcional y va en los dos rubros: sirve para reconocer el auto
 * en el patio, no para identificar al cliente.
 */
test('the plate number is optional and gets stored when given', function () {
    $lunes = abrirElTaller();

    $servicio = Servicio::factory()->duracion(90)->create(['slug' => 'service']);

    $this->post(route('taller.turnos.store'), datosDelTurno([
        'servicio' => $servicio->slug,
        'fecha' => $lunes->toDateString(),
        'hora' => '10:00',
    ]))->assertSessionHasNoErrors();

    expect(Turno::sole()->matricula)->toBeNull();

    $this->post(route('taller.turnos.store'), datosDelTurno([
        'servicio' => $servicio->slug,
        'fecha' => $lunes->toDateString(),
        'hora' => '14:00',
        'matricula' => 'ABC 1234',
    ]))->assertSessionHasNoErrors();

    expect(Turno::where('matricula', 'ABC 1234')->sole())->not->toBeNull();
});

test('an overlong plate number is rejected', function () {
    $lunes = abrirElTaller();

    $servicio = Servicio::factory()->duracion(90)->create(['slug' => 'service']);

    $this->from(route('taller'))
        ->post(route('taller.turnos.store'), datosDelTurno([
            'servicio' => $servicio->slug,
            'fecha' => $lunes->toDateString(),
            'hora' => '10:00',
            'matricula' => str_repeat('A', 13),
        ]))
        ->assertSessionHasErrors('matricula');
});
