<?php

namespace App\Http\Controllers;

use App\Jobs\ProcesarWebhookWhatsapp;
use App\Models\Ajuste;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * El webhook de la WhatsApp Cloud API.
 *
 * Dos capas, como en Vocero: el token va en la URL (uno equivocado da 404 sin
 * leer nada) y cada POST trae la firma `x-hub-signature-256` del App Secret.
 * Lo que pasa la puerta se encola y se responde 200 enseguida: Meta reintenta
 * y termina dando de baja a un webhook que tarda o devuelve 5xx.
 */
class WhatsappWebhookController extends Controller
{
    /**
     * The verification handshake Meta runs when the webhook is configured.
     */
    public function verificar(Request $request, string $token): Response
    {
        $this->exigirToken($token);

        abort_unless(
            $request->query('hub_mode') === 'subscribe'
                && hash_equals((string) Ajuste::conexionWhatsapp()['verify_token'], (string) $request->query('hub_verify_token')),
            403,
        );

        return response((string) $request->query('hub_challenge'), 200, ['Content-Type' => 'text/plain']);
    }

    /**
     * Receive an event from Meta.
     */
    public function recibir(Request $request, string $token): JsonResponse
    {
        $this->exigirToken($token);

        $secreto = (string) Ajuste::conexionWhatsapp()['app_secret'];

        if ($secreto !== '') {
            $esperada = 'sha256='.hash_hmac('sha256', $request->getContent(), $secreto);

            abort_unless(hash_equals($esperada, (string) $request->header('X-Hub-Signature-256')), 401);
        }

        $payload = $request->json()->all();

        if (($payload['object'] ?? null) === 'whatsapp_business_account') {
            ProcesarWebhookWhatsapp::dispatch($payload);
        }

        return response()->json(['received' => true]);
    }

    /**
     * Un token que no es el nuestro: la ruta no existe.
     */
    private function exigirToken(string $token): void
    {
        $esperado = (string) Ajuste::conexionWhatsapp()['verify_token'];

        abort_if($esperado === '' || ! hash_equals($esperado, $token), 404);
    }
}
