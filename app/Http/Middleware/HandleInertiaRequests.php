<?php

namespace App\Http\Middleware;

use App\Models\Conversacion;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'whatsapp' => fn (): ?array => $this->avisosDeWhatsapp($request),
        ];
    }

    /**
     * Los contadores de WhatsApp para el primer pintado del menú lateral.
     *
     * Va en un closure: las recargas parciales que no lo piden (el polling
     * de la bandeja, por ejemplo) no hacen las consultas. Después los
     * mantiene al día `useAvisosWhatsapp` con su propio endpoint.
     *
     * @return array{noLeidos: int, atencionHumana: int}|null
     */
    private function avisosDeWhatsapp(Request $request): ?array
    {
        $user = $request->user();

        if ($user === null || $user->cannot('viewAny', Conversacion::class)) {
            return null;
        }

        return Conversacion::contadoresDeAvisos();
    }
}
