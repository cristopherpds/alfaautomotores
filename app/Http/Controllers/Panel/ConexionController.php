<?php

namespace App\Http\Controllers\Panel;

use App\Exceptions\WhatsappException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ajustes\ConexionIaRequest;
use App\Http\Requests\Ajustes\ConexionWhatsappRequest;
use App\Models\Ajuste;
use App\Services\WhatsappCloud;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Las credenciales de WhatsApp (Meta) y de la IA (OpenRouter), como en la
 * Configuración de Vocero.
 *
 * Se guardan en `ajustes`, con los secretos cifrados (`Ajuste::SECRETOS`).
 * Lo que no se carga acá sale del `.env`. Los secretos nunca vuelven al
 * navegador: la página sólo dice si están cargados y sus últimos caracteres.
 */
class ConexionController extends Controller implements HasMiddleware
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
     * Show both connections.
     */
    public function show(): Response
    {
        $whatsapp = Ajuste::conexionWhatsapp();
        $ia = Ajuste::conexionIa();

        return Inertia::render('panel/ajustes/conexiones', [
            'whatsapp' => [
                'token' => Ajuste::resumenDeSecreto(Ajuste::CONEXION_WHATSAPP, 'token', $whatsapp['token']),
                'appSecret' => Ajuste::resumenDeSecreto(Ajuste::CONEXION_WHATSAPP, 'app_secret', $whatsapp['app_secret']),
                'phoneNumberId' => $whatsapp['phone_number_id'],
                'wabaId' => $whatsapp['waba_id'],
                'verifyToken' => $whatsapp['verify_token'],
                'graphVersion' => $whatsapp['graph_version'],
                'webhookUrl' => filled($whatsapp['verify_token'])
                    ? route('webhooks.whatsapp.recibir', ['token' => $whatsapp['verify_token']])
                    : null,
            ],
            'ia' => [
                'apiKey' => Ajuste::resumenDeSecreto(Ajuste::CONEXION_IA, 'api_key', $ia['api_key']),
                'modelo' => $ia['modelo'],
                'modeloTranscripcion' => $ia['modelo_transcripcion'],
            ],
        ]);
    }

    /**
     * Save the Meta connection.
     */
    public function whatsapp(ConexionWhatsappRequest $request): RedirectResponse
    {
        Ajuste::guardarConexion(Ajuste::CONEXION_WHATSAPP, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Conexión de WhatsApp guardada.')]);

        return back();
    }

    /**
     * Save the AI connection.
     */
    public function ia(ConexionIaRequest $request): RedirectResponse
    {
        Ajuste::guardarConexion(Ajuste::CONEXION_IA, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Conexión de IA guardada.')]);

        return back();
    }

    /**
     * Ask Meta for the number with the saved credentials.
     */
    public function probarWhatsapp(WhatsappCloud $whatsapp): RedirectResponse
    {
        try {
            $numero = $whatsapp->numero();
        } catch (WhatsappException $error) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Meta no aceptó la conexión: :motivo', ['motivo' => $error->paraElPanel()])]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Conectado al número :numero (:nombre).', [
            'numero' => $numero['numero'] ?? '?',
            'nombre' => $numero['nombre'] ?? 'sin nombre verificado',
        ])]);

        return back();
    }

    /**
     * Check the OpenRouter key without spending a token.
     */
    public function probarIa(): RedirectResponse
    {
        $clave = Ajuste::conexionIa()['api_key'];

        if (blank($clave)) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Falta la clave de OpenRouter.')]);

            return back();
        }

        try {
            $respuesta = Http::withToken((string) $clave)->acceptJson()->timeout(15)->get('https://openrouter.ai/api/v1/key');
        } catch (ConnectionException) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('No se pudo conectar con OpenRouter.')]);

            return back();
        }

        Inertia::flash('toast', $respuesta->successful()
            ? ['type' => 'success', 'message' => __('La clave de OpenRouter funciona.')]
            : ['type' => 'error', 'message' => __('OpenRouter rechazó la clave (:estado).', ['estado' => $respuesta->status()])]);

        return back();
    }

    /**
     * Forget a saved secret so the `.env` value applies again.
     */
    public function olvidar(string $conexion, string $campo): RedirectResponse
    {
        $clave = match ($conexion) {
            'whatsapp' => Ajuste::CONEXION_WHATSAPP,
            'ia' => Ajuste::CONEXION_IA,
            default => abort(404),
        };

        abort_unless(in_array($campo, Ajuste::SECRETOS[$clave], true), 404);

        Ajuste::olvidarSecreto($clave, $campo);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Clave borrada del panel.')]);

        return back();
    }
}
