<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LavaderoController;
use App\Http\Controllers\Panel\AjusteController;
use App\Http\Controllers\Panel\AuditoriaController;
use App\Http\Controllers\Panel\ClienteController;
use App\Http\Controllers\Panel\ClienteExportController;
use App\Http\Controllers\Panel\EntregaController;
use App\Http\Controllers\Panel\ProductoController as PanelProductoController;
use App\Http\Controllers\Panel\ProductoImagenController;
use App\Http\Controllers\Panel\ServicioController;
use App\Http\Controllers\Panel\TurnoController;
use App\Http\Controllers\Panel\VehiculoController as PanelVehiculoController;
use App\Http\Controllers\Panel\VehiculoImagenController;
use App\Http\Controllers\Panel\VehiculoLoteController;
use App\Http\Controllers\PoliticaPrivacidadController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\TallerController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VehiculoController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('catalogo', [VehiculoController::class, 'index'])->name('catalogo');
Route::get('vehiculos/{slug}', [VehiculoController::class, 'show'])->name('vehiculos.show');

/* El catálogo de movilidad son dos grillas sobre la misma tabla: `bicicletas`
   es la bici sin motor, que se publica sin precio, y `movilidad` todo lo que
   anda con batería. La ficha es una sola para las dos. */
Route::get('movilidad', [ProductoController::class, 'movilidad'])->name('movilidad');
Route::get('bicicletas', [ProductoController::class, 'bicicletas'])->name('bicicletas');
Route::get('productos/{slug}', [ProductoController::class, 'show'])->name('productos.show');

/* Los dos negocios que agendan. Cada uno ofrece los servicios de su rubro y
   calcula los horarios contra sus propios puestos; los huecos se piden con una
   recarga parcial sobre la misma ruta. */
Route::get('taller', [TallerController::class, 'index'])->name('taller');
Route::post('taller/turnos', [TallerController::class, 'store'])->name('taller.turnos.store');

Route::get('lavadero', [LavaderoController::class, 'index'])->name('lavadero');
Route::post('lavadero/turnos', [LavaderoController::class, 'store'])->name('lavadero.turnos.store');

Route::get('politica-de-privacidad', PoliticaPrivacidadController::class)->name('politica-privacidad');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

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

        /* Movilidad y bicicletas son un solo ABM: el índice filtra la sección
           con `?seccion=`. Van bajo `panel/` porque `productos/{slug}` es la
           ficha pública. */
        Route::resource('productos', PanelProductoController::class)->except('show');

        Route::post('productos/{producto}/imagenes', [ProductoImagenController::class, 'store'])
            ->name('productos.imagenes.store');
        Route::patch('productos/{producto}/imagenes/orden', [ProductoImagenController::class, 'orden'])
            ->name('productos.imagenes.orden');
        Route::delete('productos/{producto}/imagenes/{imagen}', [ProductoImagenController::class, 'destroy'])
            ->name('productos.imagenes.destroy');

        /* Las fotos de la tira «Nuestros clientes» de la portada. No hay `create`
           ni `edit`: se suben y se corrigen desde el índice. */
        Route::resource('entregas', EntregaController::class)
            ->only(['index', 'store', 'update', 'destroy']);

        /* El taller. Los servicios son un ABM normal; los turnos no tienen
           `create` ni `edit`: se cargan y se resuelven desde el calendario. */
        Route::resource('servicios', ServicioController::class)->except('show');

        Route::get('turnos', [TurnoController::class, 'index'])->name('turnos.index');
        Route::post('turnos', [TurnoController::class, 'store'])->name('turnos.store');
        Route::patch('turnos/{turno}/estado', [TurnoController::class, 'estado'])->name('turnos.estado');
        Route::delete('turnos/{turno}', [TurnoController::class, 'destroy'])->name('turnos.destroy');

        /* Los clientes nacen solos de las reservas; el alta manual es para
           los leads. `exportar` va antes del resource porque
           `clientes/{cliente}` lo tomaría como un id. */
        Route::get('clientes/exportar', ClienteExportController::class)->name('clientes.exportar');
        Route::resource('clientes', ClienteController::class)->except(['edit']);

        /* Sólo lectura: las entradas las escribe el trait `Auditable`. */
        Route::get('auditoria', [AuditoriaController::class, 'index'])->name('auditoria.index');

        /* Ajustes del sitio público, sólo para admins. */
        Route::get('ajustes/whatsapp', [AjusteController::class, 'whatsapp'])->name('ajustes.whatsapp');
        Route::put('ajustes/whatsapp', [AjusteController::class, 'guardarWhatsapp'])->name('ajustes.whatsapp.update');
    });
});

require __DIR__.'/settings.php';
