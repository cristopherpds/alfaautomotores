<?php

namespace App\Http\Controllers;

use App\Concerns\AgendaPublica;
use App\Concerns\ProvidesSiteInfo;
use App\Enums\Rubro;
use App\Http\Requests\Turnos\TurnoLavaderoRequest;
use App\Models\Servicio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La página del lavadero: la lista de precios y la reserva de turnos.
 *
 * Comparte con el taller toda la mecánica de la reserva (`AgendaPublica`); lo
 * propio de acá es que los servicios son tamaños de vehículo —van sin área y
 * con precio de lista— así que la grilla no tiene filtros.
 */
class LavaderoController extends Controller
{
    use AgendaPublica;
    use ProvidesSiteInfo;

    /**
     * Show the car wash page.
     */
    public function index(Request $request): Response
    {
        $servicios = Servicio::publicos(Rubro::Lavadero);

        return Inertia::render('lavadero', [
            'site' => $this->siteInfo(),
            'servicios' => $servicios->map(fn (Servicio $servicio): array => [
                'slug' => $servicio->slug,
                'nombre' => $servicio->nombre,
                'duracion' => $servicio->duracion,
                'duracionLegible' => $servicio->duracionLegible(),
                'precioLegible' => $servicio->precioLegible(),
                'descripcion' => $servicio->descripcion,
                'foto' => $servicio->url(),
                'agendable' => $servicio->agendable,
            ])->all(),
            'ventana' => $this->ventana(),
            'huecos' => $this->huecos($request, $servicios),
        ]);
    }

    /**
     * Book a wash from the public site.
     */
    public function store(TurnoLavaderoRequest $request): RedirectResponse
    {
        return $this->reservar($request);
    }

    protected function rubro(): Rubro
    {
        return Rubro::Lavadero;
    }
}
