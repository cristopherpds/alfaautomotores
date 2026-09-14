<?php

namespace App\Concerns;

use App\Enums\Rubro;
use App\Http\Requests\Turnos\TurnoPublicoRequest;
use App\Models\Puesto;
use App\Models\Servicio;
use App\Models\Turno;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;

/**
 * La reserva de turnos desde el sitio público, compartida por los dos negocios.
 *
 * Taller y lavadero piden lo mismo —un servicio, un día y un hueco— y sólo se
 * diferencian en el rubro, que decide qué servicios se ofrecen, qué puestos
 * miran los horarios y de qué archivo de config sale la ventana de reserva.
 *
 * Los horarios libres se recalculan con una recarga parcial de Inertia sobre
 * `huecos` (`?servicio=&fecha=`) en vez de un endpoint JSON aparte: el proyecto
 * no tiene rutas de API y así la disponibilidad se pide en un solo lugar.
 */
trait AgendaPublica
{
    /**
     * El negocio del que es esta página.
     */
    abstract protected function rubro(): Rubro;

    /**
     * Reservar un horario y volver con el aviso.
     */
    protected function reservar(TurnoPublicoRequest $request): RedirectResponse
    {
        $servicio = Servicio::where('slug', $request->validated('servicio'))
            ->where('rubro', $this->rubro())
            ->sole();

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
            'matricula' => $request->validated('matricula'),
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
     * La ventana de días que ofrece la página.
     *
     * @return array{desde: string, hasta: string}
     */
    protected function ventana(): array
    {
        return [
            'desde' => $this->primerDia()->toDateString(),
            'hasta' => $this->ultimoDia()->toDateString(),
        ];
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
    protected function huecos(Request $request, Collection $servicios): array
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
        return now()->addDays((int) $this->rubro()->config('anticipacion_dias'))->startOfDay();
    }

    /**
     * El último día que se ofrece.
     */
    private function ultimoDia(): CarbonInterface
    {
        return now()->addDays((int) $this->rubro()->config('horizonte_dias'))->startOfDay();
    }
}
