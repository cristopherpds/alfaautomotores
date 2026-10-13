<?php

use App\Enums\EstadoCotizacion;
use App\Enums\UserRole;
use App\Models\Cotizacion;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

/**
 * Una cotización con la foto del tablero ya guardada en el disco privado.
 */
function cotizacionConFoto(): Cotizacion
{
    $cotizacion = Cotizacion::factory()->create();
    $ruta = Cotizacion::CARPETA.'/'.$cotizacion->id.'/tablero.jpg';

    Storage::disk('local')->put($ruta, 'jpg');
    $cotizacion->forceFill(['foto_tablero' => $ruta])->saveQuietly();

    return $cotizacion;
}

test('guests are sent to the login', function () {
    $cotizacion = cotizacionConFoto();

    $this->get(route('panel.cotizaciones.index'))->assertRedirect(route('login'));
    $this->get(route('panel.cotizaciones.foto', [$cotizacion, 'tablero']))->assertRedirect(route('login'));
});

test('the whole team sees the list and a request, photos included', function () {
    $cotizacion = cotizacionConFoto();
    Cotizacion::factory()->contactada()->create();

    $equipo = User::factory()->role(UserRole::Equipo)->create();

    $this->actingAs($equipo)
        ->get(route('panel.cotizaciones.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('panel/cotizaciones/index')
            ->has('cotizaciones.data', 2)
        );

    $this->actingAs($equipo)
        ->get(route('panel.cotizaciones.show', $cotizacion))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('panel/cotizaciones/show')
            ->has('cotizacion.fotos', 1)
            ->where('cotizacion.fotos.0.lado', 'tablero')
            ->where('can.update', false)
            ->where('can.delete', false)
        );

    $this->actingAs($equipo)
        ->get(route('panel.cotizaciones.foto', [$cotizacion, 'tablero']))
        ->assertOk();
});

test('the list filters by state', function () {
    Cotizacion::factory()->create();
    Cotizacion::factory()->contactada()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('panel.cotizaciones.index', ['estado' => 'contactada']))
        ->assertInertia(fn ($page) => $page
            ->has('cotizaciones.data', 1)
            ->where('cotizaciones.data.0.estado', 'contactada')
            ->where('filtros.estado', 'contactada')
        );
});

test('a missing photo is a 404', function () {
    $cotizacion = Cotizacion::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('panel.cotizaciones.foto', [$cotizacion, 'derecha']))
        ->assertNotFound();
});

test('a seller moves a request along', function () {
    $cotizacion = Cotizacion::factory()->create();

    $this->actingAs(User::factory()->role(UserRole::Vendedor)->create())
        ->patch(route('panel.cotizaciones.estado', $cotizacion), ['estado' => 'contactada'])
        ->assertSessionHasNoErrors();

    expect($cotizacion->fresh()->estado)->toBe(EstadoCotizacion::Contactada);
});

test('the team role cannot change or delete a request', function () {
    $cotizacion = Cotizacion::factory()->create();
    $equipo = User::factory()->role(UserRole::Equipo)->create();

    $this->actingAs($equipo)
        ->patch(route('panel.cotizaciones.estado', $cotizacion), ['estado' => 'contactada'])
        ->assertForbidden();

    $this->actingAs($equipo)
        ->delete(route('panel.cotizaciones.destroy', $cotizacion))
        ->assertForbidden();
});

test('deleting a request removes its photos', function () {
    $cotizacion = cotizacionConFoto();
    $ruta = $cotizacion->foto_tablero;

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('panel.cotizaciones.destroy', $cotizacion))
        ->assertRedirect(route('panel.cotizaciones.index'));

    expect(Cotizacion::count())->toBe(0);
    Storage::disk('local')->assertMissing($ruta);
});
