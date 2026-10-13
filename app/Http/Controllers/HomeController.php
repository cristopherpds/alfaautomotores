<?php

namespace App\Http\Controllers;

use App\Concerns\ProvidesSiteInfo;
use App\Models\Entrega;
use App\Models\HeroSlide;
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
        $site = $this->siteInfo();

        return Inertia::render('welcome', [
            'site' => $site,
            'slides' => $this->slides(),
            'destacados' => Vehiculo::destacados(),
            'totalStock' => Vehiculo::contar(),
            'entregas' => Entrega::paraLaTira(),
        ]);
    }

    /**
     * Los slides del hero. Sin ninguno vigente, el de siempre: la primera
     * vista nunca queda vacía.
     *
     * @return array<int, array<string, mixed>>
     */
    private function slides(): array
    {
        $slides = HeroSlide::vigentes()->map(fn (HeroSlide $slide): array => $slide->datos());

        return $slides->isEmpty() ? [HeroSlide::porDefecto()] : $slides->all();
    }
}
