<?php

use App\Enums\EstadoProducto;
use App\Enums\FamiliaProducto;
use App\Models\Producto;
use App\Models\ProductoImagen;
use Illuminate\Support\Facades\Storage;

test('the mobility page lists everything except bicycles', function () {
    Producto::factory()->count(2)->create();
    Producto::factory()->familia(FamiliaProducto::Monopatin)->create();
    Producto::factory()->bicicleta()->create();
    Producto::factory()->borrador()->create();

    $this->get(route('movilidad'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('movilidad')
            ->has('productos', 3)
            ->where('site.ciudad', 'Rivera')
        );
});

test('the bicycle page lists only bicycles', function () {
    Producto::factory()->count(2)->create();
    Producto::factory()->bicicleta()->create(['slug' => 'speed-26']);
    Producto::factory()->bicicleta()->borrador()->create();

    $this->get(route('bicicletas'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('bicicletas')
            ->has('productos', 1)
            ->where('productos.0.slug', 'speed-26')
        );
});

test('a bicycle reaches the site without a price', function () {
    Producto::factory()->bicicleta()->create(['slug' => 'speed-26']);

    $this->get(route('productos.show', 'speed-26'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('productos/show')
            ->where('producto.precio', null)
            ->where('producto.familia', 'bicicleta')
        );
});

test('a product page renders its details', function () {
    Producto::factory()->create([
        'slug' => 'moto-electrica-max-350',
        'nombre' => 'Moto eléctrica MAX 350',
        'codigo' => 'MAX350',
        'precio' => 33900,
        'specs' => [['Potencia', '350 W'], ['Autonomía', '25–30 km']],
        'colores' => ['Blanca', 'Roja'],
    ]);

    $this->get(route('productos.show', 'moto-electrica-max-350'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('productos/show')
            ->where('producto.nombre', 'Moto eléctrica MAX 350')
            ->where('producto.codigo', 'MAX350')
            ->where('producto.precio', 33900)
            ->where('producto.familia', 'moto')
            ->has('producto.specs', 2)
            ->has('producto.colores', 2)
            ->where('site.telefono', config('alfa.telefono'))
        );
});

test('out of stock and made to order products still reach the public site', function () {
    Producto::factory()->sinStock()->create(['slug' => 'neo-cobra']);
    Producto::factory()->porEncargue()->create(['slug' => 'k-wing']);

    $this->get(route('movilidad'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('productos', 2));

    $this->get(route('productos.show', 'neo-cobra'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('producto.estado', 'sin_stock'));

    $this->get(route('productos.show', 'k-wing'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('producto.estado', 'por_encargue'));
});

test('a draft never reaches the public site', function () {
    Producto::factory()->borrador()->create(['slug' => 'sin-publicar']);

    $this->get(route('productos.show', 'sin-publicar'))->assertNotFound();
});

test('an unknown slug is a 404', function () {
    $this->get(route('productos.show', 'no-existe'))->assertNotFound();
});

test('a product page ships every gallery photo', function () {
    $producto = Producto::factory()->create(['slug' => 'thunder']);

    collect(range(0, 4))->each(fn (int $orden) => ProductoImagen::factory()
        ->orden($orden)
        ->create(['producto_id' => $producto->id]));

    $this->get(route('productos.show', 'thunder'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('producto.imagenes', 5));
});

test('related products share the family and exclude the current one', function () {
    $thunder = Producto::factory()->familia(FamiliaProducto::Monopatin)->create(['slug' => 'thunder']);
    Producto::factory()->familia(FamiliaProducto::Monopatin)->create(['slug' => 'raptor']);
    Producto::factory()->familia(FamiliaProducto::Moto)->create(['slug' => 'max-350']);

    $this->get(route('productos.show', 'thunder'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('similares', 1)
            ->where('similares.0.slug', 'raptor')
        );

    expect(Producto::similares($thunder)->pluck('slug'))->not->toContain('thunder');
});

test('a bicycle never shows up among a motorbike related list', function () {
    $moto = Producto::factory()->create();
    Producto::factory()->bicicleta()->create(['slug' => 'speed-26']);

    expect(Producto::similares($moto)->pluck('slug'))->not->toContain('speed-26');
});

test('deleting a product takes its photos with it', function () {
    // Sin el fake, el hook `deleting` borra la carpeta real `productos/{id}`.
    Storage::fake('public');

    $producto = Producto::factory()->create();
    ProductoImagen::factory()->create(['producto_id' => $producto->id]);

    $producto->delete();

    expect(ProductoImagen::count())->toBe(0);
});

test('every public product has a reachable page', function () {
    Producto::factory()->count(2)->create();
    Producto::factory()->sinStock()->create();
    Producto::factory()->bicicleta()->create();

    Producto::query()->whereNot('estado', EstadoProducto::Borrador)->each(
        fn (Producto $producto) => $this->get(route('productos.show', $producto->slug))->assertOk(),
    );
});
