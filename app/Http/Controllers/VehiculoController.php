<?php

namespace App\Http\Controllers;

use App\Concerns\ProvidesSiteInfo;
use App\Models\Vehiculo;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class VehiculoController extends Controller
{
    use ProvidesSiteInfo;

    /**
     * Show the public catalogue.
     *
     * El stock entero viaja al cliente: los filtros y la paginación del
     * catálogo son de navegador, sin ida y vuelta al servidor.
     */
    public function index(): Response
    {
        return Inertia::render('catalogo', [
            'site' => $this->siteInfo(),
            'vehiculos' => Vehiculo::publicos(),
        ]);
    }

    /**
     * Show a single vehicle.
     */
    public function show(string $slug): Response
    {
        $vehiculo = Vehiculo::buscar($slug);

        if ($vehiculo === null) {
            throw new NotFoundHttpException;
        }

        return Inertia::render('vehiculos/show', [
            'site' => $this->siteInfo(),
            'vehiculo' => $vehiculo,
            'similares' => Vehiculo::similares($vehiculo),
        ]);
    }
}
