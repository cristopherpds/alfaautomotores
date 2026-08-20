<?php

namespace App\Http\Controllers\Panel;

use App\Enums\EstadoVehiculo;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vehiculos\VehiculoLoteDestroyRequest;
use App\Http\Requests\Vehiculos\VehiculoLoteEstadoRequest;
use App\Models\Vehiculo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Acciones sobre varios vehículos a la vez, desde la tabla del panel.
 *
 * Los dos métodos recorren los modelos y los guardan o borran de a uno en vez
 * de resolver el lote con una sola query. No es un descuido: `Vehiculo` tiene
 * hooks `saving` (apaga `destacado` cuando el vehículo pasa a borrador) y
 * `deleting` (borra la carpeta de fotos), y los updates y deletes del query
 * builder no disparan eventos de modelo. Con una query masiva quedarían
 * borradores destacados en la portada y carpetas de fotos huérfanas.
 */
class VehiculoLoteController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     *
     * Las abilities son las de clase (`updateAny`/`deleteAny`): la ruta del
     * lote no tiene `{vehiculo}` que resolver.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:updateAny,'.Vehiculo::class, only: ['estado']),
            new Middleware('can:deleteAny,'.Vehiculo::class, only: ['destroy']),
        ];
    }

    /**
     * Move every selected vehicle to the given state.
     */
    public function estado(VehiculoLoteEstadoRequest $request): RedirectResponse
    {
        $estado = EstadoVehiculo::from($request->validated('estado'));

        $vehiculos = Vehiculo::findMany($request->validated('vehiculos'));

        DB::transaction(function () use ($vehiculos, $estado): void {
            foreach ($vehiculos as $vehiculo) {
                $vehiculo->estado = $estado;
                $vehiculo->save();
            }
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => trans_choice(
                '{1} 1 vehículo pasó a :estado.|[2,*] :cantidad vehículos pasaron a :estado.',
                $vehiculos->count(),
                ['cantidad' => $vehiculos->count(), 'estado' => Str::lcfirst($estado->label())],
            ),
        ]);

        return back();
    }

    /**
     * Remove every selected vehicle from the stock, photos included.
     */
    public function destroy(VehiculoLoteDestroyRequest $request): RedirectResponse
    {
        $vehiculos = Vehiculo::findMany($request->validated('vehiculos'));

        DB::transaction(function () use ($vehiculos): void {
            foreach ($vehiculos as $vehiculo) {
                $vehiculo->delete();
            }
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => trans_choice(
                '{1} 1 vehículo eliminado.|[2,*] :cantidad vehículos eliminados.',
                $vehiculos->count(),
                ['cantidad' => $vehiculos->count()],
            ),
        ]);

        return back();
    }
}
