<?php

use App\Enums\AreaServicio;
use App\Models\Servicio;

/*
 * La página pública del taller: la grilla de servicios y los horarios libres.
 * Los huecos se piden sobre la misma ruta (`?servicio=&fecha=`) con una recarga
 * parcial de Inertia, así que se prueban acá y no en un endpoint aparte.
 */

test('the workshop page renders for guests', function () {
    abrirElTaller();

    Servicio::factory()->count(3)->create();

    $this->get(route('taller'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('taller')
            ->has('servicios', 3)
            ->has('areas', count(AreaServicio::cases()))
            ->where('site.ciudad', 'Rivera')
            ->where('huecos', [])
        );
});

test('the booking window opens tomorrow and closes in a month', function () {
    abrirElTaller();

    $this->get(route('taller'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('ventana.desde', '2026-09-15')
            ->where('ventana.hasta', '2026-10-14')
            ->etc()
        );
});

test('inactive services never reach the public grid', function () {
    abrirElTaller();

    Servicio::factory()->create(['slug' => 'a-la-vista']);
    Servicio::factory()->inactivo()->create(['slug' => 'escondido']);

    $this->get(route('taller'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('servicios', 1)
            ->where('servicios.0.slug', 'a-la-vista')
            ->etc()
        );
});

test('a job that is quoted at the shop travels flagged', function () {
    abrirElTaller();

    Servicio::factory()->noAgendable()->create(['slug' => 'correa']);

    $this->get(route('taller'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('servicios.0.agendable', false)
            ->etc()
        );
});

test('the free slots of a day follow the shop hours', function () {
    $lunes = abrirElTaller();

    $servicio = Servicio::factory()->duracion(90)->create(['slug' => 'service']);

    // Lunes: 08:30–12:00 y 14:00–18:00 en tramos de hora y media.
    $this->get(route('taller', ['servicio' => $servicio->slug, 'fecha' => $lunes->toDateString()]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('huecos', ['08:30', '10:00', '14:00', '15:30'])
            ->etc()
        );

    // Sábado: sólo la mañana.
    $this->get(route('taller', ['servicio' => $servicio->slug, 'fecha' => $lunes->copy()->addDays(5)->toDateString()]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('huecos', ['08:30', '10:00'])
            ->etc()
        );

    // Domingo: no se atiende.
    $this->get(route('taller', ['servicio' => $servicio->slug, 'fecha' => $lunes->copy()->addDays(6)->toDateString()]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('huecos', [])->etc());
});

/*
 * Un trabajo de cuatro horas sólo entra en la ventana de la tarde, que mide
 * exactamente cuatro: es la razón por la que los trabajos mayores se marcan
 * como no agendables en vez de ofrecerse con un solo horario posible.
 */
test('a long job only fits in the afternoon window', function () {
    $lunes = abrirElTaller();

    $servicio = Servicio::factory()->duracion(240)->create(['slug' => 'correa']);

    $this->get(route('taller', ['servicio' => $servicio->slug, 'fecha' => $lunes->toDateString()]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('huecos', ['14:00'])->etc());
});

test('slots are only computed inside the booking window', function (string $cuando) {
    abrirElTaller();

    $servicio = Servicio::factory()->duracion(60)->create(['slug' => 'service']);

    $this->get(route('taller', ['servicio' => $servicio->slug, 'fecha' => $cuando]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('huecos', [])->etc());
})->with([
    'hoy' => '2026-09-14',
    'pasado el mes' => '2026-10-20',
    'una fecha inventada' => 'no-es-una-fecha',
]);

/*
 * El hero del taller usa esta foto de fondo por ruta fija (ver
 * `resources/js/pages/taller.tsx`). Si falta, la sección no se rompe —queda el
 * fondo tinta— así que el fallo pasaría desapercibido sin esta comprobación.
 */
test('the hero background photo ships with the public assets', function () {
    expect(public_path('assets/hero-taller.jpg'))->toBeFile();
});
