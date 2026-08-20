<?php

use App\Models\Vehiculo;

test('the landing page renders for guests', function () {
    Vehiculo::factory()->count(8)->create();
    Vehiculo::factory()->borrador()->create();

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('welcome')
            ->has('destacados', Vehiculo::MAX_DESTACADOS)
            ->where('totalStock', 8)
            ->where('site.ciudad', 'Rivera')
            ->where('site.whatsapp', config('alfa.whatsapp'))
        );
});

test('the featured vehicles carry everything the cards render', function () {
    Vehiculo::factory()->create([
        'slug' => 'strada-freedom-24',
        'marca' => 'Fiat',
        'precio' => 23900,
        'moneda' => 'USD',
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('destacados.0', fn ($vehiculo) => $vehiculo
                ->where('slug', 'strada-freedom-24')
                ->where('marca', 'Fiat')
                ->where('precio', 23900)
                ->where('moneda', 'USD')
                ->where('estado', 'publicado')
                ->etc()
            )
        );
});

test('the pinned vehicles come first and the rest fills up the row', function () {
    // El más viejo del stock: sin destacar quedaría último.
    $viejo = Vehiculo::factory()->destacado()->create([
        'slug' => 'el-destacado',
        'created_at' => now()->subYear(),
    ]);

    Vehiculo::factory()->count(3)->create();

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('destacados.0.slug', $viejo->slug)
            ->has('destacados', 4)
        );
});

test('sold and reserved vehicles never make the landing page', function () {
    Vehiculo::factory()->reservado()->create(['slug' => 'reservado']);
    Vehiculo::factory()->vendido()->create(['slug' => 'vendido']);
    Vehiculo::factory()->create(['slug' => 'a-la-venta']);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('destacados', 1)
            ->where('destacados.0.slug', 'a-la-venta')
            // Reservados y vendidos siguen contando como stock visible.
            ->where('totalStock', 3)
        );
});

/*
 * El hero de la portada usa este video de fondo por ruta fija (ver
 * `resources/js/pages/welcome.tsx`). Si falta, la sección no se rompe —queda el
 * fondo tinta— así que el fallo pasaría desapercibido sin esta comprobación.
 */
test('the hero background video ships with the public assets', function () {
    expect(public_path('assets/hero-ruta.mp4'))->toBeFile();
});
