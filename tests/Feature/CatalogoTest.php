<?php

use App\Models\Vehiculo;

test('the catalogue lists the whole public stock', function () {
    $this->get(route('catalogo'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('catalogo')
            ->has('vehiculos', 22)
            ->where('site.ciudad', 'Rivera')
        );
});

test('a vehicle page renders its details', function () {
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

test('an unknown slug is a 404', function () {
    $this->get(route('vehiculos.show', 'no-existe'))->assertNotFound();
});

test('related vehicles share the body type and exclude the current one', function () {
    $this->get(route('vehiculos.show', 'tiggo2-23'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            // El otro SUV publicado; el CS55 Plus queda fuera por reservado.
            ->has('similares', 1)
            ->where('similares.0.slug', 'ecosport-13')
            ->where('similares.0.tipo', 'SUV')
        );

    $tiggo = Vehiculo::buscar('tiggo2-23');

    expect(Vehiculo::similares($tiggo)->pluck('slug'))->not->toContain('tiggo2-23');
});

test('sold and reserved vehicles stay out of the related list', function () {
    // El CS55 Plus está reservado: no puede aparecer como sugerencia.
    $similares = Vehiculo::similares(Vehiculo::buscar('tiggo2-23'));

    expect($similares->pluck('slug'))->not->toContain('cs55-plus-23');
});

test('every public vehicle has a reachable page', function () {
    Vehiculo::publicos()->each(function (Vehiculo $vehiculo) {
        $this->get(route('vehiculos.show', $vehiculo->slug))->assertOk();
    });
});
