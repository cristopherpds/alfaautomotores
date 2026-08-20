<?php

use App\Enums\EstadoVehiculo;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\Vehiculo;

test('a seller can pin a vehicle to the landing page', function () {
    $vehiculo = Vehiculo::factory()->create();

    $this->actingAs(User::factory()->role(UserRole::Vendedor)->create())
        ->from(route('panel.vehiculos.index'))
        ->patch(route('panel.vehiculos.destacado', $vehiculo), ['destacado' => true])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('panel.vehiculos.index'));

    expect($vehiculo->refresh()->destacado)->toBeTrue();
});

test('a pinned vehicle can be unpinned', function () {
    $vehiculo = Vehiculo::factory()->destacado()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->patch(route('panel.vehiculos.destacado', $vehiculo), ['destacado' => false])
        ->assertSessionHasNoErrors();

    expect($vehiculo->refresh()->destacado)->toBeFalse();
});

test('the team role cannot pin a vehicle', function () {
    $vehiculo = Vehiculo::factory()->create();

    $this->actingAs(User::factory()->role(UserRole::Equipo)->create())
        ->patch(route('panel.vehiculos.destacado', $vehiculo), ['destacado' => true])
        ->assertForbidden();

    expect($vehiculo->refresh()->destacado)->toBeFalse();
});

test('the landing page only holds so many pinned vehicles', function () {
    Vehiculo::factory()->count(Vehiculo::MAX_DESTACADOS)->destacado()->create();
    $sobrante = Vehiculo::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('panel.vehiculos.index'))
        ->patch(route('panel.vehiculos.destacado', $sobrante), ['destacado' => true])
        ->assertSessionHasErrors('destacado');

    expect($sobrante->refresh()->destacado)->toBeFalse()
        ->and(Vehiculo::contarDestacados())->toBe(Vehiculo::MAX_DESTACADOS);
});

test('re-saving an already pinned vehicle does not trip the cap', function () {
    $destacados = Vehiculo::factory()->count(Vehiculo::MAX_DESTACADOS)->destacado()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->patch(route('panel.vehiculos.destacado', $destacados->first()), ['destacado' => true])
        ->assertSessionHasNoErrors();

    expect(Vehiculo::contarDestacados())->toBe(Vehiculo::MAX_DESTACADOS);
});

test('freeing a slot lets another vehicle in', function () {
    $destacados = Vehiculo::factory()->count(Vehiculo::MAX_DESTACADOS)->destacado()->create();
    $sobrante = Vehiculo::factory()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->patch(route('panel.vehiculos.destacado', $destacados->first()), ['destacado' => false])
        ->assertSessionHasNoErrors();

    $this->actingAs($admin)
        ->patch(route('panel.vehiculos.destacado', $sobrante), ['destacado' => true])
        ->assertSessionHasNoErrors();

    expect($sobrante->refresh()->destacado)->toBeTrue();
});

test('the landing page leads with the pinned vehicles', function () {
    $viejo = Vehiculo::factory()->destacado()->create([
        'slug' => 'el-elegido',
        'created_at' => now()->subYear(),
    ]);

    Vehiculo::factory()->count(2)->create();

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('destacados.0.slug', $viejo->slug));
});

test('a draft cannot sneak onto the landing page by being pinned', function () {
    $borrador = Vehiculo::factory()->borrador()->destacado()->create(['slug' => 'sin-publicar']);
    Vehiculo::factory()->create(['slug' => 'a-la-venta']);

    // Guardar un borrador lo desmarca solo, así que ni siquiera queda pinchado.
    expect($borrador->refresh()->destacado)->toBeFalse();

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('destacados', 1)
            ->where('destacados.0.slug', 'a-la-venta')
        );
});

test('a draft cannot be pinned from the panel', function () {
    $borrador = Vehiculo::factory()->borrador()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('panel.vehiculos.index'))
        ->patch(route('panel.vehiculos.destacado', $borrador), ['destacado' => true])
        ->assertSessionHasErrors('destacado');

    expect($borrador->refresh()->destacado)->toBeFalse();
});

test('turning a pinned vehicle into a draft unpins it', function () {
    $vehiculo = Vehiculo::factory()->destacado()->create();

    $vehiculo->update(['estado' => EstadoVehiculo::Borrador]);

    expect($vehiculo->refresh()->destacado)->toBeFalse()
        ->and(Vehiculo::contarDestacados())->toBe(0);
});

test('a reserved vehicle can be pinned and reaches the landing page', function () {
    $reservado = Vehiculo::factory()->reservado()->create(['slug' => 'con-senia']);

    $this->actingAs(User::factory()->admin()->create())
        ->patch(route('panel.vehiculos.destacado', $reservado), ['destacado' => true])
        ->assertSessionHasNoErrors();

    expect($reservado->refresh()->destacado)->toBeTrue();

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('destacados.0.slug', 'con-senia'));
});

test('every slot the panel counts is a slot the landing page shows', function () {
    // El bug que arreglamos: el panel contaba destacados que la portada nunca
    // llegaba a mostrar, y el lugar libre se lo comia el relleno automatico.
    Vehiculo::factory()->reservado()->destacado()->create();
    Vehiculo::factory()->vendido()->destacado()->create();
    Vehiculo::factory()->borrador()->destacado()->create();

    expect(Vehiculo::contarDestacados())->toBe(2)
        ->and(Vehiculo::destacados()->where('destacado', true))->toHaveCount(2);
});
