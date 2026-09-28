<?php

namespace App\Http\Controllers;

use App\Concerns\ProvidesSiteInfo;
use App\Models\Producto;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Las dos grillas del catálogo de movilidad y la ficha de un producto.
 *
 * Calcado de `VehiculoController`: el catálogo entero viaja al cliente y los
 * filtros y la paginación son de navegador, sin ida y vuelta al servidor. Son
 * unas decenas de filas, no hace falta más.
 */
class ProductoController extends Controller
{
    use ProvidesSiteInfo;

    /**
     * Motos, scooters, monopatines, triciclos y bicicletas eléctricas.
     */
    public function movilidad(): Response
    {
        return Inertia::render('movilidad', [
            'site' => $this->siteInfo(),
            'productos' => Producto::deSeccion(bicicletas: false),
        ]);
    }

    /**
     * Las bicicletas sin motor, que se publican sin precio.
     */
    public function bicicletas(): Response
    {
        return Inertia::render('bicicletas', [
            'site' => $this->siteInfo(),
            'productos' => Producto::deSeccion(bicicletas: true),
        ]);
    }

    /**
     * Show a single product.
     */
    public function show(string $slug): Response
    {
        $producto = Producto::buscar($slug);

        if ($producto === null) {
            throw new NotFoundHttpException;
        }

        return Inertia::render('productos/show', [
            'site' => $this->siteInfo(),
            'producto' => $producto,
            'similares' => Producto::similares($producto),
        ]);
    }
}
