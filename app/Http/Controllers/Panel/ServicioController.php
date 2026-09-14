<?php

namespace App\Http\Controllers\Panel;

use App\Enums\AreaServicio;
use App\Http\Controllers\Controller;
use App\Http\Requests\Taller\ServicioRequest;
use App\Models\Servicio;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Los trabajos que ofrece el taller.
 *
 * `duracion` es lo que mide el turno, así que cambiarla acá cambia los horarios
 * que la web ofrece a partir de la próxima consulta. Un servicio con turnos no
 * se puede borrar —lo impide la clave foránea—: se desactiva.
 */
class ServicioController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,'.Servicio::class, only: ['index']),
            new Middleware('can:create,'.Servicio::class, only: ['create', 'store']),
            new Middleware('can:update,servicio', only: ['edit', 'update']),
            new Middleware('can:delete,servicio', only: ['destroy']),
        ];
    }

    /**
     * Show the workshop services.
     */
    public function index(Request $request): Response
    {
        /** @var User $actor */
        $actor = $request->user();

        return Inertia::render('panel/servicios/index', [
            'servicios' => Servicio::query()
                ->withCount('turnos')
                ->orderBy('orden')
                ->orderBy('nombre')
                ->get()
                ->map(fn (Servicio $servicio): array => $this->toListItem($servicio))
                ->all(),
            'puedeGestionar' => $actor->can('create', Servicio::class),
        ]);
    }

    /**
     * Show the form to add a service.
     */
    public function create(): Response
    {
        return Inertia::render('panel/servicios/create', [
            'areas' => AreaServicio::options(),
        ]);
    }

    /**
     * Store a new service.
     */
    public function store(ServicioRequest $request): RedirectResponse
    {
        $servicio = new Servicio($request->validated());

        if ($request->hasFile('foto')) {
            $servicio->foto = $this->guardarFoto($request);
        }

        $servicio->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Servicio creado.')]);

        return to_route('panel.servicios.index');
    }

    /**
     * Show the form to edit a service.
     */
    public function edit(Servicio $servicio): Response
    {
        return Inertia::render('panel/servicios/edit', [
            'servicio' => [
                ...$this->toListItem($servicio->loadCount('turnos')),
                'descripcion' => $servicio->descripcion,
            ],
            'areas' => AreaServicio::options(),
        ]);
    }

    /**
     * Update a service.
     */
    public function update(ServicioRequest $request, Servicio $servicio): RedirectResponse
    {
        $servicio->fill($request->validated());

        if ($request->hasFile('foto')) {
            /* La foto vieja se va con la nueva: si no, quedan huérfanas en el
               disco sin que nada las nombre. */
            if ($servicio->getOriginal('foto') !== null) {
                Storage::disk('public')->delete((string) $servicio->getOriginal('foto'));
            }

            $servicio->foto = $this->guardarFoto($request);
        }

        $servicio->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Servicio actualizado.')]);

        return to_route('panel.servicios.index');
    }

    /**
     * Remove a service that never got booked.
     */
    public function destroy(Servicio $servicio): RedirectResponse
    {
        /* Los turnos guardan qué se hizo: borrar el servicio dejaría la agenda
           sin nombre. La FK ya lo impide; esto da el mensaje. */
        if ($servicio->turnos()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Ese servicio ya tiene turnos: desactivalo en vez de borrarlo.')]);

            return back();
        }

        $servicio->borrarConArchivo();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Servicio eliminado.')]);

        return to_route('panel.servicios.index');
    }

    /**
     * Guardar la foto en el disco y devolver su ruta.
     *
     * `store()` devuelve `false` si el disco falla: quedarse callado dejaría un
     * servicio sin foto y sin explicación.
     */
    private function guardarFoto(ServicioRequest $request): string
    {
        $ruta = $request->file('foto')->store('servicios', 'public');

        if ($ruta === false) {
            throw new RuntimeException('No se pudo guardar la foto del servicio.');
        }

        return $ruta;
    }

    /**
     * La fila que consume la tabla del panel.
     *
     * @return array{id: int, slug: string, nombre: string, area: string, areaLabel: string, duracion: int, duracionLegible: string, foto: string|null, activo: bool, agendable: bool, orden: int, turnos_count: int}
     */
    private function toListItem(Servicio $servicio): array
    {
        return [
            'id' => $servicio->id,
            'slug' => $servicio->slug,
            'nombre' => $servicio->nombre,
            'area' => $servicio->area->value,
            'areaLabel' => $servicio->area->label(),
            'duracion' => $servicio->duracion,
            'duracionLegible' => $servicio->duracionLegible(),
            'foto' => $servicio->url(),
            'activo' => $servicio->activo,
            'agendable' => $servicio->agendable,
            'orden' => $servicio->orden,
            'turnos_count' => (int) ($servicio->turnos_count ?? 0),
        ];
    }
}
