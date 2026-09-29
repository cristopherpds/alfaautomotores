<?php

use App\Enums\EstadoTurno;
use App\Enums\EstadoVehiculo;
use App\Enums\UserRole;
use App\Models\Producto;
use App\Models\ProductoImagen;
use App\Models\Servicio;
use App\Models\Turno;
use App\Models\User;
use App\Models\Vehiculo;
use App\Models\VehiculoImagen;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/* Un miércoles a la mañana fijo: los turnos «de hoy» y la semana no pueden
   depender de la hora a la que corre el test. */
beforeEach(function () {
    Storage::fake('public');
    $this->travelTo(Carbon::parse('2026-09-30 08:00'));
});

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('the stock is counted by state, with the featured cap', function () {
    Vehiculo::factory()->count(3)->publicado()->has(VehiculoImagen::factory(), 'fotos')->create();
    Vehiculo::factory()->reservado()->has(VehiculoImagen::factory(), 'fotos')->create();
    Vehiculo::factory()->borrador()->create();
    Vehiculo::factory()->publicado()->destacado()->has(VehiculoImagen::factory(), 'fotos')->create();

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('stock.publicados', 4)
            ->where('stock.reservados', 1)
            ->where('stock.borradores', 1)
            ->where('stock.destacados', 1)
            ->where('stock.maxDestacados', Vehiculo::MAX_DESTACADOS)
        );
});

test('cars that need attention are listed oldest first', function () {
    // Publicado sin fotos: sale con «Foto pendiente».
    $sinFotos = Vehiculo::factory()->publicado()->create();
    // Borrador de hace diez días.
    $olvidado = Vehiculo::factory()->borrador()->create(['created_at' => now()->subDays(10)]);
    // Publicado hace tres meses, con fotos: no rota.
    $estancado = Vehiculo::factory()->publicado()->has(VehiculoImagen::factory(), 'fotos')
        ->create(['created_at' => now()->subDays(90)]);
    // Estos no piden nada: borrador de ayer y publicado reciente con fotos.
    Vehiculo::factory()->borrador()->create(['created_at' => now()->subDay()]);
    Vehiculo::factory()->publicado()->has(VehiculoImagen::factory(), 'fotos')->create();

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('revisar.sinFotos.total', 1)
            ->where('revisar.sinFotos.lista.0.id', $sinFotos->id)
            ->where('revisar.borradoresViejos.total', 1)
            ->where('revisar.borradoresViejos.lista.0.id', $olvidado->id)
            ->where('revisar.borradoresViejos.lista.0.dias', 10)
            ->where('revisar.estancados.total', 1)
            ->where('revisar.estancados.lista.0.id', $estancado->id)
        );
});

test('sales of the month count cars by the date they were sold', function () {
    // Vendido este mes, desde el panel.
    $vehiculo = Vehiculo::factory()->reservado()->create();
    $this->actingAs(User::factory()->role(UserRole::Vendedor)->create())
        ->put(route('panel.vehiculos.update', $vehiculo), [
            ...$vehiculo->only(['slug', 'marca', 'modelo', 'version', 'anio', 'km', 'precio', 'desc']),
            'moneda' => $vehiculo->moneda->value,
            'comb' => $vehiculo->comb->value,
            'trans' => $vehiculo->trans->value,
            'tipo' => $vehiculo->tipo->value,
            'estado' => 'vendido',
        ])
        ->assertSessionHasNoErrors();

    // Vendido el mes pasado: no cuenta.
    $this->travelTo(now()->subMonth());
    Vehiculo::factory()->vendido()->create();
    $this->travelBack();
    $this->travelTo(Carbon::parse('2026-09-30 08:00'));

    $this->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('stock.vendidosMes', 1));
});

test('the sale date is set when a car is sold and cleared if it goes back', function () {
    $vehiculo = Vehiculo::factory()->reservado()->create();
    expect($vehiculo->vendido_at)->toBeNull();

    $vehiculo->update(['estado' => EstadoVehiculo::Vendido]);
    expect($vehiculo->vendido_at?->toDateTimeString())->toBe('2026-09-30 08:00:00');

    // Guardar otra cosa no mueve la fecha de venta.
    $this->travelTo(now()->addDays(3));
    $vehiculo->update(['precio' => 1]);
    expect($vehiculo->vendido_at?->toDateTimeString())->toBe('2026-09-30 08:00:00');

    // Si la venta se cae, la fecha se va; si se vuelve a vender, es la nueva.
    $vehiculo->update(['estado' => EstadoVehiculo::Reservado]);
    expect($vehiculo->vendido_at)->toBeNull();

    $vehiculo->update(['estado' => EstadoVehiculo::Vendido]);
    expect($vehiculo->vendido_at?->toDateString())->toBe('2026-10-03');
});

