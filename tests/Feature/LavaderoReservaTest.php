<?php

use App\Enums\EstadoTurno;
use App\Enums\OrigenTurno;
use App\Models\Puesto;
use App\Models\Servicio;
use App\Models\Turno;
use Illuminate\Testing\TestResponse;

/*
 * La reserva de un lavado desde la web. Funciona igual que la del taller —el
 * turno entra pendiente y ya ocupa el horario— pero contra los boxes del
 * lavadero, que son capacidad aparte.
 */

/**
 * Los datos del cliente. El nombre no puede chocar con los helpers de otros
 * archivos: Pest carga todo en un mismo proceso.
 *
 * @return array<string, mixed>
 */
function datosDelLavado(array $extra = []): array
{
    return [
        'nombre' => 'Ana',
        'apellido' => 'Pérez',
        'email' => 'ana@example.com',
        'celular' => '099 123 456',
        ...$extra,
    ];
}

test('a visitor books a wash and it comes in as pending', function () {
    $lunes = abrirElLavadero();

    $servicio = Servicio::factory()->lavadero()->duracion(45)->create(['slug' => 'lavado-auto']);

    $this->from(route('lavadero'))
        ->post(route('lavadero.turnos.store'), datosDelLavado([
            'servicio' => $servicio->slug,
            'fecha' => $lunes->toDateString(),
            'hora' => '10:00',
            'vehiculo_marca' => 'Fiat',
            'vehiculo_modelo' => 'Cronos',
            'vehiculo_anio' => 2019,
            'matricula' => 'ABC 1234',
            'comentario' => 'Tiene pelo de perro en el baúl.',
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('lavadero'));

    $turno = Turno::sole();

    expect($turno->estado)->toBe(EstadoTurno::Pendiente)
        ->and($turno->origen)->toBe(OrigenTurno::Web)
        ->and($turno->inicia_at->format('Y-m-d H:i'))->toBe($lunes->format('Y-m-d').' 10:00')
        // El fin sale de la duración del servicio, no del formulario.
        ->and($turno->termina_at->format('H:i'))->toBe('10:45')
        ->and($turno->matricula)->toBe('ABC 1234')
        // El box del lavadero, no un puesto de mecánica.
        ->and($turno->puesto->nombre)->toBe('Box de lavado 1')
        // La cita espejo es lo que hace que el horario deje de ofrecerse.
        ->and($turno->schedule_id)->not->toBeNull();
});

/*
 * El invariante que justifica todo el eje de rubro: las dos capacidades son
 * independientes. Si alguna vez `Puesto::huecosDelDia()` vuelve a sumar todos
 * los puestos activos, este test es el que lo caza.
 */
test('booking a wash does not eat into the workshop capacity', function () {
    $lunes = abrirElTaller(1);
    abrirElLavadero(1);

    $service = Servicio::factory()->duracion(60)->create(['slug' => 'service']);
    $lavado = Servicio::factory()->lavadero()->duracion(60)->create(['slug' => 'lavado-auto']);

    // Con un solo box, este lavado deja el lavadero lleno a las 10:30.
    $this->post(route('lavadero.turnos.store'), datosDelLavado([
        'servicio' => $lavado->slug,
        'fecha' => $lunes->toDateString(),
        'hora' => '10:30',
    ]))->assertSessionHasNoErrors();

    expect(Puesto::huecosDelDia($lunes, $lavado))->not->toContain('10:30')
        // …y el único puesto del taller sigue libre a esa misma hora.
        ->and(Puesto::huecosDelDia($lunes, $service))->toContain('10:30');

    // El camino de vuelta: llenar el taller no toca el lavadero.
    $this->post(route('taller.turnos.store'), datosDelTurno([
        'servicio' => $service->slug,
        'fecha' => $lunes->toDateString(),
        'hora' => '09:30',
    ]))->assertSessionHasNoErrors();

    expect(Puesto::huecosDelDia($lunes, $service))->not->toContain('09:30')
        ->and(Puesto::huecosDelDia($lunes, $lavado))->toContain('09:30');
});

/*
 * Las dos rutas comparten la tabla de servicios, así que cada una tiene que
 * rechazar los del otro rubro aunque el slug exista y esté activo.
 */
test('a workshop job cannot be booked from the car wash page', function () {
    $lunes = abrirElTaller();
    abrirElLavadero();

    $service = Servicio::factory()->duracion(60)->create(['slug' => 'service']);

    $this->from(route('lavadero'))
        ->post(route('lavadero.turnos.store'), datosDelLavado([
            'servicio' => $service->slug,
            'fecha' => $lunes->toDateString(),
            'hora' => '10:00',
        ]))
        ->assertSessionHasErrors('servicio');

    expect(Turno::count())->toBe(0);
});

test('a wash cannot be booked from the workshop page', function () {
    $lunes = abrirElTaller();
    abrirElLavadero();

    $lavado = Servicio::factory()->lavadero()->duracion(60)->create(['slug' => 'lavado-auto']);

    $this->from(route('taller'))
        ->post(route('taller.turnos.store'), datosDelTurno([
            'servicio' => $lavado->slug,
            'fecha' => $lunes->toDateString(),
            'hora' => '10:00',
        ]))
        ->assertSessionHasErrors('servicio');

    expect(Turno::count())->toBe(0);
});

test('the wash bay only takes one car at a time', function () {
    $lunes = abrirElLavadero(1);

    $lavado = Servicio::factory()->lavadero()->duracion(60)->create(['slug' => 'lavado-auto']);

    $reservar = fn (): TestResponse => $this->from(route('lavadero'))
        ->post(route('lavadero.turnos.store'), datosDelLavado([
            'servicio' => $lavado->slug,
            'fecha' => $lunes->toDateString(),
            'hora' => '10:30',
        ]));

    $reservar()->assertSessionHasNoErrors();
    $reservar()->assertSessionHasErrors('hora');

    expect(Turno::count())->toBe(1);
});

test('cancelling a wash gives the slot back', function () {
    $lunes = abrirElLavadero(1);

    $lavado = Servicio::factory()->lavadero()->duracion(60)->create(['slug' => 'lavado-auto']);

    $this->post(route('lavadero.turnos.store'), datosDelLavado([
        'servicio' => $lavado->slug,
        'fecha' => $lunes->toDateString(),
        'hora' => '10:30',
    ]))->assertSessionHasNoErrors();

    $turno = Turno::sole();

    expect(Puesto::huecosDelDia($lunes, $lavado))->not->toContain('10:30');

    $turno->cancelar();

    expect(Puesto::huecosDelDia($lunes, $lavado))->toContain('10:30')
        ->and($turno->fresh()->schedule_id)->toBeNull()
        ->and($turno->fresh()->estado)->toBe(EstadoTurno::Cancelado);
});

test('the customer fields are required', function (string $campo) {
    $lunes = abrirElLavadero();

    $lavado = Servicio::factory()->lavadero()->duracion(60)->create(['slug' => 'lavado-auto']);

    $datos = datosDelLavado([
        'servicio' => $lavado->slug,
        'fecha' => $lunes->toDateString(),
        'hora' => '10:00',
    ]);

    unset($datos[$campo]);

    $this->from(route('lavadero'))
        ->post(route('lavadero.turnos.store'), $datos)
        ->assertSessionHasErrors($campo);
})->with(['nombre', 'apellido', 'email', 'celular']);

test('a date outside the booking window is rejected', function (string $cuando) {
    abrirElLavadero();

    $lavado = Servicio::factory()->lavadero()->duracion(60)->create(['slug' => 'lavado-auto']);

    $this->from(route('lavadero'))
        ->post(route('lavadero.turnos.store'), datosDelLavado([
            'servicio' => $lavado->slug,
            'fecha' => $cuando,
            'hora' => '10:00',
        ]))
        ->assertSessionHasErrors('fecha');
})->with([
    'hoy' => '2026-09-14',
    'ayer' => '2026-09-13',
    'pasado el mes' => '2026-10-20',
]);

test('an hour the car wash does not offer is rejected', function (string $hora) {
    $lunes = abrirElLavadero();

    $lavado = Servicio::factory()->lavadero()->duracion(60)->create(['slug' => 'lavado-auto']);

    $this->from(route('lavadero'))
        ->post(route('lavadero.turnos.store'), datosDelLavado([
            'servicio' => $lavado->slug,
            'fecha' => $lunes->toDateString(),
            'hora' => $hora,
        ]))
        ->assertSessionHasErrors('hora');
})->with([
    'antes de abrir' => '07:00',
    'en el corte del mediodía' => '12:30',
    'una hora que no arranca ningún turno' => '10:15',
]);
