<?php

use App\Enums\EstadoTurno;
use App\Enums\UserRole;
use App\Models\Cliente;
use App\Models\Servicio;
use App\Models\Turno;
use App\Models\User;

/**
 * Una reserva pública del taller, con los datos que se le pasen encima.
 *
 * El nombre no puede chocar con los helpers de otros archivos: Pest carga
 * todo en el mismo proceso.
 *
 * @param  array<string, mixed>  $datos
 */
function reservarComoCliente(Servicio $servicio, string $hora, array $datos = []): void
{
    test()->post(route('taller.turnos.store'), [
        'servicio' => $servicio->slug,
        'fecha' => elLunes()->toDateString(),
        'hora' => $hora,
        'nombre' => 'Ana',
        'apellido' => 'Pérez',
        'email' => 'ana@example.com',
        'celular' => '099 123 456',
        ...$datos,
    ])->assertSessionHasNoErrors();
}

test('a public booking creates the client and links the booking', function () {
    abrirElTaller();
    $servicio = Servicio::factory()->duracion(30)->create(['slug' => 'service']);

    reservarComoCliente($servicio, '10:00', ['matricula' => 'sbc 1234']);

    $cliente = Cliente::sole();

    expect($cliente)
        ->nombre->toBe('Ana')
        ->celular_normalizado->toBe('099123456')
        ->acepta_novedades->toBeFalse()
        ->and(Turno::sole()->cliente_id)->toBe($cliente->id);
});

test('booking again with the same phone written differently reuses the client', function () {
    abrirElTaller();
    $servicio = Servicio::factory()->duracion(30)->create(['slug' => 'service']);

    reservarComoCliente($servicio, '10:00');
    reservarComoCliente($servicio, '14:00', ['celular' => '099-123-456', 'email' => 'Ana.Nueva@Example.com']);

    expect(Cliente::count())->toBe(1)
        ->and(Cliente::sole()->email)->toBe('ana.nueva@example.com')
        ->and(Cliente::sole()->turnos)->toHaveCount(2);
});

test('a new phone with a known email is still the same client', function () {
    abrirElTaller();
    $servicio = Servicio::factory()->duracion(30)->create(['slug' => 'service']);

    reservarComoCliente($servicio, '10:00');
    reservarComoCliente($servicio, '14:00', ['celular' => '098 000 111']);

    expect(Cliente::count())->toBe(1)
        ->and(Cliente::sole()->celular_normalizado)->toBe('098000111');
});

test('ticking the box gives consent with a date and a later booking never takes it away', function () {
    abrirElTaller();
    $servicio = Servicio::factory()->duracion(30)->create(['slug' => 'service']);

    reservarComoCliente($servicio, '10:00', ['acepta_novedades' => '1']);

    $cliente = Cliente::sole();
    expect($cliente->acepta_novedades)->toBeTrue()
        ->and($cliente->acepta_novedades_at)->not->toBeNull();

    reservarComoCliente($servicio, '14:00');

    expect($cliente->refresh()->acepta_novedades)->toBeTrue();
});

test('a booking from the panel links the client too, with the consent box', function () {
    abrirElTaller();
    $servicio = Servicio::factory()->duracion(30)->create(['slug' => 'service']);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('panel.turnos.store'), [
            'servicio' => $servicio->slug,
            'fecha' => elLunes()->toDateString(),
            'hora' => '10:00',
            'nombre' => 'Juan',
            'apellido' => 'Díaz',
            'email' => 'juan@example.com',
            'celular' => '098 555 111',
            'acepta_novedades' => '1',
        ])
        ->assertSessionHasNoErrors();

    expect(Turno::sole()->ficha)
        ->nombreCompleto()->toBe('Juan Díaz')
        ->acepta_novedades->toBeTrue();
});

test('taking consent away from the panel clears its date', function () {
    $cliente = Cliente::factory()->aceptaNovedades()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('panel.clientes.update', $cliente), [
            ...$cliente->only(['nombre', 'apellido', 'email', 'celular', 'notas']),
            'acepta_novedades' => false,
        ])
        ->assertSessionHasNoErrors();

    expect($cliente->refresh())
        ->acepta_novedades->toBeFalse()
        ->acepta_novedades_at->toBeNull();
});

test('the list searches by name, phone, email and plate', function (string $busqueda) {
    $ana = Cliente::factory()->create(['nombre' => 'Ana', 'apellido' => 'Pérez', 'celular' => '099 123 456', 'email' => 'ana@example.com']);
    Turno::factory()->for($ana, 'ficha')->create(['matricula' => 'SBC1234']);
    Cliente::factory()->create(['nombre' => 'Juan', 'apellido' => 'Díaz']);

    $this->actingAs(User::factory()->create())
        ->get(route('panel.clientes.index', ['busqueda' => $busqueda]))
        ->assertInertia(fn ($page) => $page
            ->component('panel/clientes/index')
            ->has('clientes.data', 1)
            ->where('clientes.data.0.nombre', 'Ana Pérez')
        );
})->with([
    'nombre' => 'ana pé',
    'celular' => '123456',
    'email' => 'ana@exa',
    'matrícula' => 'sbc12',
]);

test('the list filters by business and by consent', function () {
    $taller = Servicio::factory()->create();
    $lavadero = Servicio::factory()->lavadero()->create();

    $delTaller = Cliente::factory()->aceptaNovedades()->create();
    Turno::factory()->for($delTaller, 'ficha')->for($taller)->create();
    $delLavadero = Cliente::factory()->create();
    Turno::factory()->for($delLavadero, 'ficha')->for($lavadero)->create();

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('panel.clientes.index', ['rubro' => 'lavadero']))
        ->assertInertia(fn ($page) => $page
            ->has('clientes.data', 1)
            ->where('clientes.data.0.id', $delLavadero->id)
        );

    $this->actingAs($admin)
        ->get(route('panel.clientes.index', ['novedades' => '1']))
        ->assertInertia(fn ($page) => $page
            ->has('clientes.data', 1)
            ->where('clientes.data.0.id', $delTaller->id)
        );
});

