<?php

namespace App\Http\Controllers\Panel;

use App\Enums\Combustible;
use App\Enums\EstadoVehiculo;
use App\Enums\Moneda;
use App\Enums\TipoVehiculo;
use App\Enums\Transmision;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vehiculos\DestacarVehiculoRequest;
use App\Http\Requests\Vehiculos\VehiculoStoreRequest;
use App\Http\Requests\Vehiculos\VehiculoUpdateRequest;
use App\Models\User;
use App\Models\Vehiculo;
use App\Models\VehiculoImagen;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class VehiculoController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,'.Vehiculo::class, only: ['index']),
            new Middleware('can:create,'.Vehiculo::class, only: ['create', 'store']),
            new Middleware('can:update,vehiculo', only: ['edit', 'update', 'destacado']),
            new Middleware('can:delete,vehiculo', only: ['destroy']),
        ];
    }

    /**
     * Show the whole stock, drafts included.
     */
    public function index(Request $request): Response
    {
        $actor = $request->user();

        return Inertia::render('panel/vehiculos/index', [
            'vehiculos' => Vehiculo::query()
                ->with('fotos')
                ->withCount('fotos')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get()
                ->map(fn (Vehiculo $vehiculo): array => $this->toListItem($vehiculo, $actor))
                ->all(),
            'puedeCrear' => $actor->can('create', Vehiculo::class),
            'maxDestacados' => Vehiculo::MAX_DESTACADOS,
        ]);
    }

    /**
     * Show the form to add a vehicle to the stock.
     */
    public function create(): Response
    {
        return Inertia::render('panel/vehiculos/create', [
            'opciones' => $this->opciones(),
        ]);
    }

    /**
     * Add a vehicle to the stock.
     *
     * Se vuelve a la edición y no al listado: recién ahí se le pueden cargar
     * las fotos, que necesitan que el vehículo ya exista.
     */
    public function store(VehiculoStoreRequest $request): RedirectResponse
    {
        $vehiculo = Vehiculo::create($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Vehículo creado. Ahora podés cargarle las fotos.'),
        ]);

        return to_route('panel.vehiculos.edit', $vehiculo);
    }

    /**
     * Show the form to edit a vehicle, along with its gallery.
     */
    public function edit(Vehiculo $vehiculo): Response
    {
        $vehiculo->load('fotos');

        return Inertia::render('panel/vehiculos/edit', [
            'vehiculo' => $this->toFormItem($vehiculo),
            'opciones' => $this->opciones(),
            'maxImagenes' => Vehiculo::MAX_IMAGENES,
        ]);
    }

    /**
     * Update the given vehicle.
     */
    public function update(VehiculoUpdateRequest $request, Vehiculo $vehiculo): RedirectResponse
    {
        $vehiculo->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Vehículo actualizado.')]);

        return to_route('panel.vehiculos.index');
    }

    /**
     * Pin or unpin the vehicle from the landing page.
     *
     * `destacado` queda fuera del Fillable a propósito: sólo se cambia por acá,
     * donde el tope ya pasó por la validación.
     */
    public function destacado(DestacarVehiculoRequest $request, Vehiculo $vehiculo): RedirectResponse
    {
        $vehiculo->destacado = $request->boolean('destacado');
        $vehiculo->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $vehiculo->destacado
                ? __('Vehículo destacado en la portada.')
                : __('Vehículo quitado de los destacados.'),
        ]);

        return back();
    }

    /**
     * Remove the given vehicle from the stock.
     */
    public function destroy(Vehiculo $vehiculo): RedirectResponse
    {
        $vehiculo->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Vehículo eliminado.')]);

        return to_route('panel.vehiculos.index');
    }

    /**
     * Las opciones de los selectores de la ficha.
     *
     * @return array<string, array<int, array<string, string>>>
     */
    private function opciones(): array
    {
        return [
            'estados' => EstadoVehiculo::options(),
            'tipos' => TipoVehiculo::options(),
            'combustibles' => Combustible::options(),
            'transmisiones' => Transmision::options(),
            'monedas' => Moneda::options(),
        ];
    }

    /**
     * Formatear un vehículo para la tabla, resolviendo con la policy real lo
     * que el usuario puede hacer con él.
     *
     * @return array{id: int, slug: string, titulo: string, tipo: string, anio: int, km: int, precio: int, moneda: string, estado: string, destacado: bool, destacable: bool, portada: string|null, imagenes_count: int, can: array{update: bool, delete: bool}}
     */
    private function toListItem(Vehiculo $vehiculo, User $actor): array
    {
        return [
            'id' => $vehiculo->id,
            'slug' => $vehiculo->slug,
            'titulo' => $vehiculo->titulo(),
            'tipo' => $vehiculo->tipo->value,
            'anio' => $vehiculo->anio,
            'km' => $vehiculo->km,
            'precio' => $vehiculo->precio,
            'moneda' => $vehiculo->moneda->value,
            'estado' => $vehiculo->estado->value,
            'destacado' => $vehiculo->destacado,
            'destacable' => $vehiculo->esDestacable(),
            'portada' => $vehiculo->fotos->first()?->url(),
            'imagenes_count' => (int) $vehiculo->fotos_count,
            'can' => [
                'update' => $actor->can('update', $vehiculo),
                'delete' => $actor->can('delete', $vehiculo),
            ],
        ];
    }

    /**
     * Formatear un vehículo para el formulario de edición y su galería.
     *
     * @return array<string, mixed>
     */
    private function toFormItem(Vehiculo $vehiculo): array
    {
        return [
            'id' => $vehiculo->id,
            'slug' => $vehiculo->slug,
            'titulo' => $vehiculo->titulo(),
            'marca' => $vehiculo->marca,
            'modelo' => $vehiculo->modelo,
            'version' => $vehiculo->version,
            'anio' => $vehiculo->anio,
            'km' => $vehiculo->km,
            'precio' => $vehiculo->precio,
            'moneda' => $vehiculo->moneda->value,
            'comb' => $vehiculo->comb->value,
            'trans' => $vehiculo->trans->value,
            'tipo' => $vehiculo->tipo->value,
            'estado' => $vehiculo->estado->value,
            'destacado' => $vehiculo->destacado,
            'destacable' => $vehiculo->esDestacable(),
            'desc' => $vehiculo->desc,
            'fotos' => $vehiculo->fotos
                ->map(fn (VehiculoImagen $foto): array => [
                    'id' => $foto->id,
                    'url' => $foto->url(),
                ])
                ->all(),
        ];
    }
}