test('the sale date cannot be sent from a form', function () {
    $vehiculo = Vehiculo::factory()->reservado()->create();

    $vehiculo->fill(['vendido_at' => '2020-01-01'])->save();

    expect($vehiculo->refresh()->vendido_at)->toBeNull();
});

test("today's bookings are split by business and cancelled ones are left out", function () {
    $taller = Servicio::factory()->create(['nombre' => 'Service completo']);
    $lavado = Servicio::factory()->lavadero()->create(['nombre' => 'Lavado']);

    Turno::factory()->for($taller)->inicia(now()->setTime(10, 0))->create(['nombre' => 'Ana', 'apellido' => 'Pérez']);
    Turno::factory()->for($lavado)->inicia(now()->setTime(11, 30))->create();
    Turno::factory()->for($taller)->inicia(now()->setTime(12, 0))->estado(EstadoTurno::Cancelado)->create();
    // Mañana: no es de hoy.
    Turno::factory()->for($taller)->inicia(now()->addDay()->setTime(9, 0))->create();

    $this->actingAs(User::factory()->role(UserRole::Equipo)->create())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('hoy.taller', 1)
            ->where('hoy.taller.0.hora', '10:00')
            ->where('hoy.taller.0.cliente', 'Ana Pérez')
            ->where('hoy.taller.0.servicio', 'Service completo')
            ->has('hoy.lavadero', 1)
        );
});

test('pending bookings are listed by how long they have been waiting', function () {
    $servicio = Servicio::factory()->create();

    $reciente = Turno::factory()->for($servicio)->inicia(now()->addDays(2)->setTime(9, 0))
        ->create(['created_at' => now()->subHour()]);
    $antiguo = Turno::factory()->for($servicio)->inicia(now()->addDays(3)->setTime(9, 0))
        ->create(['created_at' => now()->subDays(2)]);
    // Confirmado y ya pasado: ninguno de los dos cuenta.
    Turno::factory()->for($servicio)->inicia(now()->addDay()->setTime(9, 0))->estado(EstadoTurno::Confirmado)->create();
    Turno::factory()->for($servicio)->inicia(now()->subDays(2)->setTime(9, 0))->create();

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('pendientes.total', 2)
            ->where('pendientes.lista.0.id', $antiguo->id)
            ->where('pendientes.lista.1.id', $reciente->id)
        );
});

test('the week shows seven days and how bookings came in', function () {
    $taller = Servicio::factory()->create();

    Turno::factory()->for($taller)->inicia(now()->addDays(2)->setTime(9, 0))->create();
    Turno::factory()->for($taller)->inicia(now()->addDays(2)->setTime(11, 0))->delPanel()->create();
    Turno::factory()->for($taller)->inicia(now()->addDays(3)->setTime(9, 0))->estado(EstadoTurno::Cancelado)->create();

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('semana.dias', 7)
            ->where('semana.dias.0.fecha', '2026-09-30')
            ->where('semana.dias.2.taller', 2)
            ->where('semana.dias.3.taller', 0)
            ->where('semana.cancelados', 1)
            ->where('semana.origen.web', 2)
            ->where('semana.origen.panel', 1)
        );
});

test('the mobility catalogue is summed up by section', function () {
    Producto::factory()->count(2)->has(ProductoImagen::factory(), 'fotos')->create();
    Producto::factory()->sinStock()->has(ProductoImagen::factory(), 'fotos')->create();
    Producto::factory()->porEncargue()->create();
    Producto::factory()->bicicleta()->has(ProductoImagen::factory(), 'fotos')->create();

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('catalogo.movilidad.publicados', 2)
            ->where('catalogo.movilidad.sinStock', 1)
            ->where('catalogo.movilidad.porEncargue', 1)
            ->where('catalogo.movilidad.sinFotos', 1)
            ->where('catalogo.bicicletas.publicados', 1)
            ->where('catalogo.bicicletas.sinFotos', 0)
        );
});

test('only admins get the recent activity', function (UserRole $role, bool $ve) {
    Vehiculo::factory()->create();

    $this->actingAs(User::factory()->role($role)->create())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $ve
            ? $page->has('actividad')->whereNot('actividad', null)
            : $page->where('actividad', null)
        );
})->with([
    'admin' => [UserRole::Admin, true],
    'vendedor' => [UserRole::Vendedor, false],
    'equipo' => [UserRole::Equipo, false],
]);
