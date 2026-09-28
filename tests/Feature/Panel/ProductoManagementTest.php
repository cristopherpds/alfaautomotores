<?php

use App\Enums\EstadoProducto;
use App\Enums\FamiliaProducto;
use App\Enums\UserRole;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/* Borrar un producto limpia `productos/{id}` del disco `public`: sin el fake,
   los tests borran las fotos reales de la semilla que tengan el mismo id. */
beforeEach(function () {
    Storage::fake('public');
});

test('the catalogue list is open to everyone on the team', function (UserRole $role) {
    Producto::factory()->create();
    Producto::factory()->borrador()->create();

    $this->actingAs(User::factory()->role($role)->create())
        ->get(route('panel.productos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('panel/productos/index')
            ->where('seccion', 'movilidad')
            // Los borradores sí se ven en el panel.
            ->has('productos', 2)
        );
})->with([
    'admin' => UserRole::Admin,
    'vendedor' => UserRole::Vendedor,
    'equipo' => UserRole::Equipo,
]);

test('each section lists only its own families', function () {
    Producto::factory()->create(['nombre' => 'MAX 350']);
    Producto::factory()->familia(FamiliaProducto::Monopatin)->create(['nombre' => 'Monopatín X']);
    Producto::factory()->bicicleta()->create(['nombre' => 'Rodado 26']);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('panel.productos.index', ['seccion' => 'movilidad']))
        ->assertInertia(fn ($page) => $page
            ->has('productos', 2)
            ->has('familias', count(FamiliaProducto::deMovilidad()))
        );

    $this->actingAs($admin)
        ->get(route('panel.productos.index', ['seccion' => 'bicicletas']))
        ->assertInertia(fn ($page) => $page
            ->where('seccion', 'bicicletas')
            ->has('productos', 1)
            ->where('productos.0.nombre', 'Rodado 26')
            ->where('productos.0.precio', null)
            ->has('familias', 1)
        );
});

test('the list says whether the viewer can manage the catalogue', function (UserRole $role, bool $gestiona) {
    Producto::factory()->create();

    $this->actingAs(User::factory()->role($role)->create())
        ->get(route('panel.productos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('puedeCrear', $gestiona)
            ->where('productos.0.can.update', $gestiona)
            ->where('productos.0.can.delete', $gestiona)
        );
})->with([
    'admin' => [UserRole::Admin, true],
    'vendedor' => [UserRole::Vendedor, true],
    'equipo' => [UserRole::Equipo, false],
]);

test('guests are redirected to the login page', function () {
    $this->get(route('panel.productos.index'))->assertRedirect(route('login'));
});

test('the panel calls a published product Publicado, like the list badge', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('panel.productos.create'))
        ->assertInertia(fn ($page) => $page
            ->where('opciones.estados.1.value', 'publicado')
            ->where('opciones.estados.1.label', 'Publicado')
        );
});

test('adding from the bicycles section starts as a bicycle', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('panel.productos.create', ['seccion' => 'bicicletas']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('panel/productos/create')
            ->where('familiaInicial', 'bicicleta')
        );
});

test('sellers and admins can add a product', function (UserRole $role) {
    $this->actingAs(User::factory()->role($role)->create())
        ->post(route('panel.productos.store'), datosDeProducto())
        ->assertSessionHasNoErrors();

    $producto = Producto::where('slug', 'max-350')->firstOrFail();

    expect($producto->nombre)->toBe('MAX 350')
        ->and($producto->familia)->toBe(FamiliaProducto::Moto)
        ->and($producto->estado)->toBe(EstadoProducto::Publicado)
        ->and($producto->specs)->toBe([['Potencia', '350 W'], ['Autonomía', '25 km']])
        ->and($producto->colores)->toBe(['Negra', 'Roja']);
})->with([
    'admin' => UserRole::Admin,
    'vendedor' => UserRole::Vendedor,
]);

test('creating a product lands on its edit page so photos can be added', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('panel.productos.store'), datosDeProducto())
        ->assertRedirect(route('panel.productos.edit', Producto::firstOrFail()));
});

test('the team role cannot add a product', function () {
    $this->actingAs(User::factory()->role(UserRole::Equipo)->create())
        ->post(route('panel.productos.store'), datosDeProducto())
        ->assertForbidden();

    expect(Producto::count())->toBe(0);
});

