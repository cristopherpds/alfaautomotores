<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Vehiculo;
use App\Models\VehiculoImagen;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

/**
 * Una foto de prueba.
 *
 * `UploadedFile::fake()->image()` necesita la extensión GD, que no está
 * instalada: para validar `image|mimes` alcanza con un archivo que declare el
 * mime correcto.
 */
function fotoDePrueba(string $nombre = 'frente.jpg'): UploadedFile
{
    return UploadedFile::fake()->create($nombre, 100, 'image/jpeg');
}

test('a seller can upload photos to a vehicle', function () {
    $vehiculo = Vehiculo::factory()->create();

    $this->actingAs(User::factory()->role(UserRole::Vendedor)->create())
        ->from(route('panel.vehiculos.edit', $vehiculo))
        ->post(route('panel.vehiculos.imagenes.store', $vehiculo), [
            'imagenes' => [
                fotoDePrueba('frente.jpg'),
                fotoDePrueba('interior.jpg'),
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('panel.vehiculos.edit', $vehiculo));

    $fotos = $vehiculo->fotos()->get();

    expect($fotos)->toHaveCount(2)
        ->and($fotos->pluck('orden')->all())->toBe([0, 1]);

    $fotos->each(fn (VehiculoImagen $foto) => Storage::disk('public')->assertExists($foto->ruta));
});

test('the team role cannot upload photos', function () {
    $vehiculo = Vehiculo::factory()->create();

    $this->actingAs(User::factory()->role(UserRole::Equipo)->create())
        ->post(route('panel.vehiculos.imagenes.store', $vehiculo), [
            'imagenes' => [fotoDePrueba('frente.jpg')],
        ])
        ->assertForbidden();

    expect($vehiculo->fotos()->count())->toBe(0);
});

test('only real images get through', function () {
    $vehiculo = Vehiculo::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('panel.vehiculos.edit', $vehiculo))
        ->post(route('panel.vehiculos.imagenes.store', $vehiculo), [
            'imagenes' => [UploadedFile::fake()->create('lista.pdf', 100, 'application/pdf')],
        ])
        ->assertSessionHasErrors('imagenes.0');

    expect($vehiculo->fotos()->count())->toBe(0);
});

test('the gallery does not go past its limit', function () {
    $vehiculo = Vehiculo::factory()
        ->has(VehiculoImagen::factory()->count(Vehiculo::MAX_IMAGENES), 'fotos')
        ->create();

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('panel.vehiculos.edit', $vehiculo))
        ->post(route('panel.vehiculos.imagenes.store', $vehiculo), [
            'imagenes' => [fotoDePrueba('una-mas.jpg')],
        ])
        ->assertSessionHasErrors('imagenes');

    expect($vehiculo->fotos()->count())->toBe(Vehiculo::MAX_IMAGENES);
});

test('deleting a photo takes its file with it', function () {
    $vehiculo = Vehiculo::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('panel.vehiculos.imagenes.store', $vehiculo), [
            'imagenes' => [fotoDePrueba('frente.jpg')],
        ]);

    $foto = $vehiculo->fotos()->firstOrFail();

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('panel.vehiculos.imagenes.destroy', [$vehiculo, $foto]))
        ->assertSessionHasNoErrors();

    expect($vehiculo->fotos()->count())->toBe(0);
    Storage::disk('public')->assertMissing($foto->ruta);
});

test('photos of another vehicle cannot be deleted through this one', function () {
    $vehiculo = Vehiculo::factory()->create();
    $ajena = VehiculoImagen::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('panel.vehiculos.imagenes.destroy', [$vehiculo, $ajena]))
        ->assertNotFound();

    expect(VehiculoImagen::find($ajena->id))->not->toBeNull();
});

test('reordering the gallery changes the cover', function () {
    $vehiculo = Vehiculo::factory()->create();
    $primera = VehiculoImagen::factory()->orden(0)->for($vehiculo)->create();
    $segunda = VehiculoImagen::factory()->orden(1)->for($vehiculo)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->patch(route('panel.vehiculos.imagenes.orden', $vehiculo), [
            'imagenes' => [$segunda->id, $primera->id],
        ])
        ->assertSessionHasNoErrors();

    expect($vehiculo->fotos()->first()->id)->toBe($segunda->id);
});

test('the new order can only mention photos of this vehicle', function () {
    $vehiculo = Vehiculo::factory()->create();
    $propia = VehiculoImagen::factory()->for($vehiculo)->create();
    $ajena = VehiculoImagen::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('panel.vehiculos.edit', $vehiculo))
        ->patch(route('panel.vehiculos.imagenes.orden', $vehiculo), [
            'imagenes' => [$ajena->id, $propia->id],
        ])
        ->assertSessionHasErrors('imagenes.0');
});

test('deleting a vehicle sweeps away its gallery', function () {
    $vehiculo = Vehiculo::factory()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('panel.vehiculos.imagenes.store', $vehiculo), [
            'imagenes' => [fotoDePrueba('frente.jpg')],
        ]);

    $foto = $vehiculo->fotos()->firstOrFail();

    $this->actingAs($admin)
        ->delete(route('panel.vehiculos.destroy', $vehiculo))
        ->assertRedirect(route('panel.vehiculos.index'));

    expect(VehiculoImagen::find($foto->id))->toBeNull();
    Storage::disk('public')->assertMissing($foto->ruta);
});

test('the cover reaches the public site', function () {
    $vehiculo = Vehiculo::factory()->create(['slug' => 'strada-freedom-24']);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('panel.vehiculos.imagenes.store', $vehiculo), [
            'imagenes' => [fotoDePrueba('frente.jpg')],
        ]);

    $this->get(route('vehiculos.show', 'strada-freedom-24'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('vehiculo.imagenes', 1));
});
