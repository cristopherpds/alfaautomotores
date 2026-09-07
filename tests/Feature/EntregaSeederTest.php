<?php

use App\Models\Entrega;
use Database\Seeders\EntregaSeeder;
use Illuminate\Support\Facades\Storage;

/*
 * El importador de la semilla histórica: `database/data/entregas/` deja de ser
 * el dato y pasa a cargarse una vez por entorno. Lee del filesystem real y
 * escribe en el disco falseado.
 */

beforeEach(function () {
    Storage::fake('public');
});

/** Cuántos archivos de la semilla siguen la convención de nombres. */
function entregasEnLaSemilla(): int
{
    $archivos = glob(database_path('data/entregas/*')) ?: [];

    return count(array_filter(
        $archivos,
        fn (string $ruta): bool => (bool) preg_match(
            '/^(\d+)_(\d{4}-\d{2}-\d{2})\.(?:jpe?g|png|webp|avif)$/i',
            basename($ruta),
        ),
    ));
}

test('the seeder imports the delivery photos once', function () {
    $this->seed(EntregaSeeder::class);

    $cargadas = entregasEnLaSemilla();

    expect($cargadas)->toBeGreaterThan(0)
        ->and(Entrega::count())->toBe($cargadas);

    // Idempotente: correrlo de nuevo no duplica ni filas ni archivos.
    $this->seed(EntregaSeeder::class);

    expect(Entrega::count())->toBe($cargadas);

    Entrega::all()->each(fn (Entrega $entrega) => Storage::disk('public')->assertExists($entrega->ruta));
});

test('the seed filename carries the delivery date', function () {
    $this->seed(EntregaSeeder::class);

    $entrega = Entrega::where('ruta', 'entregas/01_2025-10-28.jpg')->sole();

    expect($entrega->fecha->toDateString())->toBe('2025-10-28');
});

/* El README de la carpeta no sigue la convención de nombres: se ignora. */
test('files that break the naming convention are skipped', function () {
    $this->seed(EntregaSeeder::class);

    expect(Entrega::where('ruta', 'like', '%README%')->count())->toBe(0)
        ->and(Entrega::count())->toBe(entregasEnLaSemilla());
});
