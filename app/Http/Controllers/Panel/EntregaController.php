<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Entregas\EntregaFechaRequest;
use App\Http\Requests\Entregas\EntregaStoreRequest;
use App\Models\Entrega;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Las fotos de la tira «Nuestros clientes» de la portada.
 *
 * Los archivos viven en el disco `public`, bajo `entregas/`. No hay orden que
 * administrar: la tira se arma por fecha, así que lo único editable de una
 * entrega es esa fecha.
 */
class EntregaController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,'.Entrega::class, only: ['index']),
            new Middleware('can:create,'.Entrega::class, only: ['store']),
            new Middleware('can:update,entrega', only: ['update']),
            new Middleware('can:delete,entrega', only: ['destroy']),
        ];
    }

    /**
     * Show the delivery photos.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('panel/entregas/index', [
            'entregas' => Entrega::ordenadas()
                ->get()
                ->map(fn (Entrega $entrega): array => ['id' => $entrega->id, ...$entrega->datos()])
                ->all(),
            /* Resuelto con la policy real: la página nunca duplica la regla en
               TypeScript. Va de página y no por fila porque el permiso depende
               del rol, no de la entrega. */
            'puedeGestionar' => $request->user()?->can('create', Entrega::class) ?? false,
            'maxPorLote' => Entrega::MAX_POR_LOTE,
        ]);
    }

    /**
     * Upload a batch of delivery photos sharing one date.
     */
    public function store(EntregaStoreRequest $request): RedirectResponse
    {
        $fecha = $request->validated('fecha');

        /** @var array<int, UploadedFile> $archivos */
        $archivos = $request->file('fotos');

        foreach ($archivos as $archivo) {
            Entrega::create([
                'ruta' => $archivo->store(Entrega::CARPETA, 'public'),
                'fecha' => $fecha,
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice(
            '{1} Entrega cargada.|[2,*] :cantidad entregas cargadas.',
            count($archivos),
            ['cantidad' => count($archivos)],
        )]);

        return back();
    }

    /**
     * Fix the date printed over one delivery.
     */
    public function update(EntregaFechaRequest $request, Entrega $entrega): RedirectResponse
    {
        $entrega->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Fecha actualizada.')]);

        return back();
    }

    /**
     * Remove one delivery from the strip, file included.
     */
    public function destroy(Entrega $entrega): RedirectResponse
    {
        $entrega->borrarConArchivo();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Entrega eliminada.')]);

        return back();
    }
}
