<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ajustes\AjusteWhatsappRequest;
use App\Models\Ajuste;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Los ajustes del sitio público que se cambian desde el panel.
 */
class AjusteController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:update,'.Ajuste::class),
        ];
    }

    /**
     * Show the WhatsApp button settings, with a preview.
     */
    public function whatsapp(): Response
    {
        return Inertia::render('panel/ajustes/whatsapp', [
            'boton' => Ajuste::botonWhatsapp(),
            'maxContactos' => AjusteWhatsappRequest::MAX_CONTACTOS,
        ]);
    }

    /**
     * Save the WhatsApp button settings.
     */
    public function guardarWhatsapp(AjusteWhatsappRequest $request): RedirectResponse
    {
        /** @var list<array<string, mixed>> $contactos */
        $contactos = $request->validated('contactos');

        Ajuste::guardar(Ajuste::BOTON_WHATSAPP, [
            'activo' => $request->boolean('activo'),
            'titulo' => $request->validated('titulo'),
            'subtitulo' => $request->validated('subtitulo'),
            'contactos' => array_map(fn (array $contacto): array => [
                'nombre' => $contacto['nombre'],
                'detalle' => $contacto['detalle'] ?? null,
                'numero' => $contacto['numero'],
                'mensaje' => $contacto['mensaje'],
                'respaldo' => filter_var($contacto['respaldo'], FILTER_VALIDATE_BOOLEAN),
                'horario' => [
                    'siempre' => filter_var($contacto['horario']['siempre'], FILTER_VALIDATE_BOOLEAN),
                    'dias' => array_values(array_unique(array_map('intval', $contacto['horario']['dias'] ?? []))),
                    'desde' => $contacto['horario']['desde'],
                    'hasta' => $contacto['horario']['hasta'],
                ],
            ], array_values($contactos)),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Botón de WhatsApp actualizado.')]);

        return back();
    }
}
