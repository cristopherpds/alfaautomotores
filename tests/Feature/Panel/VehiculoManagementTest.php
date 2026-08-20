<?php

use App\Enums\EstadoVehiculo;
use App\Enums\TipoVehiculo;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\Vehiculo;

test('the stock list is open to everyone on the team', function (UserRole $role) {
    Vehiculo::factory()->create();
    Vehiculo::factory()->borrador()->create();

    $this->actingAs(User::factory()->role($role)->create())
        ->get(route('panel.vehiculos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('panel/vehiculos/index')
            // Los borradores sí se ven en el panel.
            ->has('vehiculos', 2)
            ->where('maxDestacados', Vehiculo::MAX_DESTACADOS)
        );
})->with([
    'admin' => UserRole::Admin,
    'vendedor' => UserRole::Vendedor,
    'equipo' => UserRole::Equipo,
]);

test('the list says whether the viewer can manage the stock', function (UserRole $role, bool $gestiona) {
    Vehiculo::factory()->create();

    $this->actingAs(User::factory()->role($role)->create())
        ->get(route('panel.vehiculos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('puedeCrear', $gestiona)
            ->where('vehiculos.0.can.update', $gestiona)
            ->where('vehiculos.0.can.delete', $gestiona)
        );
})->with([
    'admin' => [UserRole::Admin, true],
    'vendedor' => [UserRole::Vendedor, true],
    'equipo' => [UserRole::Equipo, false],
]);

test('guests are redirected to the login page', function () {
    $this->get(route('panel.vehiculos.index'))->assertRedirect(route('login'));
});

test('sellers and admins can add a vehicle', function (UserRole $role) {
    $this->actingAs(User::factory()->role($role)->create())
        ->post(route('panel.vehiculos.store'), datosDeVehiculo())
        ->assertSessionHasNoErrors();

    $vehiculo = Vehiculo::where('slug', 'strada-freedom-24')->firstOrFail();

    expect($vehiculo->marca)->toBe('Fiat')
        ->and($vehiculo->estado)->toBe(EstadoVehiculo::Publicado)
        ->and($vehiculo->destacado)->toBeFalse();
})->with([
    'admin' => UserRole::Admin,
    'vendedor' => UserRole::Vendedor,
]);

test('creating a vehicle lands on its edit page so photos can be added', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('panel.vehiculos.store'), datosDeVehiculo())
        ->assertRedirect(route('panel.vehiculos.edit', Vehiculo::firstOrFail()));
});

test('the team role cannot add a vehicle', function () {
    $this->actingAs(User::factory()->role(UserRole::Equipo)->create())
        ->post(route('panel.vehiculos.store'), datosDeVehiculo())
        ->assertForbidden();

    expect(Vehiculo::count())->toBe(0);
});

test('the slug cannot be taken by another vehicle', function () {
    Vehiculo::factory()->create(['slug' => 'strada-freedom-24']);

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('panel.vehiculos.create'))
        ->post(route('panel.vehiculos.store'), datosDeVehiculo())
        ->assertSessionHasErrors('slug');

    expect(Vehiculo::count())->toBe(1);
});

test('the slug is stored in lower case', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('panel.vehiculos.store'), datosDeVehiculo([
            'slug' => '  Strada-Freedom-24  ',
        ]))
        ->assertSessionHasNoErrors();

    expect(Vehiculo::sole()->slug)->toBe('strada-freedom-24');
});

test('the slug is taken even when it only differs in case', function () {
    Vehiculo::factory()->create(['slug' => 'strada-freedom-24']);

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('panel.vehiculos.create'))
        ->post(route('panel.vehiculos.store'), datosDeVehiculo([
            'slug' => 'Strada-Freedom-24',
        ]))
        ->assertSessionHasErrors('slug');

    expect(Vehiculo::count())->toBe(1);
});

test('a vehicle keeps its own slug when edited', function () {
    $vehiculo = Vehiculo::factory()->create(['slug' => 'strada-freedom-24']);

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('panel.vehiculos.update', $vehiculo), datosDeVehiculo(['km' => 25000]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('panel.vehiculos.index'));

    expect($vehiculo->refresh()->km)->toBe(25000);
});

test('the state has to be one of the supported ones', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->from(route('panel.vehiculos.create'))
        ->post(route('panel.vehiculos.store'), datosDeVehiculo(['estado' => 'en-camino']))
        ->assertSessionHasErrors('estado');
});

test('the body type has to be one of the supported ones', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->from(route('panel.vehiculos.create'))
        ->post(route('panel.vehiculos.store'), datosDeVehiculo(['tipo' => 'Monoplaza']))
        ->assertSessionHasErrors('tipo');
});

test('a van or minibus can be filed as Utilitario', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('panel.vehiculos.store'), datosDeVehiculo([
            'slug' => 'express-97',
            'marca' => 'Renault',
            'modelo' => 'Express',
            'tipo' => 'Utilitario',
        ]))
        ->assertSessionHasNoErrors();

    expect(Vehiculo::firstWhere('slug', 'express-97')->tipo)
        ->toBe(TipoVehiculo::Utilitario);
});

test('sellers and admins can delete a vehicle', function () {
    $vehiculo = Vehiculo::factory()->create();

    $this->actingAs(User::factory()->role(UserRole::Vendedor)->create())
        ->delete(route('panel.vehiculos.destroy', $vehiculo))
        ->assertRedirect(route('panel.vehiculos.index'));

    expect(Vehiculo::find($vehiculo->id))->toBeNull();
});

test('the team role cannot delete a vehicle', function () {
    $vehiculo = Vehiculo::factory()->create();

    $this->actingAs(User::factory()->role(UserRole::Equipo)->create())
        ->delete(route('panel.vehiculos.destroy', $vehiculo))
        ->assertForbidden();

    expect(Vehiculo::find($vehiculo->id))->not->toBeNull();
});

/**
 * Una ficha válida, para no repetirla en cada test.
 *
 * @param  array<string, mixed>  $reemplazos
 * @return array<string, mixed>
 */
function datosDeVehiculo(array $reemplazos = []): array
{
    return [
        'slug' => 'strada-freedom-24',
        'marca' => 'Fiat',
        'modelo' => 'Strada',
        'version' => 'Freedom',
        'anio' => 2024,
        'km' => 18000,
        'precio' => 23900,
        'moneda' => 'USD',
        'comb' => 'Nafta',
        'trans' => 'Manual',
        'tipo' => 'Pick-up',
        'estado' => 'publicado',
        'desc' => 'Cabina doble, único dueño, service oficial al día.',
        ...$reemplazos,
    ];
}
