<?php

use App\Enums\UserRole;
use App\Models\Producto;
use App\Models\ProductoImagen;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

/**
 * Una foto de prueba.
 *
 * No se llama `fotoDePrueba()` porque ese nombre ya lo ocupa
 * `VehiculoImagenTest` y Pest carga todo en el mismo proceso. Sin GD, alcanza
 * con un archivo que declare el mime correcto.
 */
function fotoDeProducto(string $nombre = 'frente.jpg'): UploadedFile
{
    return UploadedFile::fake()->create($nombre, 100, 'image/jpeg');
}

test('a seller can upload photos to a product', function () {
    $producto = Producto::factory()->create();

    $this->actingAs(User::factory()->role(UserRole::Vendedor)->create())
        ->from(route('panel.productos.edit', $producto))
        ->post(route('panel.productos.imagenes.store', $producto), [
            'imagenes' => [fotoDeProducto('frente.jpg'), fotoDeProducto('lateral.jpg')],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('panel.productos.edit', $producto));

    $fotos = $producto->fotos()->get();

    expect($fotos)->toHaveCount(2)
        ->and($fotos->pluck('orden')->all())->toBe([0, 1]);

    $fotos->each(fn (ProductoImagen $foto) => Storage::disk('public')->assertExists($foto->ruta));
});

test('the team role cannot upload photos to a product', function () {
    $producto = Producto::factory()->create();

    $this->actingAs(User::factory()->role(UserRole::Equipo)->create())
        ->post(route('panel.productos.imagenes.store', $producto), [
            'imagenes' => [fotoDeProducto()],
        ])
        ->assertForbidden();

    expect($producto->fotos()->count())->toBe(0);
});

test('the product gallery does not go past its limit', function () {
    $producto = Producto::factory()
        ->has(ProductoImagen::factory()->count(Producto::MAX_IMAGENES), 'fotos')
        ->create();

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('panel.productos.edit', $producto))
        ->post(route('panel.productos.imagenes.store', $producto), [
            'imagenes' => [fotoDeProducto('una-mas.jpg')],
        ])
        ->assertSessionHasErrors('imagenes');

    expect($producto->fotos()->count())->toBe(Producto::MAX_IMAGENES);
});

test('reordering the product gallery changes the cover', function () {
    $producto = Producto::factory()->create();
    $primera = ProductoImagen::factory()->orden(0)->for($producto)->create();
    $segunda = ProductoImagen::factory()->orden(1)->for($producto)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->patch(route('panel.productos.imagenes.orden', $producto), [
            'imagenes' => [$segunda->id, $primera->id],
        ])
        ->assertSessionHasNoErrors();

    expect($producto->fotos()->first()->id)->toBe($segunda->id);
});

test('the new order can only mention photos of this product', function () {
    $producto = Producto::factory()->create();
    $propia = ProductoImagen::factory()->for($producto)->create();
    $ajena = ProductoImagen::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('panel.productos.edit', $producto))
        ->patch(route('panel.productos.imagenes.orden', $producto), [
            'imagenes' => [$ajena->id, $propia->id],
        ])
        ->assertSessionHasErrors('imagenes.0');
});

test('photos of another product cannot be deleted through this one', function () {
    $producto = Producto::factory()->create();
    $ajena = ProductoImagen::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('panel.productos.imagenes.destroy', [$producto, $ajena]))
        ->assertNotFound();

    expect(ProductoImagen::find($ajena->id))->not->toBeNull();
});

test('deleting a product sweeps away its gallery', function () {
    $producto = Producto::factory()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('panel.productos.imagenes.store', $producto), [
            'imagenes' => [fotoDeProducto()],
        ]);

    $foto = $producto->fotos()->firstOrFail();

    $this->actingAs($admin)
        ->delete(route('panel.productos.imagenes.destroy', [$producto, $foto]))
        ->assertSessionHasNoErrors();

    Storage::disk('public')->assertMissing($foto->ruta);

    $this->actingAs($admin)
        ->post(route('panel.productos.imagenes.store', $producto), [
            'imagenes' => [fotoDeProducto()],
        ]);

    $otra = $producto->fotos()->firstOrFail();

    $this->actingAs($admin)->delete(route('panel.productos.destroy', $producto));

    expect(ProductoImagen::find($otra->id))->toBeNull();
    Storage::disk('public')->assertMissing($otra->ruta);
});

test('the product cover reaches the public site', function () {
    $producto = Producto::factory()->create(['slug' => 'max-350']);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('panel.productos.imagenes.store', $producto), [
            'imagenes' => [fotoDeProducto()],
        ]);

    $this->get(route('productos.show', 'max-350'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('producto.imagenes', 1));
});
