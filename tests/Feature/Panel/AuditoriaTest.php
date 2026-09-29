<?php

use App\Enums\AccionAuditoria;
use App\Enums\UserRole;
use App\Models\Auditoria;
use App\Models\Producto;
use App\Models\ProductoImagen;
use App\Models\Servicio;
use App\Models\User;
use App\Models\Vehiculo;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/* Borrar vehículos y productos limpia sus carpetas del disco `public`: sin el
   fake, los tests borrarían fotos reales de la semilla. */
beforeEach(function () {
    Storage::fake('public');
});

/**
 * Las entradas de un tipo, de la más vieja a la más nueva.
 *
 * Filtra por tipo porque las factories también dejan entradas (el usuario del
 * `actingAs`, por ejemplo), y esas no son las que mira cada test.
 *
 * @return Collection<int, Auditoria>
 */
function entradasDe(string $tipo)
{
    return Auditoria::where('tipo', $tipo)->orderBy('id')->get();
}

test('creating, editing and deleting a vehicle leaves three entries with who did it', function () {
    $vendedor = User::factory()->role(UserRole::Vendedor)->create(['name' => 'Laura Vendedora']);

    $this->actingAs($vendedor)->post(route('panel.vehiculos.store'), [
        'slug' => 'strada-freedom-24', 'marca' => 'Fiat', 'modelo' => 'Strada',
        'version' => 'Freedom', 'anio' => 2024, 'km' => 18000, 'precio' => 23900,
        'moneda' => 'USD', 'comb' => 'Nafta', 'trans' => 'Manual', 'tipo' => 'Pick-up',
        'estado' => 'publicado', 'desc' => 'Cabina doble.',
    ]);

    $vehiculo = Vehiculo::sole();

    $this->actingAs($vendedor)->put(route('panel.vehiculos.update', $vehiculo), [
        ...$vehiculo->only(['slug', 'marca', 'modelo', 'version', 'anio', 'km', 'desc']),
        'precio' => 22900, 'moneda' => 'USD', 'comb' => 'Nafta', 'trans' => 'Manual',
        'tipo' => 'Pick-up', 'estado' => 'publicado',
    ]);

    $this->actingAs($vendedor)->delete(route('panel.vehiculos.destroy', $vehiculo));

    $entradas = entradasDe('vehiculo');

    expect($entradas->pluck('accion')->all())
        ->toBe([AccionAuditoria::Creado, AccionAuditoria::Modificado, AccionAuditoria::Eliminado])
        ->and($entradas->pluck('user_id')->unique()->all())->toBe([$vendedor->id])
        ->and($entradas->pluck('usuario')->unique()->all())->toBe(['Laura Vendedora'])
        ->and($entradas->pluck('etiqueta')->unique()->all())->toBe(['Fiat Strada Freedom'])
        // La modificación trae sólo lo que cambió, con el valor anterior.
        ->and($entradas[1]->cambios)->toBe(['precio' => [23900, 22900]])
        ->and($entradas[0]->cambios)->toHaveKey('marca', [null, 'Fiat'])
        ->and($entradas[0]->cambios)->not->toHaveKeys(['id', 'created_at', 'updated_at'])
        ->and($entradas[2]->cambios)->toHaveKey('precio', [22900, null]);
});

test('a batch delete leaves one entry per vehicle', function () {
    $vehiculos = Vehiculo::factory()->count(3)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('panel.vehiculos.lote.destroy'), [
            'vehiculos' => $vehiculos->pluck('id')->all(),
        ]);

    expect(entradasDe('vehiculo')->where('accion', AccionAuditoria::Eliminado))->toHaveCount(3);
});

test('uploading, reordering and deleting product photos is recorded', function () {
    $producto = Producto::factory()->create(['nombre' => 'MAX 350']);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('panel.productos.imagenes.store', $producto), [
        'imagenes' => [
            UploadedFile::fake()->create('a.jpg', 100, 'image/jpeg'),
            UploadedFile::fake()->create('b.jpg', 100, 'image/jpeg'),
        ],
    ]);

    [$primera, $segunda] = $producto->fotos()->get()->all();

    $this->actingAs($admin)->patch(route('panel.productos.imagenes.orden', $producto), [
        'imagenes' => [$segunda->id, $primera->id],
    ]);

    $this->actingAs($admin)->delete(route('panel.productos.imagenes.destroy', [$producto, $primera]));

    $fotos = entradasDe('foto_producto');

    expect($fotos->pluck('accion')->all())
        ->toBe([AccionAuditoria::Creado, AccionAuditoria::Creado, AccionAuditoria::Eliminado])
        ->and($fotos->first()->etiqueta)->toBe('Foto de MAX 350');

    // El reordenamiento es un update masivo: queda anotado sobre el producto.
    expect(entradasDe('producto')->last())
        ->accion->toBe(AccionAuditoria::Modificado)
        ->cambios->toBe(['fotos' => [null, 'reordenadas']])
        ->user_id->toBe($admin->id);
});

