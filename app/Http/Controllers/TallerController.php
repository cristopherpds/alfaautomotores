<?php

namespace App\Http\Controllers;

use App\Concerns\ProvidesSiteInfo;
use App\Enums\AreaServicio;
use App\Http\Requests\Taller\TurnoStoreRequest;
use App\Models\Puesto;
use App\Models\Servicio;
use App\Models\Turno;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La página del taller: los servicios y la reserva de turnos.
 *
 * Los horarios libres se recalculan con una recarga parcial de Inertia sobre
 * `huecos` (`?servicio=&fecha=`) en vez de un endpoint JSON aparte: el proyecto
 * no tiene rutas de API y así la disponibilidad se pide en un solo lugar.
 */
class TallerController extends Controller
{
    use ProvidesSiteInfo;

    /**
     * Show the workshop page.
     */
    public function index(Request $request): Response
    {
        $servicios = Servicio::publicos();

        return Inertia::render('taller', [
            'site' => $this->siteInfo(),
            'servicios' => $servicios->map(fn (Servicio $servicio): array => [
                'slug' => $servicio->slug,
                'nombre' => $servicio->nombre,
                'area' => $servicio->area->value,
                'areaLabel' => $servicio->area->label(),
                'duracion' => $servicio->duracion,
                'duracionLegible' => $servicio->duracionLegible(),
                'descripcion' => $servicio->descripcion,
                'foto' => $servicio->url(),
                'agendable' => $servicio->agendable,
            ])->all(),
            'areas' => AreaServicio::options(),
            'ventana' => [
                'desde' => $this->primerDia()->toDateString(),
                'hasta' => $this->ultimoDia()->toDateString(),
            ],
            'huecos' => $this->huecos($request, $servicios),
        ]);
    }

    /**
     * Book a slot from the public site.
     */
    public function store(TurnoStoreRequest $request): RedirectResponse
    {
        $servicio = Servicio::where('slug', $request->validated('servicio'))->sole();

        $inicio = Date::parse(
            $request->validated('fecha').' '.$request->validated('hora')
        );

        $turno = Turno::reservar($servicio, $inicio, [
            'nombre' => $request->validated('nombre'),
            'apellido' => $request->validated('apellido'),
            'email' => $request->validated('email'),
            'celular' => $request->validated('celular'),
            'vehiculo_marca' => $request->validated('vehiculo_marca'),
            'vehiculo_modelo' => $request->validated('vehiculo_modelo'),
            'vehiculo_anio' => $request->validated('vehiculo_anio'),
            'comentario' => $request->validated('comentario'),
        ]);

        /* Entre que se pintaron los horarios y llegó este POST alguien pudo
           quedarse con el último puesto. */
        if ($turno === null) {
            return back()->withErrors([
                'hora' => __('Ese horario se acaba de ocupar. Elegí otro, por favor.'),
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Turno reservado. Te confirmamos por WhatsApp.')]);

        return back();
    }

    /**
     * Las horas libres del día y servicio pedidos.
     *
     * Sin parámetros —o con un servicio que no se agenda— devuelve una lista
     * vacía: la página arranca sin ningún día elegido.
     *
     * @param  Collection<int, Servicio>  $servicios
     * @return list<string>
     */
    private function huecos(Request $request, Collection $servicios): array
    {
        $servicio = $servicios->firstWhere('slug', $request->string('servicio')->value());

        if ($servicio === null || ! $servicio->agendable) {
            return [];
        }

        $fecha = rescue(
            fn (): CarbonInterface => Date::createFromFormat('Y-m-d', (string) $request->string('fecha'))->startOfDay(),
            null,
            report: false,
        );

        if ($fecha === null || $fecha->lt($this->primerDia()) || $fecha->gt($this->ultimoDia())) {
            return [];
        }

        return Puesto::huecosDelDia($fecha, $servicio);
    }

    /**
     * El primer día que se puede reservar: mañana.
     */
    private function primerDia(): CarbonInterface
    {
        return now()->addDays((int) config('taller.anticipacion_dias'))->startOfDay();
    }

    /**
     * El último día que se ofrece.
     */
    private function ultimoDia(): CarbonInterface
    {
        return now()->addDays((int) config('taller.horizonte_dias'))->startOfDay();
    }
}
