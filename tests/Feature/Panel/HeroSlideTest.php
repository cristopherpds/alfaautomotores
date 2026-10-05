<?php

use App\Enums\PosicionTexto;
use App\Enums\TipoFondo;
use App\Enums\UserRole;
use App\Models\HeroSlide;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
 * El ABM de los slides del hero de la portada: fondo (foto o video), textos,
 * hasta dos botones y una vigencia opcional.
 */

beforeEach(function () {
    Storage::fake('public');
});

/**
 * Los campos de un slide. El nombre no puede chocar con los helpers de otros
 * archivos: Pest carga todos los tests en un mismo proceso.
 *
 * @return array<string, mixed>
 */
function datosDelSlide(array $extra = []): array
{
    return [
        'tipo_fondo' => TipoFondo::Imagen->value,
        'fondo' => UploadedFile::fake()->image('fondo.jpg', 1920, 1080),
        'eyebrow' => 'Rivera · Uruguay',
        'titulo' => "Tu próximo auto\nestá acá.",
        'bajada' => 'Financiación en pesos y recibimos tu usado.',
        'posicion' => PosicionTexto::AbajoIzquierda->value,
        'boton1_texto' => 'Ver catálogo',
        'boton1_url' => '/catalogo',
        'boton2_texto' => 'Escribinos',
        'boton2_url' => 'https://wa.me/59899000000',
        'activo' => true,
        'orden' => 0,
        ...$extra,
    ];
}

function vendedorDelHero(): User
{
    return User::factory()->role(UserRole::Vendedor)->create();
}

test('a seller adds a slide with an image background', function () {
    $this->actingAs(vendedorDelHero())
        ->post(route('panel.hero.store'), datosDelSlide())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('panel.hero.index'));

    $slide = HeroSlide::sole();

    expect($slide->tipo_fondo)->toBe(TipoFondo::Imagen)
        ->and($slide->titulo)->toBe("Tu próximo auto\nestá acá.")
        ->and($slide->datos()['botones'])->toHaveCount(2);

    Storage::disk('public')->assertExists($slide->fondo);
});

test('a seller adds a slide with a video background', function () {
    $this->actingAs(vendedorDelHero())
        ->post(route('panel.hero.store'), datosDelSlide([
            'tipo_fondo' => TipoFondo::Video->value,
            'fondo' => UploadedFile::fake()->create('ruta.mp4', 5000, 'video/mp4'),
        ]))
        ->assertSessionHasNoErrors();

    $slide = HeroSlide::sole();

    expect($slide->tipo_fondo)->toBe(TipoFondo::Video);
    Storage::disk('public')->assertExists($slide->fondo);
});

test('the background has to match the chosen type', function () {
    $this->actingAs(vendedorDelHero())
        ->post(route('panel.hero.store'), datosDelSlide([
            'tipo_fondo' => TipoFondo::Video->value,
            'fondo' => UploadedFile::fake()->image('foto.jpg'),
        ]))
        ->assertSessionHasErrors('fondo');

    $this->actingAs(vendedorDelHero())
        ->post(route('panel.hero.store'), datosDelSlide([
            'fondo' => UploadedFile::fake()->create('ruta.mp4', 100, 'video/mp4'),
        ]))
        ->assertSessionHasErrors('fondo');

    expect(HeroSlide::count())->toBe(0);
});

test('a new slide needs a background', function () {
    $this->actingAs(vendedorDelHero())
        ->post(route('panel.hero.store'), datosDelSlide(['fondo' => null]))
        ->assertSessionHasErrors('fondo');
});

test('a button needs both its text and its destination', function () {
    $this->actingAs(vendedorDelHero())
        ->post(route('panel.hero.store'), datosDelSlide([
            'boton1_url' => '',
            'boton2_texto' => '',
        ]))
        ->assertSessionHasErrors(['boton1_url', 'boton2_texto']);
});

test('a button only takes a site path or a full address', function (string $destino) {
    $this->actingAs(vendedorDelHero())
        ->post(route('panel.hero.store'), datosDelSlide(['boton1_url' => $destino]))
        ->assertSessionHasErrors('boton1_url');
})->with(['catalogo', 'javascript:alert(1)', '//otro-sitio.com']);

test('a slide keeps the position chosen for its text', function () {
    $this->actingAs(vendedorDelHero())
        ->post(route('panel.hero.store'), datosDelSlide(['posicion' => 'centro-derecha']))
        ->assertSessionHasNoErrors();

    $slide = HeroSlide::sole();

    expect($slide->posicion)->toBe(PosicionTexto::CentroDerecha)
        ->and($slide->datos()['posicion'])->toBe('centro-derecha');
});