test('a booking from the public site is recorded as the web client', function () {
    $lunes = abrirElTaller();
    $servicio = Servicio::factory()->duracion(30)->create(['slug' => 'service', 'nombre' => 'Service completo']);

    $this->post(route('taller.turnos.store'), [
        'servicio' => $servicio->slug,
        'fecha' => $lunes->toDateString(),
        'hora' => '10:00',
        'nombre' => 'Ana',
        'apellido' => 'Pérez',
        'email' => 'ana@example.com',
        'celular' => '099 123 456',
    ])->assertSessionHasNoErrors();

    expect(entradasDe('turno')->sole())
        ->user_id->toBeNull()
        ->usuario->toBe(Auditoria::CLIENTE_WEB)
        ->ip->not->toBeNull()
        ->etiqueta->toBe('Ana Pérez · Service completo · '.$lunes->format('d/m/Y').' 10:00');
});

test('a password change is recorded without the hash', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->put(route('user-password.update'), [
        'current_password' => 'password',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertSessionHasNoErrors();

    $entrada = entradasDe('usuario')->where('accion', AccionAuditoria::Modificado)->sole();

    expect($entrada->cambios)->toBe(['password' => [null, 'cambiada']])
        ->and(json_encode($entrada->cambios))->not->toContain($user->refresh()->password);
});

test('creating a user never stores the password', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('users.store'), [
            'name' => 'Nuevo',
            'email' => 'nuevo@example.com',
            'password' => 'secreto-123',
            'password_confirmation' => 'secreto-123',
            'role' => 'equipo',
        ])->assertSessionHasNoErrors();

    $alta = Auditoria::where('tipo', 'usuario')->where('etiqueta', 'Nuevo')->where('accion', 'creado')->sole();

    expect($alta->cambios)->not->toHaveKey('password')
        ->and($alta->cambios)->toHaveKey('email', [null, 'nuevo@example.com']);
});

test('touching only the remember token records nothing', function () {
    $user = User::factory()->create();
    $antes = Auditoria::count();

    $user->setRememberToken('otro-token');
    $user->save();

    expect(Auditoria::count())->toBe($antes);
});

test('deleting a user keeps their entries with the name they had', function () {
    $vendedor = User::factory()->role(UserRole::Vendedor)->create(['name' => 'Laura Vendedora']);
    Producto::factory()->create();

    $this->actingAs($vendedor)->delete(
        route('panel.productos.destroy', Producto::sole()),
    );

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('users.destroy', $vendedor))
        ->assertSessionHasNoErrors();

    expect(entradasDe('producto')->last())
        ->user_id->toBeNull()
        ->usuario->toBe('Laura Vendedora');
});

test('seeding the database writes no entries', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Auditoria::count())->toBe(0);
});

test('only admins can open the audit log', function (UserRole $role, int $estado) {
    $this->actingAs(User::factory()->role($role)->create())
        ->get(route('panel.auditoria.index'))
        ->assertStatus($estado);
})->with([
    'admin' => [UserRole::Admin, 200],
    'vendedor' => [UserRole::Vendedor, 403],
    'equipo' => [UserRole::Equipo, 403],
]);

test('the log is filtered by user, type and action', function () {
    Auditoria::factory()->create(['usuario' => 'Laura', 'tipo' => 'vehiculo']);
    Auditoria::factory()->create(['usuario' => 'Laura', 'tipo' => 'producto']);
    Auditoria::factory()->accion(AccionAuditoria::Eliminado)->create(['usuario' => 'Pedro', 'tipo' => 'vehiculo']);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('panel.auditoria.index', ['usuario' => 'Laura']))
        ->assertInertia(fn ($page) => $page
            ->component('panel/auditoria/index')
            ->has('entradas.data', 2)
            ->where('filtros.usuario', 'Laura')
        );

    $this->actingAs($admin)
        ->get(route('panel.auditoria.index', ['tipo' => 'vehiculo', 'accion' => 'eliminado']))
        ->assertInertia(fn ($page) => $page
            ->has('entradas.data', 1)
            ->where('entradas.data.0.usuario', 'Pedro')
        );
});

test('an unknown action in the filter is rejected', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('panel.auditoria.index', ['accion' => 'hackeado']))
        ->assertSessionHasErrors('accion');
});

test('photos of a product get the label of their product', function () {
    $foto = ProductoImagen::factory()->for(Producto::factory()->state(['nombre' => 'Chopper']))->create();

    expect($foto->etiquetaDeAuditoria())->toBe('Foto de Chopper');
});
