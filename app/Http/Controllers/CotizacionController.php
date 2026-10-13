<?php

namespace App\Http\Controllers;

use App\Concerns\ProvidesSiteInfo;
use App\Http\Requests\Cotizaciones\CotizacionRequest;
use App\Models\Cotizacion;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Cotizá tu auto»: el formulario para ofrecer un usado.
 *
 * La solicitud se guarda y se atiende desde `panel/cotizaciones`; no pasa por
 * WhatsApp porque las fotos no viajan en un link `wa.me`.
 */
class CotizacionController extends Controller
{
    use ProvidesSiteInfo;

    /**
     * Show the quote request form.
     */
    public function create(): Response
    {
        return Inertia::render('cotiza-tu-auto', [
            'site' => $this->siteInfo(),
            'anios' => range(now()->year + 1, CotizacionRequest::ANIO_MINIMO),
            'fotos' => collect(Cotizacion::FOTOS)
                ->map(fn (string $label, string $lado): array => ['value' => $lado, 'label' => $label])
                ->values()
                ->all(),
        ]);
    }

    /**
     * Store a quote request from the public site.
     */
    public function store(CotizacionRequest $request): RedirectResponse
    {
        $fotos = [];

        foreach (array_keys(Cotizacion::FOTOS) as $lado) {
            $fotos[$lado] = $request->file('foto_'.$lado);
        }

        Cotizacion::recibir([
            'marca' => $request->validated('marca'),
            'modelo' => $request->validated('modelo'),
            'anio' => (int) $request->validated('anio'),
            'kilometros' => (int) $request->validated('kilometros'),
            'detalles' => $request->validated('detalles'),
            'nombre' => $request->validated('nombre'),
            'apellido' => $request->validated('apellido'),
            'celular' => $request->validated('celular'),
        ], $fotos);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('¡Listo! Te contactamos a la brevedad con la cotización.')]);

        return back();
    }
}
