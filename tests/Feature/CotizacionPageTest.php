<?php

use App\Enums\EstadoCotizacion;
use App\Models\Cliente;
use App\Models\Cotizacion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
 * «Cotizá tu auto» desde el sitio público. La solicitud se guarda con sus
 * fotos en el disco privado y suma a la persona como lead en Clientes.
 */

beforeEach(function () {
    Storage::fake('local');
});

/**
 * Los datos obligatorios de una solicitud.
 *
 * El nombre no puede chocar con los helpers de otros archivos: Pest carga todo
 * en un mismo proceso.
 *
 * @return array<string, mixed>
 */
function datosDeCotizacion(array $extra = []): array
{
    return [
        'marca' => 'Toyota',
        'modelo' => 'Corolla',
        'anio' => 2018,
        'kilometros' => '85.000',
        'nombre' => 'Ana',
        'apellido' => 'Pérez',
        'celular' => '099 123 456',
        ...$extra,
    ];
}

/**
 * Una foto de prueba: sin GD alcanza con declarar el mime.
 */
function fotoDeCotizacion(string $nombre = 'auto.jpg'): UploadedFile
{
    return UploadedFile::fake()->create($nombre, 100, 'image/jpeg');
}

test('the quote page renders with the years and photo slots', function () {
    $this->get(route('cotizacion'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('cotiza-tu-auto')
            ->where('anios.0', now()->year + 1)
            ->has('fotos', 4)
            ->where('fotos.0.value', 'derecha')
            ->where('fotos.3.value', 'tablero')
        );
});

test('a visitor sends a quote request with photos', function () {
    $this->from(route('cotizacion'))
        ->post(route('cotizacion.store'), datosDeCotizacion([
            'detalles' => 'Único dueño.',
            'foto_derecha' => fotoDeCotizacion('derecha.jpg'),
            'foto_tablero' => fotoDeCotizacion('tablero.jpg'),
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('cotizacion'));

    $cotizacion = Cotizacion::sole();

    expect($cotizacion->marca)->toBe('Toyota')
        ->and($cotizacion->kilometros)->toBe(85000)
        ->and($cotizacion->detalles)->toBe('Único dueño.')
        ->and($cotizacion->estado)->toBe(EstadoCotizacion::Nueva)
        ->and($cotizacion->ladosConFoto())->toBe(['derecha', 'tablero'])
        ->and($cotizacion->foto_izquierda)->toBeNull();

    Storage::disk('local')->assertExists($cotizacion->foto_derecha);
    Storage::disk('local')->assertExists($cotizacion->foto_tablero);
    expect($cotizacion->foto_derecha)->toStartWith('cotizaciones/'.$cotizacion->id.'/');
    Storage::disk('public')->assertMissing($cotizacion->foto_derecha);
});

test('the photos are optional', function () {
    $this->post(route('cotizacion.store'), datosDeCotizacion())
        ->assertSessionHasNoErrors();

    expect(Cotizacion::sole()->ladosConFoto())->toBe([]);
});

test('the seller joins the clients as a lead, matched by phone', function () {
    $existente = Cliente::factory()->create(['celular' => '099123456']);

    $this->post(route('cotizacion.store'), datosDeCotizacion());

    expect(Cliente::count())->toBe(1)
        ->and(Cotizacion::sole()->cliente_id)->toBe($existente->id);

    $this->post(route('cotizacion.store'), datosDeCotizacion(['celular' => '098 765 432']));

    expect(Cliente::count())->toBe(2);
});

test('the required fields are validated', function () {
    $this->post(route('cotizacion.store'), [])
        ->assertSessionHasErrors(['marca', 'modelo', 'anio', 'kilometros', 'nombre', 'apellido', 'celular']);

    expect(Cotizacion::count())->toBe(0);
});

test('the year has to be in range', function (int $anio) {
    $this->post(route('cotizacion.store'), datosDeCotizacion(['anio' => $anio]))
        ->assertSessionHasErrors('anio');
})->with([
    'too old' => 1970,
    'from the future' => fn () => now()->year + 2,
]);

test('a photo slot only takes images', function () {
    $this->post(route('cotizacion.store'), datosDeCotizacion([
        'foto_interior' => UploadedFile::fake()->create('papeles.pdf', 100, 'application/pdf'),
    ]))->assertSessionHasErrors('foto_interior');

    expect(Cotizacion::count())->toBe(0);
});
