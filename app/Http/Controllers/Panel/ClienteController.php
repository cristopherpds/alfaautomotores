<?php

namespace App\Http\Controllers\Panel;

use App\Enums\Rubro;
use App\Http\Controllers\Controller;
use App\Http\Requests\Clientes\ClienteStoreRequest;
use App\Http\Requests\Clientes\ClienteUpdateRequest;
use App\Models\Cliente;
use App\Models\Turno;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Los clientes del taller y el lavadero.
 *
 * La mayoría nace sola de una reserva (`Cliente::desdeReserva()`); el alta
 * manual es para los leads que llaman o pasan sin reservar. Filtros y
 * paginación son del servidor: la lista crece sin tope.
 */
class ClienteController extends Controller implements HasMiddleware
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
            new Middleware('can:viewAny,'.Cliente::class, only: ['index']),
            new Middleware('can:view,cliente', only: ['show']),
            new Middleware('can:create,'.Cliente::class, only: ['create', 'store']),
            new Middleware('can:update,cliente', only: ['update']),
            new Middleware('can:delete,cliente', only: ['destroy']),
        ];
    }

    /**
     * List the clients, filtered.
     */
    public function index(Request $request): Response
    {
        $filtros = $this->filtros($request);
        $actor = $request->user();

        return Inertia::render('panel/clientes/index', [
            'clientes' => Cliente::filtrados($filtros)
                ->withCount('turnos')
                ->with(['turnos' => fn ($query) => $query->with('servicio')->limit(1)])
                ->paginate(self::POR_PAGINA)
                ->withQueryString()
                ->through(fn (Cliente $cliente): array => $this->toListItem($cliente)),
            'filtros' => [
                'busqueda' => $filtros['busqueda'],
                'rubro' => $filtros['rubro'],
                'novedades' => $filtros['novedades'],
            ],
            'rubros' => Rubro::options(),
            'puedeCrear' => $actor->can('create', Cliente::class),
            'puedeExportar' => $actor->can('export', Cliente::class),
        ]);
    }

    /**
     * Show the form to add a client by hand.
     */
    public function create(): Response
    {
        return Inertia::render('panel/clientes/create');
    }

    /**
     * Add a client by hand: a lead that never booked.
     */
    public function store(ClienteStoreRequest $request): RedirectResponse
    {
        $cliente = Cliente::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Cliente agregado.')]);

        return to_route('panel.clientes.show', $cliente);
    }

    /**
     * Show a client's file: contact, vehicles, notes and bookings.
     */
    public function show(Request $request, Cliente $cliente): Response
    {
        $cliente->load('turnos.servicio');
        $actor = $request->user();

        return Inertia::render('panel/clientes/show', [
            'cliente' => [
                'id' => $cliente->id,
                'nombre' => $cliente->nombre,
                'apellido' => $cliente->apellido,
                'email' => $cliente->email,
                'celular' => $cliente->celular,
                'acepta_novedades' => $cliente->acepta_novedades,
                'acepta_novedades_at' => $cliente->acepta_novedades_at?->toIso8601String(),
                'notas' => $cliente->notas,
                'creado' => $cliente->created_at?->toIso8601String(),
            ],
            'vehiculos' => $cliente->vehiculos()->all(),
            'turnos' => $cliente->turnos
                ->map(fn (Turno $turno): array => [
                    'id' => $turno->id,
                    'inicia_at' => $turno->inicia_at->toIso8601String(),
                    'servicio' => $turno->servicio->nombre,
                    'rubro' => $turno->servicio->rubro->value,
                    'rubroLabel' => $turno->servicio->rubro->label(),
                    'estado' => $turno->estado->value,
                    'estadoLabel' => $turno->estado->label(),
                    'origen' => $turno->origen->value,
                    'vehiculo' => $turno->vehiculo(),
                    'comentario' => $turno->comentario,
                ])
                ->all(),
            'can' => [
                'update' => $actor->can('update', $cliente),
                'delete' => $actor->can('delete', $cliente),
            ],
        ]);
    }

    /**
     * Update a client's contact details, notes and consent.
     */
    public function update(ClienteUpdateRequest $request, Cliente $cliente): RedirectResponse
    {
        $cliente->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Cliente actualizado.')]);

        return to_route('panel.clientes.show', $cliente);
    }

    /**
     * Delete a client. Their bookings stay, with their own copy of the data.
     */
    public function destroy(Cliente $cliente): RedirectResponse
    {
        $cliente->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Cliente eliminado.')]);

        return to_route('panel.clientes.index');
    }

    /**
     * Los filtros del listado, tal como llegan en la URL.
     *
     * @return array{busqueda: string, rubro: string, novedades: bool}
     */
    public static function filtros(Request $request): array
    {
        return [
            'busqueda' => trim($request->string('busqueda')->value()),
            'rubro' => Rubro::tryFrom($request->string('rubro')->value())?->value ?? '',
            'novedades' => $request->boolean('novedades'),
        ];
    }

    /**
     * Formatear un cliente para la tabla.
     *
     * @return array<string, mixed>
     */
    private function toListItem(Cliente $cliente): array
    {
        /** @var Turno|null $ultimo */
        $ultimo = $cliente->turnos->first();

        return [
            'id' => $cliente->id,
            'nombre' => $cliente->nombreCompleto(),
            'celular' => $cliente->celular,
            'email' => $cliente->email,
            'acepta_novedades' => $cliente->acepta_novedades,
            'turnos_count' => (int) $cliente->turnos_count,
            'ultimo' => $ultimo === null ? null : [
                'fecha' => $ultimo->inicia_at->toIso8601String(),
                'servicio' => $ultimo->servicio->nombre,
                'rubroLabel' => $ultimo->servicio->rubro->label(),
            ],
        ];
    }
}
