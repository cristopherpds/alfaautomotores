<?php

use App\Ai\Tools\ProponerHorarios;
use App\Ai\Tools\ReservarTurno;
use App\Enums\EstadoTurno;
use App\Enums\UserRole;
use App\Models\Ajuste;
use App\Models\Conversacion;
use App\Models\Cotizacion;
use App\Models\Servicio;
use App\Models\Turno;
use App\Models\User;
use App\Notifications\NuevaCotizacion;
use App\Notifications\NuevoTurno;
use App\Notifications\TurnoConfirmado;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Tools\Request;

/*
 * Los avisos de lo que entra desde la web o WhatsApp. Al equipo (admin y
 * vendedor) le llega cada cotización y cada turno de un cliente, en la
 * campana y por email. Al cliente, sólo el email de turno confirmado, y sólo
 * si está prendido en Ajustes › Avisos.
 */

beforeEach(function () {
    Notification::fake();
    Storage::fake('local');

    $this->admin = User::factory()->admin()->create();
    $this->vendedor = User::factory()->role(UserRole::Vendedor)->create();
    $this->equipo = User::factory()->role(UserRole::Equipo)->create();
});

/**
 * Los datos de un cliente que reserva. El nombre no puede chocar con los
 * helpers de otros archivos: Pest carga todo en un mismo proceso.
 *
 * @return array<string, mixed>
 */
function datosDelClienteQueAvisa(array $extra = []): array
{
    return [
        'nombre' => 'Ana',
        'apellido' => 'Pérez',
        'email' => 'ana@example.com',
        'celular' => '099 123 456',
        ...$extra,
    ];
}

/**
 * Un turno pendiente, reservado como lo haría la web.
 */
function turnoPendienteParaAvisar(?string $email = 'ana@example.com'): Turno
{
    $lunes = abrirElTaller(puestos: 1);

    $servicio = Servicio::factory()->duracion(90)->create(['slug' => 'service']);

    return Turno::reservar($servicio, $lunes->copy()->setTime(10, 0), [
        'nombre' => 'Ana', 'apellido' => 'Pérez', 'email' => $email, 'celular' => '099',
    ]);
}

test('a quote from the site notifies admins and sellers, not the team role', function () {
    $this->post(route('cotizacion.store'), [
        'marca' => 'Toyota',
        'modelo' => 'Corolla',
        'anio' => 2018,
        'kilometros' => '85.000',
        'nombre' => 'Ana',
        'apellido' => 'Pérez',
        'celular' => '099 123 456',
    ])->assertSessionHasNoErrors();

    $cotizacion = Cotizacion::sole();

    Notification::assertSentTo(
        [$this->admin, $this->vendedor],
        NuevaCotizacion::class,
        fn (NuevaCotizacion $aviso, array $canales) => $aviso->cotizacion->is($cotizacion)
            && $canales === ['database', 'mail'],
    );
    Notification::assertNotSentTo($this->equipo, NuevaCotizacion::class);
});

test('the bell entry of a quote carries the car but no personal data', function () {
    $cotizacion = Cotizacion::factory()->create(['marca' => 'Toyota', 'modelo' => 'Corolla', 'anio' => 2018]);

    $datos = (new NuevaCotizacion($cotizacion))->toArray($this->admin);

    expect($datos['tipo'])->toBe('cotizacion')
        ->and($datos['detalle'])->toContain('Toyota Corolla 2018')
        ->and($datos['url'])->toBe(route('panel.cotizaciones.show', $cotizacion, absolute: false))
        ->and(json_encode($datos))->not->toContain($cotizacion->apellido);
});

test('the quote email names the client for the team', function () {
    $cotizacion = Cotizacion::factory()->create(['nombre' => 'Ana', 'apellido' => 'Pérez']);

    $mail = (new NuevaCotizacion($cotizacion))->toMail($this->admin);

    expect($mail->subject)->toStartWith('Nueva cotización: ')
        ->and(implode(' ', $mail->introLines))->toContain('Ana Pérez')
        ->and($mail->actionUrl)->toBe(route('panel.cotizaciones.show', $cotizacion));
});

test('a workshop booking from the site notifies the team', function () {
    $lunes = abrirElTaller();
    $servicio = Servicio::factory()->duracion(90)->create(['slug' => 'service']);

    $this->post(route('taller.turnos.store'), datosDelClienteQueAvisa([
        'servicio' => $servicio->slug,
        'fecha' => $lunes->toDateString(),
        'hora' => '10:00',
    ]))->assertSessionHasNoErrors();

    $turno = Turno::sole();

    Notification::assertSentTo(
        [$this->admin, $this->vendedor],
        NuevoTurno::class,
        fn (NuevoTurno $aviso) => $aviso->turno->is($turno),
    );
    Notification::assertNotSentTo($this->equipo, NuevoTurno::class);
});

test('a car wash booking from the site notifies the team', function () {
    $lunes = abrirElLavadero();
    $servicio = Servicio::factory()->lavadero()->duracion(45)->create(['slug' => 'lavado-auto']);

    $this->post(route('lavadero.turnos.store'), datosDelClienteQueAvisa([
        'servicio' => $servicio->slug,
        'fecha' => $lunes->toDateString(),
        'hora' => '10:00',
    ]))->assertSessionHasNoErrors();

    $aviso = null;
    Notification::assertSentTo($this->admin, NuevoTurno::class, function (NuevoTurno $enviado) use (&$aviso) {
        $aviso = $enviado;

        return true;
    });

    expect($aviso->toArray($this->admin)['titulo'])->toBe('Nuevo turno de lavadero');
});

