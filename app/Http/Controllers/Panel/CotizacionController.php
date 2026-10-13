<?php

namespace App\Http\Controllers\Panel;

use App\Enums\EstadoCotizacion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cotizaciones\CotizacionEstadoRequest;
use App\Models\Cotizacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Las solicitudes de «Cotizá tu auto» que llegan del sitio público.
 *
 * No hay alta ni edición: nacen en la web. Desde acá se miran, se marca por
 * dónde van y se borran. Las fotos están en el disco privado y se sirven por
 * `foto()`, detrás del login.
 */
class CotizacionController extends Controller implements HasMiddleware
{
    private const POR_PAGINA = 50;

    /**
     * Get the middleware that should be assigned to the controller.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,'.Cotizacion::class, only: ['index']),
            new Middleware('can:view,cotizacion', only: ['show', 'foto']),
            new Middleware('can:update,cotizacion', only: ['estado']),
            new Middleware('can:delete,cotizacion', only: ['destroy']),
        ];
    }

    /**
     * List the quote requests, newest first.
     */
    public function index(Request $request): Response
    {
        $estado = EstadoCotizacion::tryFrom($request->string('estado')->value());

        return Inertia::render('panel/cotizaciones/index', [
            'cotizaciones' => Cotizacion::query()
                ->when($estado, fn ($query, EstadoCotizacion $estado) => $query->where('estado', $estado))
                ->latest()
                ->orderByDesc('id')
                ->paginate(self::POR_PAGINA)
                ->withQueryString()
                ->through(fn (Cotizacion $cotizacion): array => [
                    'id' => $cotizacion->id,
                    'vehiculo' => $cotizacion->vehiculo(),
                    'kilometros' => $cotizacion->kilometros,
                    'nombre' => $cotizacion->nombreCompleto(),
                    'celular' => $cotizacion->celular,
                    'fotos' => count($cotizacion->ladosConFoto()),
                    'estado' => $cotizacion->estado->value,
                    'estadoLabel' => $cotizacion->estado->label(),
                    'creada' => $cotizacion->created_at?->toIso8601String(),
                ]),
            'filtros' => ['estado' => $estado->value ?? ''],
            'estados' => EstadoCotizacion::options(),
        ]);
    }

    /**
     * Show one quote request with its photos.
     */
    public function show(Request $request, Cotizacion $cotizacion): Response
    {
        $actor = $request->user();

        return Inertia::render('panel/cotizaciones/show', [
            'cotizacion' => [
                'id' => $cotizacion->id,
                'marca' => $cotizacion->marca,
                'modelo' => $cotizacion->modelo,
                'anio' => $cotizacion->anio,
                'kilometros' => $cotizacion->kilometros,
                'detalles' => $cotizacion->detalles,
                'nombre' => $cotizacion->nombreCompleto(),
                'celular' => $cotizacion->celular,
                'clienteId' => $cotizacion->cliente_id,
                'estado' => $cotizacion->estado->value,
                'creada' => $cotizacion->created_at?->toIso8601String(),
                'fotos' => array_map(fn (string $lado): array => [
                    'lado' => $lado,
                    'label' => Cotizacion::FOTOS[$lado],
                    'url' => route('panel.cotizaciones.foto', [$cotizacion, $lado]),
                ], $cotizacion->ladosConFoto()),
            ],
            'estados' => EstadoCotizacion::options(),
            'can' => [
                'update' => $actor->can('update', $cotizacion),
                'delete' => $actor->can('delete', $cotizacion),
            ],
        ]);
    }

    /**
     * Serve one of the private photos.
     */
    public function foto(Cotizacion $cotizacion, string $lado): StreamedResponse
    {
        $ruta = $cotizacion->rutaDeFoto($lado);

        abort_if($ruta === null || ! Storage::disk('local')->exists($ruta), 404);

        return Storage::disk('local')->response($ruta);
    }

    /**
     * Move a quote request along.
     */
    public function estado(CotizacionEstadoRequest $request, Cotizacion $cotizacion): RedirectResponse
    {
        $cotizacion->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Estado actualizado.')]);

        return back();
    }

    /**
     * Delete a quote request, photos included.
     */
    public function destroy(Cotizacion $cotizacion): RedirectResponse
    {
        $cotizacion->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Cotización eliminada.')]);

        return to_route('panel.cotizaciones.index');
    }
}
