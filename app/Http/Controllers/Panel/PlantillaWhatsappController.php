<?php

namespace App\Http\Controllers\Panel;

use App\Exceptions\WhatsappException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Whatsapp\EnviarPlantillaRequest;
use App\Models\Conversacion;
use App\Models\PlantillaWhatsapp;
use App\Services\Mensajero;
use App\Services\WhatsappCloud;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;

/**
 * Las plantillas de WhatsApp: traerlas de Meta y mandarlas.
 *
 * Se crean y se aprueban en el administrador de Meta, no acá. Mandar una es
 * la única forma de volver a escribirle a alguien pasadas las 24 h.
 */
class PlantillaWhatsappController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:update,conversacion', only: ['enviar']),
            new Middleware('can:administrar,'.Conversacion::class, only: ['sincronizar']),
        ];
    }

    /**
     * Send an approved template to the conversation.
     */
    public function enviar(EnviarPlantillaRequest $request, Conversacion $conversacion, Mensajero $mensajero): RedirectResponse
    {
        try {
            $mensajero->plantilla(
                $conversacion,
                $request->plantilla(),
                array_values(array_map('strval', $request->validated('variables', []))),
                $request->user(),
            );
        } catch (WhatsappException $error) {
            return back()->withErrors(['plantilla' => $error->paraElPanel()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Plantilla enviada.')]);

        return back();
    }

    /**
     * Bring the account templates from Meta.
     */
    public function sincronizar(WhatsappCloud $whatsapp): RedirectResponse
    {
        try {
            $cantidad = PlantillaWhatsapp::sincronizar($whatsapp->plantillas());
        } catch (WhatsappException $error) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $error->paraElPanel()]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice(
            '{0} No hay plantillas en la cuenta.|{1} Una plantilla sincronizada.|[2,*] :cantidad plantillas sincronizadas.',
            $cantidad,
            ['cantidad' => $cantidad],
        )]);

        return back();
    }
}
