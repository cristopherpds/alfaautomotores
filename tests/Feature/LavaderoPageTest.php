<?php

use App\Models\Servicio;

/*
 * La página pública del lavadero: la lista de precios y los horarios libres.
 * Es la hermana de `TallerPageTest`; lo que se prueba acá de más es que los dos
 * catálogos no se mezclen y que el precio viaje ya formateado.
 */

test('the car wash page renders for guests', function () {
    abrirElLavadero();

    Servicio::factory()->lavadero()->count(2)->create();

    $this->get(route('lavadero'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('lavadero')
            ->has('servicios', 2)
            ->where('site.ciudad', 'Rivera')
            ->where('huecos', [])
        );
});

test('the booking window opens tomorrow and closes in a month', function () {
    abrirElLavadero();

    $this->get(route('lavadero'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('ventana.desde', '2026-09-15')
            ->where('ventana.hasta', '2026-10-14')
            ->etc()
        );
});

/*
 * Los dos catálogos comparten tabla, así que sin el filtro por rubro los
 * lavados aparecerían entre los services y al revés. Es el error que más
 * fácilmente pasaría desapercibido.
 */
test('each page only shows the services of its own line of business', function () {
    abrirElTaller();
    abrirElLavadero();

    Servicio::factory()->create(['slug' => 'service-completo']);
    Servicio::factory()->lavadero()->create(['slug' => 'lavado-auto']);

    $this->get(route('lavadero'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('servicios', 1)
            ->where('servicios.0.slug', 'lavado-auto')
            ->etc()
        );

    $this->get(route('taller'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('servicios', 1)
            ->where('servicios.0.slug', 'service-completo')
            ->etc()
        );
});

test('inactive washes never reach the public grid', function () {
    abrirElLavadero();

    Servicio::factory()->lavadero()->create(['slug' => 'a-la-vista']);
    Servicio::factory()->lavadero()->inactivo()->create(['slug' => 'escondido']);

    $this->get(route('lavadero'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('servicios', 1)
            ->where('servicios.0.slug', 'a-la-vista')
            ->etc()
        );
});

/*
 * El precio se formatea en el servidor para que la página no tenga que saber de
 * moneda ni de separadores.
 */
test('the price travels already formatted', function () {
    abrirElLavadero();

    Servicio::factory()->lavadero(1500)->create(['slug' => 'lavado-auto']);

    $this->get(route('lavadero'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('servicios.0.precioLegible', '$ 1.500')
            ->etc()
        );
});

test('the free slots of a day follow the shop hours', function () {
    $lunes = abrirElLavadero();

    $servicio = Servicio::factory()->lavadero()->duracion(60)->create(['slug' => 'lavado-auto']);

    // Lunes: 08:30–12:00 y 14:00–18:00 en tramos de una hora.
    $this->get(route('lavadero', ['servicio' => $servicio->slug, 'fecha' => $lunes->toDateString()]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('huecos', ['08:30', '09:30', '10:30', '14:00', '15:00', '16:00', '17:00'])
            ->etc()
        );

    // Sábado: sólo la mañana.
    $this->get(route('lavadero', ['servicio' => $servicio->slug, 'fecha' => $lunes->copy()->addDays(5)->toDateString()]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('huecos', ['08:30', '09:30', '10:30'])
            ->etc()
        );

    // Domingo: no se atiende.
    $this->get(route('lavadero', ['servicio' => $servicio->slug, 'fecha' => $lunes->copy()->addDays(6)->toDateString()]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('huecos', [])->etc());
});

test('slots are only computed inside the booking window', function (string $cuando) {
    abrirElLavadero();

    $servicio = Servicio::factory()->lavadero()->duracion(60)->create(['slug' => 'lavado-auto']);

    $this->get(route('lavadero', ['servicio' => $servicio->slug, 'fecha' => $cuando]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('huecos', [])->etc());
})->with([
    'hoy' => '2026-09-14',
    'pasado el mes' => '2026-10-20',
    'una fecha inventada' => 'no-es-una-fecha',
]);
