<?php

namespace App\Http\Controllers;

use App\Concerns\AgendaPublica;
use App\Concerns\ProvidesSiteInfo;
use App\Enums\AreaServicio;
use App\Enums\Rubro;
use App\Http\Requests\Turnos\TurnoTallerRequest;
use App\Models\Servicio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La página del taller: los servicios y la reserva de turnos.
 *
 * La mecánica de la reserva vive en `AgendaPublica`, que comparte con el
 * lavadero. Lo propio del taller es la grilla filtrada por área.
 */
class TallerController extends Controller
{
    use AgendaPublica;
    use ProvidesSiteInfo;

    /**
     * Show the workshop page.
     */
    public function index(Request $request): Response
    {
        $servicios = Servicio::publicos(Rubro::Taller);

        return Inertia::render('taller', [
            'site' => $this->siteInfo(),
            'servicios' => $servicios->map(fn (Servicio $servicio): array => [
                'slug' => $servicio->slug,
                'nombre' => $servicio->nombre,
                'area' => $servicio->area?->value,
                'areaLabel' => $servicio->area?->label(),
                'duracion' => $servicio->duracion,
                'duracionLegible' => $servicio->duracionLegible(),
                'precioLegible' => $servicio->precioLegible(),
                'descripcion' => $servicio->descripcion,
                'foto' => $servicio->url(),
                'agendable' => $servicio->agendable,
            ])->all(),
            'areas' => AreaServicio::options(),
            'ventana' => $this->ventana(),
            'huecos' => $this->huecos($request, $servicios),
        ]);
    }

    /**
     * Book a slot from the public site.
     */
    public function store(TurnoTallerRequest $request): RedirectResponse
    {
        return $this->reservar($request);
    }

    protected function rubro(): Rubro
    {
        return Rubro::Taller;
    }
}
