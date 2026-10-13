<?php

use App\Http\Controllers\WhatsappWebhookController;
use Illuminate\Support\Facades\Route;

/*
 * Webhooks de terceros. Se cargan fuera del grupo `web` (ver
 * `bootstrap/app.php`): sin sesión, cookies ni CSRF.
 */

/* WhatsApp Cloud API. El token de la URL es la primera puerta y la firma de
   Meta (`x-hub-signature-256`) la segunda. */
Route::get('webhooks/whatsapp/{token}', [WhatsappWebhookController::class, 'verificar'])->name('webhooks.whatsapp.verificar');
Route::post('webhooks/whatsapp/{token}', [WhatsappWebhookController::class, 'recibir'])->name('webhooks.whatsapp.recibir');
