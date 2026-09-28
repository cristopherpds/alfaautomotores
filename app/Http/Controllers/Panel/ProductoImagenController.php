<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Productos\ProductoImagenOrdenRequest;
use App\Http\Requests\Productos\ProductoImagenStoreRequest;
use App\Models\Producto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;

/**
 * Galería de fotos de un producto de movilidad.
 *
 * Calcada de `VehiculoImagenController`: los archivos viven en el disco
 * `public`, bajo `productos/{id}/`, y la portada es la foto de menor `orden`.
 * El front usa el mismo componente de galería para los dos.
 */
class ProductoImagenController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:update,producto'),
        ];
    }

    /**
     * Add photos to the product gallery.
     */
    public function store(ProductoImagenStoreRequest $request, Producto $producto): RedirectResponse
    {
        $ultimo = $producto->fotos()->max('orden');
        $siguiente = $ultimo === null ? 0 : ((int) $ultimo) + 1;

        /** @var array<int, UploadedFile> $archivos */
        $archivos = $request->file('imagenes');

        foreach ($archivos as $archivo) {
            $producto->fotos()->create([
                'ruta' => $archivo->store($producto->carpetaDeFotos(), 'public'),
                'orden' => $siguiente++,
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Fotos cargadas.')]);

        return back();
    }

    /**
     * Reorder the gallery. The first photo becomes the cover.
     */
    public function orden(ProductoImagenOrdenRequest $request, Producto $producto): RedirectResponse
    {
        foreach ($request->validated('imagenes') as $posicion => $imagenId) {
            $producto->fotos()->whereKey($imagenId)->update(['orden' => $posicion]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Galería reordenada.')]);

        return back();
    }

    /**
     * Remove one photo from the gallery, file included.
     */
    public function destroy(Producto $producto, int $imagen): RedirectResponse
    {
        $producto->fotos()->findOrFail($imagen)->borrarConArchivo();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Foto eliminada.')]);

        return back();
    }
}
