<?php

use App\Enums\TipoVehiculo;
use App\Models\Vehiculo;
use App\Models\VehiculoImagen;

test('the catalogue lists the whole public stock', function () {
    Vehiculo::factory()->count(3)->create();
    Vehiculo::factory()->borrador()->create();

    $this->get(route('catalogo'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('catalogo')
            ->has('vehiculos', 3)
            ->where('site.ciudad', 'Rivera')
        );
});

test('a vehicle page renders its details', function () {
    Vehiculo::factory()->create([
        'slug' => 'tiggo2-23',
        'marca' => 'Chery',
        'modelo' => 'Tiggo 2',
        'version' => 'Pro',
        'precio' => 20900,
        'trans' => 'Automática',
    ]);

    $this->get(route('vehiculos.show', 'tiggo2-23'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('vehiculos/show')
            ->where('vehiculo.marca', 'Chery')
            ->where('vehiculo.modelo', 'Tiggo 2')
            ->where('vehiculo.version', 'Pro')
            ->where('vehiculo.precio', 20900)
            ->where('vehiculo.trans', 'Automática')
            ->where('site.telefono', config('alfa.telefono'))
        );
});

test('a vehicle page ships every gallery photo', function () {
    $vehiculo = Vehiculo::factory()->create(['slug' => 'corsa-10']);

    collect(range(0, 9))->each(fn (int $orden) => VehiculoImagen::factory()
        ->orden($orden)
        ->create(['vehiculo_id' => $vehiculo->id]));

    $this->get(route('vehiculos.show', 'corsa-10'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('vehiculo.imagenes', 10));
});

test('a draft never reaches the public site', function () {
    Vehiculo::factory()->borrador()->create(['slug' => 'sin-publicar']);

    $this->get(route('vehiculos.show', 'sin-publicar'))->assertNotFound();
});

test('an unknown slug is a 404', function () {
    $this->get(route('vehiculos.show', 'no-existe'))->assertNotFound();
});

test('related vehicles share the body type and exclude the current one', function () {
    $tiggo = Vehiculo::factory()->tipo(TipoVehiculo::Suv)->create(['slug' => 'tiggo2-23']);
    Vehiculo::factory()->tipo(TipoVehiculo::Suv)->create(['slug' => 'ecosport-13']);
    Vehiculo::factory()->tipo(TipoVehiculo::Hatchback)->create(['slug' => 'gol-15']);

    $this->get(route('vehiculos.show', 'tiggo2-23'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('similares', 1)
            ->where('similares.0.slug', 'ecosport-13')
            ->where('similares.0.tipo', 'SUV')
        );

    expect(Vehiculo::similares($tiggo)->pluck('slug'))->not->toContain('tiggo2-23');
});

test('sold and reserved vehicles stay out of the related list', function () {
    $tiggo = Vehiculo::factory()->tipo(TipoVehiculo::Suv)->create();
    Vehiculo::factory()->tipo(TipoVehiculo::Suv)->reservado()->create(['slug' => 'cs55-plus-23']);
    Vehiculo::factory()->tipo(TipoVehiculo::Suv)->vendido()->create(['slug' => 'creta-20']);

    expect(Vehiculo::similares($tiggo)->pluck('slug'))
        ->not->toContain('cs55-plus-23')
        ->not->toContain('creta-20');
});

test('every public vehicle has a reachable page', function () {
    Vehiculo::factory()->count(2)->create();
    Vehiculo::factory()->reservado()->create();
    Vehiculo::factory()->vendido()->create();

    Vehiculo::publicos()->each(function (Vehiculo $vehiculo) {
        $this->get(route('vehiculos.show', $vehiculo->slug))->assertOk();
    });
});
