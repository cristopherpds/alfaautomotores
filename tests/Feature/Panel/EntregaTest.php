<?php

use App\Enums\UserRole;
use App\Models\Entrega;
use App\Models\User;
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
 * mime correcto. El nombre no puede repetir el helper de `VehiculoImagenTest`:
 * Pest carga todos los archivos en el mismo proceso.
 */
function fotoDeEntrega(string $nombre = 'entrega.jpg'): UploadedFile
{
    return UploadedFile::fake()->create($nombre, 100, 'image/jpeg');
}

test('a seller uploads several photos under one date', function () {
    $this->actingAs(User::factory()->role(UserRole::Vendedor)->create())
        ->from(route('panel.entregas.index'))
        ->post(route('panel.entregas.store'), [
            'fecha' => '2026-03-05',
            'fotos' => [
                fotoDeEntrega('primera.jpg'),
                fotoDeEntrega('segunda.jpg'),
                fotoDeEntrega('tercera.jpg'),
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('panel.entregas.index'));

    $entregas = Entrega::all();

    expect($entregas)->toHaveCount(3)
        ->and($entregas->pluck('fecha')->map->toDateString()->unique()->all())->toBe(['2026-03-05']);

    $entregas->each(fn (Entrega $entrega) => Storage::disk('public')->assertExists($entrega->ruta));
});

test('the team role sees the deliveries but cannot upload', function () {
    Entrega::factory()->create();

    $equipo = User::factory()->role(UserRole::Equipo)->create();

    $this->actingAs($equipo)
        ->get(route('panel.entregas.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('panel/entregas/index')
            ->has('entregas', 1)
            ->where('puedeGestionar', false)
        );

    $this->actingAs($equipo)
        ->post(route('panel.entregas.store'), [
            'fecha' => '2026-03-05',
            'fotos' => [fotoDeEntrega()],
        ])
        ->assertForbidden();

    expect(Entrega::count())->toBe(1);
});

test('the team role cannot fix a date nor delete', function () {
    $entrega = Entrega::factory()->fecha('2026-03-05')->create();
    $equipo = User::factory()->role(UserRole::Equipo)->create();

    $this->actingAs($equipo)
        ->patch(route('panel.entregas.update', $entrega), ['fecha' => '2026-03-06'])
        ->assertForbidden();

    $this->actingAs($equipo)
        ->delete(route('panel.entregas.destroy', $entrega))
        ->assertForbidden();

    expect($entrega->fresh()->fecha->toDateString())->toBe('2026-03-05');
});

test('guests are sent to the login', function () {
    $this->get(route('panel.entregas.index'))
        ->assertRedirect(route('login'));
});

test('only real images get through', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->from(route('panel.entregas.index'))
        ->post(route('panel.entregas.store'), [
            'fecha' => '2026-03-05',
            'fotos' => [UploadedFile::fake()->create('lista.pdf', 100, 'application/pdf')],
        ])
        ->assertSessionHasErrors('fotos.0');

    expect(Entrega::count())->toBe(0);
});

test('the date is required and cannot be in the future', function (?string $fecha) {
    $this->actingAs(User::factory()->admin()->create())
        ->from(route('panel.entregas.index'))
        ->post(route('panel.entregas.store'), [
            'fecha' => $fecha,
            'fotos' => [fotoDeEntrega()],
        ])
        ->assertSessionHasErrors('fecha');

    expect(Entrega::count())->toBe(0);
})->with([
    'sin fecha' => null,
    'mañana' => fn () => now()->addDay()->toDateString(),
]);

test('the batch has a cap', function () {
    $fotos = [];

    for ($i = 0; $i <= Entrega::MAX_POR_LOTE; $i++) {
        $fotos[] = fotoDeEntrega("entrega-{$i}.jpg");
    }

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('panel.entregas.index'))
        ->post(route('panel.entregas.store'), [
            'fecha' => '2026-03-05',
            'fotos' => $fotos,
        ])
        ->assertSessionHasErrors('fotos');

    expect(Entrega::count())->toBe(0);
});

test('a single delivery date can be fixed afterwards', function () {
    $entrega = Entrega::factory()->fecha('2026-03-05')->create();
    $otra = Entrega::factory()->fecha('2026-03-06')->create();

    $this->actingAs(User::factory()->role(UserRole::Vendedor)->create())
        ->from(route('panel.entregas.index'))
        ->patch(route('panel.entregas.update', $entrega), ['fecha' => '2026-02-14'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('panel.entregas.index'));

    expect($entrega->fresh()->fecha->toDateString())->toBe('2026-02-14')
        // La foto no se toca: corregir la fecha no reemplaza el archivo.
        ->and($entrega->fresh()->ruta)->toBe($entrega->ruta)
        ->and($otra->fresh()->fecha->toDateString())->toBe('2026-03-06');
});

test('deleting a delivery takes its file with it', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('panel.entregas.store'), [
            'fecha' => '2026-03-05',
            'fotos' => [fotoDeEntrega()],
        ]);

    $entrega = Entrega::sole();

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('panel.entregas.index'))
        ->delete(route('panel.entregas.destroy', $entrega))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('panel.entregas.index'));

    expect(Entrega::count())->toBe(0);

    Storage::disk('public')->assertMissing($entrega->ruta);
});

test('the panel lists the deliveries newest first', function () {
    $vieja = Entrega::factory()->fecha('2025-10-28')->create();
    $primeraDelDia = Entrega::factory()->fecha('2026-03-05')->create();
    $segundaDelDia = Entrega::factory()->fecha('2026-03-05')->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('panel.entregas.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('panel/entregas/index')
            ->has('entregas', 3)
            // El id desempata las del mismo día: la última cargada va primero.
            ->where('entregas.0.id', $segundaDelDia->id)
            ->where('entregas.1.id', $primeraDelDia->id)
            ->where('entregas.2.id', $vieja->id)
            ->where('puedeGestionar', true)
            ->where('maxPorLote', Entrega::MAX_POR_LOTE)
        );
});

test('an uploaded delivery reaches the landing strip', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('panel.entregas.store'), [
            'fecha' => '2026-03-05',
            'fotos' => [fotoDeEntrega()],
        ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('entregas', 1)
            ->where('entregas.0.etiqueta', '05.03.26')
            ->etc()
        );
});
