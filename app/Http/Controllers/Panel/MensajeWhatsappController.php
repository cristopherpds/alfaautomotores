<?php

namespace App\Http\Controllers\Panel;

use App\Exceptions\WhatsappException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Whatsapp\EnviarMensajeRequest;
use App\Models\Conversacion;
use App\Services\Mensajero;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Lo que el equipo escribe desde la bandeja.
 *
 * Contestar a mano no apaga la IA: para eso está «Tomar la conversación».
 * Así una persona puede meter un dato sin cortar el hilo del agente.
 */
class MensajeWhatsappController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:update,conversacion'),
        ];
    }

    /**
     * Send a text or a file to the conversation.
     */
    public function store(EnviarMensajeRequest $request, Conversacion $conversacion, Mensajero $mensajero): RedirectResponse
    {
        $cuerpo = $request->validated('cuerpo');

        try {
            if ($request->hasFile('archivo')) {
                $mensajero->archivo($conversacion, $request->file('archivo'), $cuerpo, $request->user());
            } else {
                $mensajero->texto($conversacion, (string) $cuerpo, $request->user());
            }
        } catch (WhatsappException $error) {
            return back()->withErrors(['cuerpo' => $error->paraElPanel()]);
        }

        return back();
    }
}
