<?php

namespace App\Http\Controllers\Panel;

use App\Enums\EstadoTurno;
use App\Enums\OrigenTurno;
use App\Enums\Rubro;
use App\Http\Controllers\Controller;
use App\Http\Requests\Turnos\TurnoEstadoRequest;
use App\Http\Requests\Turnos\TurnoPanelRequest;
use App\Models\Puesto;
use App\Models\Servicio;
use App\Models\Turno;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La agenda: el calendario y el alta manual.
 *
 * Es una sola para los dos negocios. El mostrador atiende taller y lavadero,
 * así que ve el día completo y filtra por rubro cuando quiere; hacer dos
 * agendas sería duplicar controlador, página y permisos para mostrar lo mismo.
 *
 * Los turnos viajan ya con forma de evento de FullCalendar. El rango visible lo
 * manda el calendario por query (`desde`/`hasta`, más `rubro`) y se pide con una
 * recarga parcial de `turnos` al cambiar de mes o de filtro.
 *
 * El alta desde el panel no tiene la ventana de la web: el mostrador puede
 * cargar un turno para hoy mismo, y entra ya confirmado.
 */
class TurnoController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,'.Turno::class, only: ['index']),
            new Middleware('can:create,'.Turno::class, only: ['store', 'huecos']),
            new Middleware('can:update,turno', only: ['estado']),
            new Middleware('can:delete,turno', only: ['destroy']),
        ];
    }

    /**
     * Show the calendar.
     */
    public function index(Request $request): Response
    {
        /** @var User $actor */
        $actor = $request->user();

        $desde = $this->fecha($request->string('desde')->value()) ?? now()->startOfMonth();
        $hasta = $this->fecha($request->string('hasta')->value()) ?? now()->endOfMonth();

        $rubro = Rubro::tryFrom($request->string('rubro')->value());

        return Inertia::render('panel/turnos/index', [
            'turnos' => Turno::entre($desde->startOfDay(), $hasta->endOfDay())
                ->when($rubro, fn ($consulta) => $consulta->whereRelation('servicio', 'rubro', $rubro))
                ->get()
                ->map(fn (Turno $turno): array => $this->toEvento($turno))
                ->all(),
            'servicios' => Servicio::query()
                ->where('activo', true)
                ->where('agendable', true)
                ->orderBy('nombre')
                ->get()
                ->map(fn (Servicio $servicio): array => [
                    'slug' => $servicio->slug,
                    'nombre' => $servicio->nombre,
                    'rubro' => $servicio->rubro->value,
                    'rubroLabel' => $servicio->rubro->label(),
                    'duracionLegible' => $servicio->duracionLegible(),
                ])
                ->all(),
            'estados' => EstadoTurno::options(),
            'rubros' => Rubro::options(),
            'rubro' => $rubro?->value,
            'puedeGestionar' => $actor->can('create', Turno::class),
            'huecos' => $this->huecosPedidos($request),
        ]);
    }

    /**
     * Book an appointment from the panel.
     */
    public function store(TurnoPanelRequest $request): RedirectResponse
    {
        $servicio = Servicio::where('slug', $request->validated('servicio'))->sole();

        $inicio = Date::parse($request->validated('fecha').' '.$request->validated('hora'));

        $turno = Turno::reservar(
            $servicio,
            $inicio,
            [
                'nombre' => $request->validated('nombre'),
                'apellido' => $request->validated('apellido'),
                'email' => $request->validated('email'),
                'celular' => $request->validated('celular'),
                'vehiculo_marca' => $request->validated('vehiculo_marca'),
                'vehiculo_modelo' => $request->validated('vehiculo_modelo'),
                'vehiculo_anio' => $request->validated('vehiculo_anio'),
                'matricula' => $request->validated('matricula'),
                'comentario' => $request->validated('comentario'),
            ],
            EstadoTurno::Confirmado,
            OrigenTurno::Panel,
        );

        if ($turno === null) {
            return back()->withErrors(['hora' => __('Ese horario se acaba de ocupar.')]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Turno agendado.')]);

        return back();
    }

    /**
     * Move an appointment to another state.
     */
    public function estado(TurnoEstadoRequest $request, Turno $turno): RedirectResponse
    {
        $estado = EstadoTurno::from($request->validated('estado'));

        /* Cancelar es lo único que toca la agenda: borra la cita y el horario
           vuelve a ofrecerse en la web. */
        if ($estado === EstadoTurno::Cancelado) {
            $turno->cancelar();
        } else {
            $turno->estado = $estado;
            $turno->save();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Turno :estado.', ['estado' => mb_strtolower($estado->label())])]);

        return back();
    }

    /**
     * Delete an appointment for good.
     */
    public function destroy(Turno $turno): RedirectResponse
    {
        $turno->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Turno eliminado.')]);

        return back();
    }

    /**
     * Los horarios libres para el alta manual.
     *
     * @return list<string>
     */
    private function huecosPedidos(Request $request): array
    {
        $servicio = Servicio::query()
            ->where('slug', $request->string('servicio')->value())
            ->where('agendable', true)
            ->first();

        $fecha = $this->fecha($request->string('fecha')->value());

        if ($servicio === null || $fecha === null) {
            return [];
        }

        return Puesto::huecosDelDia($fecha->startOfDay(), $servicio);
    }

    /**
     * Leer una fecha `aaaa-mm-dd` de la query, o `null` si no vino o no sirve.
     */
    private function fecha(string $valor): ?CarbonInterface
    {
        if ($valor === '') {
            return null;
        }

        return rescue(
            fn (): CarbonInterface => Date::createFromFormat('Y-m-d', $valor),
            null,
            report: false,
        );
    }

    /**
     * El turno con forma de evento de FullCalendar.
     *
     * @return array{id: string, title: string, start: string, end: string, classNames: list<string>, extendedProps: array<string, mixed>}
     */
    private function toEvento(Turno $turno): array
    {
        return [
            'id' => (string) $turno->id,
            'title' => $turno->servicio->nombre.' · '.$turno->apellido,
            'start' => $turno->inicia_at->toIso8601String(),
            'end' => $turno->termina_at->toIso8601String(),
            /* Dos señales que no se pisan: el fondo dice el estado y la barra
               de la izquierda, el rubro. Ver `resources/css/app.css`. */
            'classNames' => ['turno--'.$turno->estado->value, 'turno--'.$turno->servicio->rubro->value],
            'extendedProps' => [
                'estado' => $turno->estado->value,
                'estadoLabel' => $turno->estado->label(),
                'origen' => $turno->origen->value,
                'rubro' => $turno->servicio->rubro->value,
                'rubroLabel' => $turno->servicio->rubro->label(),
                'servicio' => $turno->servicio->nombre,
                'duracionLegible' => $turno->servicio->duracionLegible(),
                'puesto' => $turno->puesto->nombre,
                'cliente' => $turno->cliente(),
                'email' => $turno->email,
                'celular' => $turno->celular,
                'vehiculo' => $turno->vehiculo(),
                'matricula' => $turno->matricula,
                'comentario' => $turno->comentario,
            ],
        ];
    }
}
