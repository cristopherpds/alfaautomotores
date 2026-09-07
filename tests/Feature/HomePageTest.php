<?php

use App\Models\Entrega;
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

/*
 * La tira de «Nuestros clientes» sale de la tabla `entregas`; los archivos viven
 * en el disco `public`. Ver `App\Models\Entrega::paraLaTira()`.
 */
test('the deliveries strip lists the photos newest first', function () {
    $vieja = Entrega::factory()->fecha('2025-10-28')->create();
    $primeraDelDia = Entrega::factory()->fecha('2025-11-18')->create();
    $segundaDelDia = Entrega::factory()->fecha('2025-11-18')->create();
    $nueva = Entrega::factory()->fecha('2026-08-18')->create();

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('entregas', 4)
            ->where('entregas.0.url', $nueva->url())
            // El id desempata las del mismo día, como antes el número del archivo.
            ->where('entregas.1.url', $segundaDelDia->url())
            ->where('entregas.2.url', $primeraDelDia->url())
            ->where('entregas.3.url', $vieja->url())
        );
});

test('each delivery carries the file and the date the strip prints', function () {
    $foto = Entrega::factory()->create([
        'ruta' => 'entregas/una-entrega.jpg',
        'fecha' => '2025-11-14',
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('entregas.0', fn ($entrega) => $entrega
                // Absoluta: `config/filesystems.php` arma la url con APP_URL.
                ->where('url', $foto->url())
                ->where('fecha', '2025-11-14')
                ->where('etiqueta', '14.11.25')
                ->where('legible', '14 de noviembre de 2025')
            )
            ->etc()
        );
});

/*
 * El local escribe «setiembre», no «septiembre»: por eso los meses van a mano en
 * el modelo y no salen del locale de Carbon.
 */
test('september is spelled the way the shop spells it', function () {
    Entrega::factory()->fecha('2025-09-14')->create();

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('entregas.0.legible', '14 de setiembre de 2025')
            ->etc()
        );
});

/* Sin fotos la sección entera no se dibuja: `entregas.tsx` devuelve `null`. */
test('the strip is empty when there are no deliveries', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('entregas', 0));
});
