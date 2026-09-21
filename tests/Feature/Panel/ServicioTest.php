<?php

use App\Enums\AreaServicio;
use App\Enums\Rubro;
use App\Enums\UserRole;
use App\Models\Puesto;
use App\Models\Servicio;
use App\Models\Turno;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
 * El ABM de los trabajos del taller. La duración es lo que mide el turno, así
 * que tocarla mueve los horarios que la web ofrece.
 */

beforeEach(function () {
    Storage::fake('public');
});

/**
 * Los campos de un servicio. El nombre no puede chocar con los helpers de otros
 * archivos: Pest carga todos los tests en un mismo proceso.
 *
 * @return array<string, mixed>
 */
function datosDelServicio(array $extra = []): array
{
    return [
        'slug' => 'service-completo',
        'nombre' => 'Service completo',
        'rubro' => Rubro::Taller->value,
        'area' => AreaServicio::Mantenimiento->value,
        'duracion' => 90,
        'descripcion' => 'Aceite, cuatro filtros y revisión de 30 puntos.',
        'activo' => true,
        'agendable' => true,
        'orden' => 0,
        ...$extra,
    ];
}

test('a seller adds a service with its photo', function () {
    $this->actingAs(User::factory()->role(UserRole::Vendedor)->create())
        ->from(route('panel.servicios.create'))
        ->post(route('panel.servicios.store'), datosDelServicio([
            'foto' => UploadedFile::fake()->create('service.jpg', 100, 'image/jpeg'),
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('panel.servicios.index'));

    $servicio = Servicio::sole();

    expect($servicio->nombre)->toBe('Service completo')
        ->and($servicio->duracion)->toBe(90)
        ->and($servicio->area)->toBe(AreaServicio::Mantenimiento);

    Storage::disk('public')->assertExists((string) $servicio->foto);
});

test('the team role sees the services but cannot touch them', function () {
    $servicio = Servicio::factory()->create();
    $equipo = User::factory()->role(UserRole::Equipo)->create();

    $this->actingAs($equipo)
        ->get(route('panel.servicios.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('panel/servicios/index')
            ->has('servicios', 1)
            ->where('puedeGestionar', false)
        );

    $this->actingAs($equipo)
        ->post(route('panel.servicios.store'), datosDelServicio())
        ->assertForbidden();

    $this->actingAs($equipo)
        ->put(route('panel.servicios.update', $servicio), datosDelServicio())
        ->assertForbidden();

    $this->actingAs($equipo)
        ->delete(route('panel.servicios.destroy', $servicio))
        ->assertForbidden();
});

test('guests are sent to the login', function () {
    $this->get(route('panel.servicios.index'))->assertRedirect(route('login'));
});

test('the slug stays unique', function () {
    Servicio::factory()->create(['slug' => 'service-completo']);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('panel.servicios.store'), datosDelServicio())
        ->assertSessionHasErrors('slug');

    expect(Servicio::count())->toBe(1);
});

test('the duration has to be a workable number of minutes', function (int $duracion) {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('panel.servicios.store'), datosDelServicio(['duracion' => $duracion]))
        ->assertSessionHasErrors('duracion');

    expect(Servicio::count())->toBe(0);
})->with([
    'demasiado corta' => 5,
    'más de un día de taller' => 600,
    'suelta' => 47,
]);

test('editing swaps the photo and keeps the disk clean', function () {
    $servicio = Servicio::factory()->create([
        'foto' => UploadedFile::fake()->create('vieja.jpg', 100, 'image/jpeg')->store('servicios', 'public'),
    ]);

    $vieja = (string) $servicio->foto;

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('panel.servicios.edit', $servicio))
        ->put(route('panel.servicios.update', $servicio), datosDelServicio([
            'slug' => $servicio->slug,
            'nombre' => 'Service renombrado',
            'foto' => UploadedFile::fake()->create('nueva.jpg', 100, 'image/jpeg'),
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('panel.servicios.index'));

    $servicio->refresh();

    expect($servicio->nombre)->toBe('Service renombrado')
        ->and($servicio->foto)->not->toBe($vieja);

    Storage::disk('public')->assertMissing($vieja);
    Storage::disk('public')->assertExists((string) $servicio->foto);
});

test('hiding a service takes it off the public page', function () {
    $servicio = Servicio::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('panel.servicios.update', $servicio), datosDelServicio([
            'slug' => $servicio->slug,
            'activo' => false,
        ]))
        ->assertSessionHasNoErrors();

    expect($servicio->fresh()->activo)->toBeFalse();

    $this->get(route('taller'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('servicios', 0)->etc());
});

test('a service without appointments can be deleted, photo included', function () {
    $servicio = Servicio::factory()->create([
        'foto' => UploadedFile::fake()->create('service.jpg', 100, 'image/jpeg')->store('servicios', 'public'),
    ]);

    $foto = (string) $servicio->foto;

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('panel.servicios.destroy', $servicio))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('panel.servicios.index'));

    expect(Servicio::count())->toBe(0);

    Storage::disk('public')->assertMissing($foto);
});

/*
 * Los turnos guardan qué trabajo se hizo: borrar el servicio dejaría la agenda
 * sin nombre. La clave foránea ya lo impide; esto comprueba que el panel avisa
 * en vez de romper.
 */
test('a service with appointments is kept', function () {
    abrirElTaller();

    $servicio = Servicio::factory()->create();

    Turno::factory()->for($servicio)->for(Puesto::query()->firstOrFail())->create();

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('panel.servicios.index'))
        ->delete(route('panel.servicios.destroy', $servicio))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('panel.servicios.index'));

    expect(Servicio::count())->toBe(1);
});

/*
 * El ABM maneja los dos rubros. El lavado va sin área y con precio; el área
 * sólo se exige cuando el rubro es el taller.
 */
test('a wash is created without an area and with a list price', function () {
    $this->actingAs(User::factory()->role(UserRole::Vendedor)->create())
        ->post(route('panel.servicios.store'), datosDelServicio([
            'slug' => 'lavado-auto',
            'nombre' => 'Auto',
            'rubro' => Rubro::Lavadero->value,
            'area' => '',
            'precio' => 500,
        ]))
        ->assertSessionHasNoErrors();

    $servicio = Servicio::where('slug', 'lavado-auto')->sole();

    expect($servicio->rubro)->toBe(Rubro::Lavadero)
        ->and($servicio->area)->toBeNull()
        ->and($servicio->precio)->toBe(500)
        ->and($servicio->precioLegible())->toBe('$ 500');
});

test('a workshop job still needs its area', function () {
    $this->actingAs(User::factory()->role(UserRole::Vendedor)->create())
        ->from(route('panel.servicios.create'))
        ->post(route('panel.servicios.store'), datosDelServicio(['area' => '']))
        ->assertSessionHasErrors('area');
});

test('the list shows both lines of business', function () {
    Servicio::factory()->create(['slug' => 'service']);
    Servicio::factory()->lavadero(700)->create(['slug' => 'lavado-camioneta']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('panel.servicios.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('servicios', 2)
            // Ordenados por rubro: primero el lavadero, después el taller.
            ->where('servicios.0.rubroLabel', 'Lavadero')
            ->where('servicios.0.precioLegible', '$ 700')
            ->where('servicios.0.areaLabel', null)
            ->where('servicios.1.rubroLabel', 'Taller')
            ->where('servicios.1.precioLegible', null)
            ->etc()
        );
});