test('the text position has to be one of the nine cells', function (?string $posicion) {
    $this->actingAs(vendedorDelHero())
        ->post(route('panel.hero.store'), datosDelSlide(['posicion' => $posicion]))
        ->assertSessionHasErrors('posicion');
})->with(['medio', 'izquierda-abajo', null]);

test('editing moves the text to another position', function () {
    $slide = HeroSlide::factory()->create();

    $this->actingAs(vendedorDelHero())
        ->put(route('panel.hero.update', $slide), datosDelSlide([
            'fondo' => null,
            'posicion' => 'arriba-centro',
        ]))
        ->assertSessionHasNoErrors();

    expect($slide->fresh()->posicion)->toBe(PosicionTexto::ArribaCentro);
});

test('the end date cannot come before the start date', function () {
    $this->actingAs(vendedorDelHero())
        ->post(route('panel.hero.store'), datosDelSlide([
            'desde' => '2026-10-15',
            'hasta' => '2026-10-01',
        ]))
        ->assertSessionHasErrors('hasta');
});

test('editing without a new background keeps the current one', function () {
    $slide = HeroSlide::factory()->create();
    Storage::disk('public')->put($slide->fondo, 'foto');

    $this->actingAs(vendedorDelHero())
        ->put(route('panel.hero.update', $slide), datosDelSlide([
            'fondo' => null,
            'titulo' => 'Otro título',
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('panel.hero.index'));

    expect($slide->fresh()->titulo)->toBe('Otro título')
        ->and($slide->fresh()->fondo)->toBe($slide->fondo);
});

test('switching the background type asks for a new file', function () {
    $slide = HeroSlide::factory()->create();

    $this->actingAs(vendedorDelHero())
        ->put(route('panel.hero.update', $slide), datosDelSlide([
            'tipo_fondo' => TipoFondo::Video->value,
            'fondo' => null,
        ]))
        ->assertSessionHasErrors('fondo');
});

test('replacing the background deletes the old file', function () {
    $slide = HeroSlide::factory()->create();
    Storage::disk('public')->put($slide->fondo, 'foto vieja');

    $this->actingAs(vendedorDelHero())
        ->put(route('panel.hero.update', $slide), datosDelSlide())
        ->assertSessionHasNoErrors();

    Storage::disk('public')->assertMissing($slide->fondo);
    Storage::disk('public')->assertExists($slide->fresh()->fondo);
});

test('deleting a slide deletes its background', function () {
    $slide = HeroSlide::factory()->create();
    Storage::disk('public')->put($slide->fondo, 'foto');

    $this->actingAs(vendedorDelHero())
        ->delete(route('panel.hero.destroy', $slide))
        ->assertRedirect(route('panel.hero.index'));

    expect(HeroSlide::count())->toBe(0);
    Storage::disk('public')->assertMissing($slide->fondo);
});

test('the panel lists every slide with its state', function () {
    $this->travelTo('2026-10-05 10:00');

    HeroSlide::factory()->create(['orden' => 1]);
    HeroSlide::factory()->inactivo()->create(['orden' => 2]);
    HeroSlide::factory()->vigencia('2026-11-01', null)->create(['orden' => 3]);
    HeroSlide::factory()->vigencia(null, '2026-09-30')->create(['orden' => 4]);

    $this->actingAs(vendedorDelHero())
        ->get(route('panel.hero.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('panel/hero/index')
            ->where('slides.0.estado', 'activo')
            ->where('slides.1.estado', 'inactivo')
            ->where('slides.2.estado', 'programado')
            ->where('slides.3.estado', 'vencido')
            ->where('puedeGestionar', true)
        );
});

test('the team role sees the slides but cannot touch them', function () {
    $slide = HeroSlide::factory()->create();
    $equipo = User::factory()->role(UserRole::Equipo)->create();

    $this->actingAs($equipo)
        ->get(route('panel.hero.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('slides', 1)
            ->where('puedeGestionar', false)
        );

    $this->actingAs($equipo)->get(route('panel.hero.create'))->assertForbidden();
    $this->actingAs($equipo)->post(route('panel.hero.store'), datosDelSlide())->assertForbidden();
    $this->actingAs($equipo)->put(route('panel.hero.update', $slide), datosDelSlide())->assertForbidden();
    $this->actingAs($equipo)->delete(route('panel.hero.destroy', $slide))->assertForbidden();

    expect(HeroSlide::count())->toBe(1);
});
