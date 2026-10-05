<?php

namespace App\Http\Controllers;

use App\Concerns\ProvidesSiteInfo;
use Inertia\Inertia;
use Inertia\Response;

/**
 * La política de privacidad del sitio público (Ley 18.331). El texto vive en
 * la página; desde acá solo viajan los datos del local que cita.
 */
class PoliticaPrivacidadController extends Controller
{
    use ProvidesSiteInfo;

    /**
     * Show the privacy policy page.
     */
    public function __invoke(): Response
    {
        return Inertia::render('politica-privacidad', [
            'site' => $this->siteInfo(),
        ]);
    }
}
