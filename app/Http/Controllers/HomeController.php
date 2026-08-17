<?php

namespace App\Http\Controllers;

use App\Concerns\ProvidesSiteInfo;
use App\Models\Vehiculo;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    use ProvidesSiteInfo;

    /**
     * Show the public landing page.
     */
    public function index(): Response
    {
        return Inertia::render('welcome', [
            'site' => $this->siteInfo(),
            'destacados' => Vehiculo::destacados(),
            'totalStock' => Vehiculo::contar(),
        ]);
    }
}