test('a bicycle can be added without a price', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('panel.productos.store'), datosDeProducto([
            'slug' => 'rodado-26',
            'familia' => 'bicicleta',
            'precio' => '',
        ]))
        ->assertSessionHasNoErrors();

    expect(Producto::sole()->precio)->toBeNull();
});

test('blank spec and colour rows are dropped', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('panel.productos.store'), datosDeProducto([
            'specs' => [['Potencia', '350 W'], ['', ''], ['Autonomía', '25 km']],
            'colores' => ['Negra', '  ', 'Roja'],
        ]))
        ->assertSessionHasNoErrors();

    expect(Producto::sole())
        ->specs->toBe([['Potencia', '350 W'], ['Autonomía', '25 km']])
        ->colores->toBe(['Negra', 'Roja']);
});

test('a product can be saved with no specs or colours at all', function () {
    $datos = datosDeProducto();
    unset($datos['specs'], $datos['colores']);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('panel.productos.store'), $datos)
        ->assertSessionHasNoErrors();

    expect(Producto::sole())
        ->specs->toBe([])
        ->colores->toBe([]);
});

test('a half filled spec row is an error', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->from(route('panel.productos.create'))
        ->post(route('panel.productos.store'), datosDeProducto([
            'specs' => [['Potencia', '']],
        ]))
        ->assertSessionHasErrors('specs.0.1');
});

test('the slug is stored in lower case and cannot be taken twice', function () {
    Producto::factory()->create(['slug' => 'max-350']);

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('panel.productos.create'))
        ->post(route('panel.productos.store'), datosDeProducto(['slug' => '  MAX-350 ']))
        ->assertSessionHasErrors('slug');

    expect(Producto::count())->toBe(1);
});

test('the family has to be one of the supported ones', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->from(route('panel.productos.create'))
        ->post(route('panel.productos.store'), datosDeProducto(['familia' => 'cuatriciclo']))
        ->assertSessionHasErrors('familia');
});

test('editing returns to the section of the saved family', function () {
    $producto = Producto::factory()->create(['slug' => 'max-350']);

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('panel.productos.update', $producto), datosDeProducto([
            'familia' => 'bicicleta',
            'precio' => '',
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('panel.productos.index', ['seccion' => 'bicicletas']));

    expect($producto->refresh()->familia)->toBe(FamiliaProducto::Bicicleta);
});

test('a draft does not reach the public grid', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('panel.productos.store'), datosDeProducto([
            'slug' => 'rodado-26',
            'familia' => 'bicicleta',
            'estado' => 'borrador',
        ]));

    $this->get(route('bicicletas'))
        ->assertInertia(fn ($page) => $page->has('productos', 0));
});

test('sellers and admins can delete a product', function () {
    $producto = Producto::factory()->bicicleta()->create();

    $this->actingAs(User::factory()->role(UserRole::Vendedor)->create())
        ->delete(route('panel.productos.destroy', $producto))
        ->assertRedirect(route('panel.productos.index', ['seccion' => 'bicicletas']));

    expect(Producto::find($producto->id))->toBeNull();
});

test('the team role cannot delete a product', function () {
    $producto = Producto::factory()->create();

    $this->actingAs(User::factory()->role(UserRole::Equipo)->create())
        ->delete(route('panel.productos.destroy', $producto))
        ->assertForbidden();

    expect(Producto::find($producto->id))->not->toBeNull();
});

/**
 * Una ficha válida, para no repetirla en cada test.
 *
 * @param  array<string, mixed>  $reemplazos
 * @return array<string, mixed>
 */
function datosDeProducto(array $reemplazos = []): array
{
    return [
        'slug' => 'max-350',
        'nombre' => 'MAX 350',
        'familia' => 'moto',
        'codigo' => 'ETB-122',
        'precio' => 32900,
        'moneda' => 'UYU',
        'estado' => 'publicado',
        'resumen' => '350 W · 30 km/h · 25 km',
        'desc' => 'Moto eléctrica urbana con batería extraíble.',
        'specs' => [['Potencia', '350 W'], ['Autonomía', '25 km']],
        'colores' => ['Negra', 'Roja'],
        ...$reemplazos,
    ];
}
