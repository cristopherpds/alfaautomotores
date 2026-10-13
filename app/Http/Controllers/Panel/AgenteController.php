<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Whatsapp\AgenteRequest;
use App\Http\Requests\Whatsapp\ConocimientoRequest;
use App\Models\Ajuste;
use App\Models\Conocimiento;
use App\Models\MensajeWhatsapp;
use App\Services\WhatsappCloud;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El agente de IA de WhatsApp: quién es, cómo habla y qué sabe.
 *
 * Como la pantalla Agente de Vocero. El perfil va en `ajustes`
 * (`Ajuste::AGENTE_IA`) y el conocimiento en `conocimientos`. Sólo admins:
 * lo que se cambia acá es lo que les contesta a los clientes.
 */
class AgenteController extends Controller implements HasMiddleware
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
     * Show the agent profile and its knowledge base.
     */
    public function show(WhatsappCloud $whatsapp): Response
    {
        $ultima = MensajeWhatsapp::query()->where('generado_por_ia', true)->latest('id')->first();

        return Inertia::render('panel/whatsapp/agente', [
            'perfil' => Ajuste::agenteIa(),
            'conocimientos' => Conocimiento::ordenados()
                ->get()
                ->map(fn (Conocimiento $entrada): array => [
                    'id' => $entrada->id,
                    'tipo' => $entrada->tipo,
                    'pregunta' => $entrada->pregunta,
                    'respuesta' => $entrada->respuesta,
                    'contenido' => $entrada->contenido,
                ])
                ->all(),
            /* La tarjeta «Quién responde a tus clientes» de Vocero: qué falta
               para que el agente conteste de verdad. */
            'estado' => [
                'whatsapp' => $whatsapp->configurado(),
                'ia' => filled(Ajuste::conexionIa()['api_key']),
                'modelo' => Ajuste::conexionIa()['modelo'],
                'ultimaRespuesta' => $ultima?->created_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Save the agent profile, on/off switch included.
     */
    public function update(AgenteRequest $request): RedirectResponse
    {
        Ajuste::guardar(Ajuste::AGENTE_IA, [
            ...$request->validated(),
            'activo' => $request->boolean('activo'),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $request->boolean('activo')
            ? __('Agente guardado. Ya contesta los mensajes nuevos.')
            : __('Agente guardado. Está apagado: no contesta a nadie.')]);

        return back();
    }

    /**
     * Add a knowledge entry at the end.
     */
    public function guardarConocimiento(ConocimientoRequest $request): RedirectResponse
    {
        Conocimiento::create([
            ...$request->datos(),
            'orden' => (int) Conocimiento::max('orden') + 1,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Conocimiento agregado.')]);

        return back();
    }

    /**
     * Fix a knowledge entry.
     */
    public function actualizarConocimiento(ConocimientoRequest $request, Conocimiento $conocimiento): RedirectResponse
    {
        $conocimiento->update($request->datos());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Conocimiento actualizado.')]);

        return back();
    }

    /**
     * Remove a knowledge entry.
     */
    public function borrarConocimiento(Conocimiento $conocimiento): RedirectResponse
    {
        $conocimiento->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Conocimiento eliminado.')]);

        return back();
    }
}