test('a booking made by the whatsapp agent notifies the team', function () {
    $lunes = abrirElTaller();
    Servicio::factory()->duracion(30)->create(['slug' => 'service']);
    $conversacion = Conversacion::factory()->create(['wa_id' => '59899123456']);

    $ofrecidos = json_decode((string) (new ProponerHorarios($conversacion))->handle(new Request([
        'servicio' => 'service',
        'fecha' => $lunes->format('Y-m-d'),
    ])), true);

    (new ReservarTurno($conversacion))->handle(new Request([
        'servicio' => 'service',
        'inicio' => $ofrecidos['horarios'][0]['inicio'],
        'confirmacion' => 'dale',
        'nombre' => 'Ana',
        'apellido' => 'Pérez',
    ]));

    Notification::assertSentTo($this->vendedor, NuevoTurno::class);
});

test('a booking loaded from the panel does not notify the team', function () {
    abrirElTaller();
    $servicio = Servicio::factory()->duracion(90)->create(['slug' => 'service']);

    $this->actingAs($this->vendedor)
        ->post(route('panel.turnos.store'), datosDelClienteQueAvisa([
            'servicio' => $servicio->slug,
            'fecha' => '2026-09-14',
            'hora' => '14:00',
        ]))
        ->assertSessionHasNoErrors();

    Notification::assertNotSentTo([$this->admin, $this->vendedor], NuevoTurno::class);
});

test('confirming a turn emails the client when the notice is on', function () {
    Ajuste::guardar(Ajuste::AVISOS, ['confirmacion_turno' => true]);
    $turno = turnoPendienteParaAvisar();

    $this->actingAs($this->admin)
        ->patch(route('panel.turnos.estado', $turno), ['estado' => EstadoTurno::Confirmado->value])
        ->assertSessionHasNoErrors();

    Notification::assertSentOnDemand(
        TurnoConfirmado::class,
        fn (TurnoConfirmado $aviso, array $canales, AnonymousNotifiable $destino) => $aviso->turno->is($turno)
            && array_key_first($destino->routes['mail']) === 'ana@example.com',
    );
});

test('confirming a turn sends nothing while the notice is off', function () {
    $turno = turnoPendienteParaAvisar();

    $this->actingAs($this->admin)
        ->patch(route('panel.turnos.estado', $turno), ['estado' => EstadoTurno::Confirmado->value]);

    Notification::assertNotSentTo(new AnonymousNotifiable, TurnoConfirmado::class);
    Notification::assertNothingSentTo(new AnonymousNotifiable);
});

test('a turn without email is confirmed without an email', function () {
    Ajuste::guardar(Ajuste::AVISOS, ['confirmacion_turno' => true]);
    $turno = turnoPendienteParaAvisar(email: null);

    $this->actingAs($this->admin)
        ->patch(route('panel.turnos.estado', $turno), ['estado' => EstadoTurno::Confirmado->value])
        ->assertSessionHasNoErrors();

    Notification::assertNothingSentTo(new AnonymousNotifiable);
});

test('other state changes and re-confirming do not email the client', function () {
    Ajuste::guardar(Ajuste::AVISOS, ['confirmacion_turno' => true]);
    $turno = turnoPendienteParaAvisar();
    $turno->update(['estado' => EstadoTurno::Confirmado]);

    $this->actingAs($this->admin)
        ->patch(route('panel.turnos.estado', $turno), ['estado' => EstadoTurno::Confirmado->value]);
    $this->actingAs($this->admin)
        ->patch(route('panel.turnos.estado', $turno), ['estado' => EstadoTurno::Completado->value]);

    Notification::assertNothingSentTo(new AnonymousNotifiable);
});

test('a turn loaded from the panel is already confirmed and emails the client', function () {
    Ajuste::guardar(Ajuste::AVISOS, ['confirmacion_turno' => true]);
    abrirElTaller();
    $servicio = Servicio::factory()->duracion(90)->create(['slug' => 'service']);

    $this->actingAs($this->vendedor)
        ->post(route('panel.turnos.store'), datosDelClienteQueAvisa([
            'servicio' => $servicio->slug,
            'fecha' => '2026-09-14',
            'hora' => '14:00',
        ]))
        ->assertSessionHasNoErrors();

    Notification::assertSentOnDemand(TurnoConfirmado::class);
});

test('the confirmation email says what, when and where', function () {
    $turno = turnoPendienteParaAvisar();

    $mail = (new TurnoConfirmado($turno))->toMail(new AnonymousNotifiable);

    expect($mail->subject)->toStartWith('Tu turno está confirmado')
        ->and($mail->greeting)->toBe('¡Hola, Ana!')
        ->and(implode(' ', $mail->introLines))
        ->toContain($turno->servicio->nombre)
        ->toContain('Lunes 21 de setiembre a las 10:00')
        ->toContain(config('alfa.direccion'))
        ->and($mail->actionUrl)->toStartWith('https://wa.me/');
});