test('the file shows vehicles and booking history', function () {
    $cliente = Cliente::factory()->create();
    $servicio = Servicio::factory()->create(['nombre' => 'Service completo']);
    Turno::factory()->for($cliente, 'ficha')->for($servicio)->inicia(now()->subDays(10)->setTime(9, 0))
        ->create(['vehiculo_marca' => 'Fiat', 'vehiculo_modelo' => 'Cronos', 'vehiculo_anio' => 2019, 'matricula' => 'sbc1234']);
    Turno::factory()->for($cliente, 'ficha')->for($servicio)->inicia(now()->addDay()->setTime(9, 0))
        ->create(['vehiculo_marca' => 'Fiat', 'vehiculo_modelo' => 'Cronos', 'vehiculo_anio' => 2019, 'matricula' => 'SBC1234']);

    $this->actingAs(User::factory()->role(UserRole::Equipo)->create())
        ->get(route('panel.clientes.show', $cliente))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('panel/clientes/show')
            // La misma matrícula en dos turnos es un solo vehículo.
            ->has('vehiculos', 1)
            ->where('vehiculos.0.matricula', 'SBC1234')
            ->has('turnos', 2)
            ->where('turnos.0.servicio', 'Service completo')
            ->where('can.update', false)
        );
});

test('a lead can be added by hand, and the phone cannot repeat', function () {
    Cliente::factory()->create(['celular' => '099 123 456']);
    $vendedor = User::factory()->role(UserRole::Vendedor)->create();

    $this->actingAs($vendedor)
        ->post(route('panel.clientes.store'), [
            'nombre' => 'Lucía', 'apellido' => 'Gómez', 'celular' => '098 777 888',
            'acepta_novedades' => true, 'notas' => 'Preguntó por el lavado premium.',
        ])
        ->assertSessionHasNoErrors();

    expect(Cliente::firstWhere('nombre', 'Lucía'))->acepta_novedades->toBeTrue();

    $this->actingAs($vendedor)
        ->from(route('panel.clientes.create'))
        ->post(route('panel.clientes.store'), [
            'nombre' => 'Otra', 'apellido' => 'Persona', 'celular' => '099-123-456',
        ])
        ->assertSessionHasErrors('celular');
});

test('the team role can look but not edit or export', function () {
    $cliente = Cliente::factory()->create();
    $equipo = User::factory()->role(UserRole::Equipo)->create();

    $this->actingAs($equipo)->get(route('panel.clientes.index'))->assertOk();
    $this->actingAs($equipo)->put(route('panel.clientes.update', $cliente), [
        ...$cliente->only(['nombre', 'apellido', 'celular']), 'notas' => 'x',
    ])->assertForbidden();
    $this->actingAs($equipo)->get(route('panel.clientes.exportar'))->assertForbidden();
    $this->actingAs($equipo)->post(route('panel.clientes.store'), [])->assertForbidden();
});

test('only an admin deletes a client, and their bookings survive', function () {
    $cliente = Cliente::factory()->create();
    $turno = Turno::factory()->for($cliente, 'ficha')->estado(EstadoTurno::Completado)->create(['nombre' => 'Ana']);

    $this->actingAs(User::factory()->role(UserRole::Vendedor)->create())
        ->delete(route('panel.clientes.destroy', $cliente))
        ->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('panel.clientes.destroy', $cliente))
        ->assertRedirect(route('panel.clientes.index'));

    expect(Cliente::find($cliente->id))->toBeNull()
        ->and($turno->refresh())
        ->cliente_id->toBeNull()
        ->nombre->toBe('Ana');
});

test('the CSV has a header, a BOM and only the filtered clients', function () {
    Cliente::factory()->aceptaNovedades()->create(['nombre' => 'Ana', 'apellido' => 'Pérez']);
    Cliente::factory()->create(['nombre' => 'Juan', 'apellido' => 'Díaz']);

    $respuesta = $this->actingAs(User::factory()->admin()->create())
        ->get(route('panel.clientes.exportar', ['novedades' => '1']))
        ->assertOk()
        ->assertDownload('clientes-'.now()->format('Y-m-d').'.csv');

    $contenido = $respuesta->streamedContent();
    $lineas = array_values(array_filter(explode("\n", $contenido)));

    expect($contenido)->toStartWith("\u{FEFF}")
        ->and($lineas)->toHaveCount(2)
        ->and($lineas[0])->toContain('Nombre;Apellido;Celular')
        ->and($lineas[1])->toContain('Ana;Pérez')
        ->and($contenido)->not->toContain('Juan');
});

test('the list puts the most recently active clients first', function () {
    $viejo = Cliente::factory()->create();
    Turno::factory()->for($viejo, 'ficha')->create(['created_at' => now()->subMonth()]);
    $reciente = Cliente::factory()->create();
    Turno::factory()->for($reciente, 'ficha')->create(['created_at' => now()->subDay()]);
    // Un lead que nunca reservó cuenta desde su alta.
    $lead = Cliente::factory()->create(['created_at' => now()->subWeek()]);

    $this->actingAs(User::factory()->create())
        ->get(route('panel.clientes.index'))
        ->assertInertia(fn ($page) => $page
            ->where('clientes.data.0.id', $reciente->id)
            ->where('clientes.data.1.id', $lead->id)
            ->where('clientes.data.2.id', $viejo->id)
        );
});
