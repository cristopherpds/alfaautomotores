<?php

use App\Models\Cotizacion;
use App\Models\User;
use App\Notifications\NuevaCotizacion;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Notifications\DatabaseNotification;

/*
 * La limpieza diaria de la campana: se van los avisos leídos hace más de 30
 * días; los no leídos y los leídos hace poco se quedan.
 */

/**
 * Un aviso de cotización para el usuario, leído (o no) en esa fecha.
 */
function avisoLeido(User $usuario, ?DateTimeInterface $leidoEl): string
{
    $usuario->notify(new NuevaCotizacion(Cotizacion::factory()->create()));

    $aviso = $usuario->notifications()->latest()->firstOrFail();
    $aviso->forceFill(['read_at' => $leidoEl, 'created_at' => now()->subDays(60)])->save();

    return $aviso->id;
}

test('it deletes only the notices read more than 30 days ago', function () {
    $usuario = User::factory()->admin()->create();

    $viejo = avisoLeido($usuario, now()->subDays(31));
    $reciente = avisoLeido($usuario, now()->subDays(29));
    $sinLeer = avisoLeido($usuario, null);

    $this->artisan('avisos:limpiar')
        ->expectsOutput('Avisos borrados: 1.')
        ->assertSuccessful();

    expect(DatabaseNotification::pluck('id')->all())
        ->not->toContain($viejo)
        ->toContain($reciente, $sinLeer);
});

test('the cleanup runs every day', function () {
    $evento = collect(app(Schedule::class)->events())
        ->first(fn ($evento): bool => str_contains($evento->command, 'avisos:limpiar'));

    expect($evento)->not->toBeNull()
        ->and($evento->expression)->toBe('0 4 * * *');
});
