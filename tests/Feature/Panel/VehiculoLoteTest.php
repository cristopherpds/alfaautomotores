<?php

use App\Enums\EstadoVehiculo;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\Vehiculo;
use App\Models\VehiculoImagen;
use Illuminate\Support\Facades\Storage;

test('sellers and admins can publish several vehicles at once', function (UserRole $role) {
    $vehiculos = Vehiculo::factory()->borrador()->count(3)->create();

    $this->actingAs(User::factory()->role($role)->create())
        ->from(route('panel.vehiculos.index'))
        ->patch(route('panel.vehiculos.lote.estado'), [
            'vehiculos' => $vehiculos->pluck('id')->all(),
            'estado' => 'publicado',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('panel.vehiculos.index'));

    expect(Vehiculo::pluck('estado')->all())
        ->each->toBe(EstadoVehiculo::Publicado);
})->with([
    'admin' => UserRole::Admin,
    'vendedor' => UserRole::Vendedor,
]);

test('only the selected vehicles change state', function () {
    $elegidos = Vehiculo::factory()->borrador()->count(2)->create();
    $intacto = Vehiculo::factory()->borrador()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->patch(route('panel.vehiculos.lote.estado'), [
            'vehiculos' => $elegidos->pluck('id')->all(),
            'estado' => 'publicado',
        ])
        ->assertSessionHasNoErrors();

    expect($intacto->refresh()->estado)->toBe(EstadoVehiculo::Borrador);
});

/*
 * Es la razón por la que el lote recorre modelos en vez de hacer una sola
 * query: un `whereIn(...)->update(...)` no dispara el hook `saving` y dejaría
 * borradores ocupando lugares de la portada.
 */
test('moving vehicles to draft in bulk unpins them from the landing page', function () {
    $vehiculos = Vehiculo::factory()->destacado()->count(2)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->patch(route('panel.vehiculos.lote.estado'), [
            'vehiculos' => $vehiculos->pluck('id')->all(),
            'estado' => 'borrador',
        ])
        ->assertSessionHasNoErrors();

    expect(Vehiculo::pluck('destacado')->all())->each->toBeFalse()
        ->and(Vehiculo::contarDestacados())->toBe(0);
});

test('sellers and admins can delete several vehicles at once', function (UserRole $role) {
    $vehiculos = Vehiculo::factory()->count(3)->create();

    $this->actingAs(User::factory()->role($role)->create())
        ->from(route('panel.vehiculos.index'))
        ->delete(route('panel.vehiculos.lote.destroy'), [
            'vehiculos' => $vehiculos->take(2)->pluck('id')->all(),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('panel.vehiculos.index'));

    expect(Vehiculo::count())->toBe(1);
})->with([
    'admin' => UserRole::Admin,
    'vendedor' => UserRole::Vendedor,
]);

/* La otra mitad de lo mismo: el hook `deleting` es el que borra los archivos. */
test('deleting in bulk takes the photo folders with it', function () {
    Storage::fake('public');

    $vehiculos = Vehiculo::factory()->count(2)->create();

    $vehiculos->each(function (Vehiculo $vehiculo): void {
        VehiculoImagen::factory()->create([
            'vehiculo_id' => $vehiculo->id,
            'ruta' => $vehiculo->carpetaDeFotos().'/portada.jpg',
        ]);

        Storage::disk('public')->put($vehiculo->carpetaDeFotos().'/portada.jpg', 'foto');
    });

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('panel.vehiculos.lote.destroy'), [
            'vehiculos' => $vehiculos->pluck('id')->all(),
        ])
        ->assertSessionHasNoErrors();

    expect(VehiculoImagen::count())->toBe(0);

    foreach ($vehiculos as $vehiculo) {
        expect(Storage::disk('public')->exists($vehiculo->carpetaDeFotos()))->toBeFalse();
    }
});

test('the team role cannot run bulk actions', function () {
    $vehiculos = Vehiculo::factory()->borrador()->count(2)->create();
    $equipo = User::factory()->role(UserRole::Equipo)->create();

    $this->actingAs($equipo)
        ->patch(route('panel.vehiculos.lote.estado'), [
            'vehiculos' => $vehiculos->pluck('id')->all(),
            'estado' => 'publicado',
        ])
        ->assertForbidden();

    $this->actingAs($equipo)
        ->delete(route('panel.vehiculos.lote.destroy'), [
            'vehiculos' => $vehiculos->pluck('id')->all(),
        ])
        ->assertForbidden();

    expect(Vehiculo::count())->toBe(2)
        ->and(Vehiculo::pluck('estado')->all())->each->toBe(EstadoVehiculo::Borrador);
});

test('guests are redirected to the login page', function () {
    $vehiculo = Vehiculo::factory()->create();

    $this->patch(route('panel.vehiculos.lote.estado'), [
        'vehiculos' => [$vehiculo->id],
        'estado' => 'publicado',
    ])->assertRedirect(route('login'));
});

test('the state has to be one of the supported ones', function () {
    $vehiculo = Vehiculo::factory()->borrador()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('panel.vehiculos.index'))
        ->patch(route('panel.vehiculos.lote.estado'), [
            'vehiculos' => [$vehiculo->id],
            'estado' => 'en-camino',
        ])
        ->assertSessionHasErrors('estado');

    expect($vehiculo->refresh()->estado)->toBe(EstadoVehiculo::Borrador);
});

test('an unknown vehicle stops the whole batch', function () {
    $vehiculo = Vehiculo::factory()->borrador()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('panel.vehiculos.index'))
        ->patch(route('panel.vehiculos.lote.estado'), [
            'vehiculos' => [$vehiculo->id, $vehiculo->id + 999],
            'estado' => 'publicado',
        ])
        ->assertSessionHasErrors('vehiculos.1');

    expect($vehiculo->refresh()->estado)->toBe(EstadoVehiculo::Borrador);
});

test('the selection cannot be empty', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->from(route('panel.vehiculos.index'))
        ->patch(route('panel.vehiculos.lote.estado'), [
            'vehiculos' => [],
            'estado' => 'publicado',
        ])
        ->assertSessionHasErrors('vehiculos');
});
