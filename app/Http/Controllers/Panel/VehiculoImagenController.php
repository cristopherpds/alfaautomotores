<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vehiculos\VehiculoImagenOrdenRequest;
use App\Http\Requests\Vehiculos\VehiculoImagenStoreRequest;
use App\Models\Vehiculo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;

/**
 * Galería de fotos de un vehículo.
 *
 * Los archivos viven en el disco `public`, bajo `vehiculos/{id}/`. La portada
 * es siempre la foto de menor `orden`, así que reordenar y elegir portada son
 * la misma operación.
 */
class VehiculoImagenController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:update,vehiculo'),
        ];
    }

    /**
     * Add photos to the vehicle gallery.
     */
    public function store(VehiculoImagenStoreRequest $request, Vehiculo $vehiculo): RedirectResponse
    {
        $ultimo = $vehiculo->fotos()->max('orden');
        $siguiente = $ultimo === null ? 0 : ((int) $ultimo) + 1;

        /** @var array<int, UploadedFile> $archivos */
        $archivos = $request->file('imagenes');

        foreach ($archivos as $archivo) {
            $vehiculo->fotos()->create([
                'ruta' => $archivo->store($vehiculo->carpetaDeFotos(), 'public'),
                'orden' => $siguiente++,
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Fotos cargadas.')]);

        return back();
    }

    /**
     * Reorder the gallery. The first photo becomes the cover.
     */
    public function orden(VehiculoImagenOrdenRequest $request, Vehiculo $vehiculo): RedirectResponse
    {
        foreach ($request->validated('imagenes') as $posicion => $imagenId) {
            $vehiculo->fotos()->whereKey($imagenId)->update(['orden' => $posicion]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Galería reordenada.')]);

        return back();
    }

    /**
     * Remove one photo from the gallery, file included.
     */
    public function destroy(Vehiculo $vehiculo, int $imagen): RedirectResponse
    {
        $vehiculo->fotos()->findOrFail($imagen)->borrarConArchivo();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Foto eliminada.')]);

        return back();
    }
}
