<?php

use App\Enums\EstadoProducto;
use App\Enums\FamiliaProducto;
use App\Models\Producto;
use Database\Seeders\ProductoSeeder;
use Illuminate\Support\Facades\Storage;

/*
 * El importador del catálogo de movilidad: lee `database/data/productos.json`
 * y las fotos de al lado del filesystem real, y escribe en el disco falseado.
 */

beforeEach(function () {
    Storage::fake('public');
});

/**
 * Las filas de la semilla.
 *
 * @return array<int, array<string, mixed>>
 */
function semillaDeProductos(): array
{
    return json_decode(
        (string) file_get_contents(database_path('data/productos.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
}

test('the seeder imports the whole catalogue once', function () {
    $this->seed(ProductoSeeder::class);

    $esperados = count(semillaDeProductos());

    expect($esperados)->toBeGreaterThan(0)
        ->and(Producto::count())->toBe($esperados);

    // Idempotente: correrlo de nuevo no duplica ni filas ni fotos.
    $this->seed(ProductoSeeder::class);

    expect(Producto::count())->toBe($esperados);
});

test('every seeded product carries a summary and a family', function () {
    $this->seed(ProductoSeeder::class);

    Producto::all()->each(function (Producto $producto): void {
        expect($producto->resumen)->not->toBeEmpty()
            ->and($producto->familia)->toBeInstanceOf(FamiliaProducto::class)
            ->and($producto->estado)->toBeInstanceOf(EstadoProducto::class);
    });
});

test('the seed keeps the cover photos next to the json', function () {
    $this->seed(ProductoSeeder::class);

    $conFoto = collect(semillaDeProductos())->filter(fn (array $fila): bool => (bool) ($fila['foto'] ?? null));

    expect($conFoto)->not->toBeEmpty();

    $conFoto->each(function (array $fila): void {
        $producto = Producto::where('slug', $fila['slug'])->sole();

        expect($producto->fotos)->toHaveCount(1);

        Storage::disk('public')->assertExists($producto->fotos->first()->ruta);
    });

    // Correrlo de nuevo no agrega una segunda portada.
    $this->seed(ProductoSeeder::class);

    $primero = Producto::where('slug', $conFoto->first()['slug'])->sole();

    expect($primero->fotos()->count())->toBe(1);
});

/*
 * El pedido era cargar también lo que el proveedor ya no publica y lo que el
 * catálogo impreso marca agotado, así que las tres situaciones tienen que
 * llegar al sitio.
 */
test('the catalogue keeps discontinued and out of stock models', function () {
    $this->seed(ProductoSeeder::class);

    foreach ([EstadoProducto::Publicado, EstadoProducto::SinStock, EstadoProducto::PorEncargue] as $estado) {
        expect(Producto::where('estado', $estado)->count())
            ->toBeGreaterThan(0, "no hay productos en estado {$estado->value}");
    }

    expect(Producto::where('estado', EstadoProducto::Borrador)->count())->toBe(0);
});

/* Las bicicletas se cotizan: ninguna sale con precio publicado. */
test('no bicycle is seeded with a price', function () {
    $this->seed(ProductoSeeder::class);

    expect(Producto::where('familia', FamiliaProducto::Bicicleta)->whereNotNull('precio')->count())->toBe(0)
        ->and(Producto::contar(bicicletas: true))->toBeGreaterThan(0);
});
