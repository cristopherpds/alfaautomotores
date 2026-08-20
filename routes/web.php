<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\Panel\VehiculoController as PanelVehiculoController;
use App\Http\Controllers\Panel\VehiculoImagenController;
use App\Http\Controllers\Panel\VehiculoLoteController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VehiculoController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('catalogo', [VehiculoController::class, 'index'])->name('catalogo');
Route::get('vehiculos/{slug}', [VehiculoController::class, 'show'])->name('vehiculos.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');

    Route::resource('users', UserController::class)->except('show');

    /* El panel va bajo `panel/` porque `vehiculos.show` y `/vehiculos/{slug}`
       ya son del sitio público. */
    Route::prefix('panel')->name('panel.')->group(function () {
        /* Antes del resource a propósito: `vehiculos/{vehiculo}` matchea
           `vehiculos/lote`, y como el binding es por id daría 404. */
        Route::patch('vehiculos/lote/estado', [VehiculoLoteController::class, 'estado'])
            ->name('vehiculos.lote.estado');
        Route::delete('vehiculos/lote', [VehiculoLoteController::class, 'destroy'])
            ->name('vehiculos.lote.destroy');

        Route::resource('vehiculos', PanelVehiculoController::class)->except('show');

        Route::patch('vehiculos/{vehiculo}/destacado', [PanelVehiculoController::class, 'destacado'])
            ->name('vehiculos.destacado');

        Route::post('vehiculos/{vehiculo}/imagenes', [VehiculoImagenController::class, 'store'])
            ->name('vehiculos.imagenes.store');
        Route::patch('vehiculos/{vehiculo}/imagenes/orden', [VehiculoImagenController::class, 'orden'])
            ->name('vehiculos.imagenes.orden');
        Route::delete('vehiculos/{vehiculo}/imagenes/{imagen}', [VehiculoImagenController::class, 'destroy'])
            ->name('vehiculos.imagenes.destroy');
    });
});

require __DIR__.'/settings.php';
