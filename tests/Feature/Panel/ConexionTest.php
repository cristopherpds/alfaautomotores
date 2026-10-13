<?php

use App\Enums\UserRole;
use App\Models\Ajuste;
use App\Models\User;
use App\Services\WhatsappCloud;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.whatsapp', [
        'token' => 'token-del-env',
        'phone_number_id' => '111222333',
        'waba_id' => '999888777',
        'app_secret' => 'secreto-del-env',
        'verify_token' => 'verify-del-env',
        'graph_version' => 'v23.0',
    ]);
    config()->set('ai.providers.openrouter.key', null);
});

test('only admins reach the connections page', function () {
    $this->actingAs(User::factory()->role(UserRole::Vendedor)->create())
        ->get(route('panel.ajustes.conexiones'))
        ->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('panel.ajustes.conexiones'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('panel/ajustes/conexiones')
            ->where('whatsapp.token.origen', 'env')
            ->where('whatsapp.token.final', '-env')
            ->where('whatsapp.phoneNumberId', '111222333')
            ->where('ia.apiKey.cargado', false)
        );
});

test('secrets are stored encrypted and never sent back to the browser', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->put(route('panel.ajustes.conexiones.whatsapp'), [
            'token' => 'EAAG-token-del-panel-1234',
            'app_secret' => 'secreto-del-panel',
            'phone_number_id' => '444555666',
            'waba_id' => '777888999',
            'verify_token' => 'un-token-largo-de-webhook',
            'graph_version' => 'v24.0',
        ])
        ->assertSessionHasNoErrors();

    $crudo = (string) DB::table('ajustes')->where('clave', Ajuste::CONEXION_WHATSAPP)->value('valor');

    expect($crudo)->not->toContain('EAAG-token-del-panel-1234')
        ->and($crudo)->not->toContain('secreto-del-panel');

    expect(Ajuste::conexionWhatsapp())
        ->token->toBe('EAAG-token-del-panel-1234')
        ->app_secret->toBe('secreto-del-panel')
        ->phone_number_id->toBe('444555666');

    $this->actingAs($admin)
        ->get(route('panel.ajustes.conexiones'))
        ->assertInertia(fn ($page) => $page
            ->where('whatsapp.token.origen', 'panel')
            ->where('whatsapp.token.final', '1234')
            ->where('whatsapp.webhookUrl', route('webhooks.whatsapp.recibir', ['token' => 'un-token-largo-de-webhook']))
        )
        ->assertDontSee('EAAG-token-del-panel-1234')
        ->assertDontSee('secreto-del-panel');
});

test('an empty secret keeps the saved one', function () {
    Ajuste::guardarConexion(Ajuste::CONEXION_WHATSAPP, ['token' => 'el-guardado']);

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('panel.ajustes.conexiones.whatsapp'), ['token' => '', 'phone_number_id' => '123456'])
        ->assertSessionHasNoErrors();

    expect(Ajuste::conexionWhatsapp()['token'])->toBe('el-guardado');
});

test('the webhook verify token has to be long enough for a url', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put(route('panel.ajustes.conexiones.whatsapp'), ['verify_token' => 'test'])
        ->assertSessionHasErrors('verify_token');
});

test('the panel credentials win over the env everywhere', function () {
    Ajuste::guardarConexion(Ajuste::CONEXION_WHATSAPP, ['token' => 'token-del-panel', 'phone_number_id' => '444555666']);

    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.x']]])]);

    app(WhatsappCloud::class)->enviarTexto('59899123456', 'Hola');

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer token-del-panel')
        && str_contains($request->url(), '/444555666/messages'));
});

test('forgetting a secret falls back to the env', function () {
    Ajuste::guardarConexion(Ajuste::CONEXION_WHATSAPP, ['token' => 'token-del-panel']);

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('panel.ajustes.conexiones.olvidar', ['conexion' => 'whatsapp', 'campo' => 'token']))
        ->assertSessionHasNoErrors();

    expect(Ajuste::conexionWhatsapp()['token'])->toBe('token-del-env');
});

test('the connection test asks meta for the number', function () {
    Http::fake(['graph.facebook.com/v23.0/111222333*' => Http::response([
        'display_phone_number' => '+598 99 000 000', 'verified_name' => 'Alfa Automotores', 'quality_rating' => 'GREEN',
    ])]);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('panel.ajustes.conexiones.whatsapp.probar'))
        ->assertInertiaFlash('toast.type', 'success');
});

test('the openrouter key is checked without spending tokens', function () {
    Http::fake(['openrouter.ai/api/v1/key' => Http::response(['data' => ['label' => 'alfa']])]);

    Ajuste::guardarConexion(Ajuste::CONEXION_IA, ['api_key' => 'sk-or-prueba', 'modelo' => 'google/gemini-2.5-flash', 'modelo_transcripcion' => 'google/gemini-2.5-flash']);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('panel.ajustes.conexiones.ia.probar'))
        ->assertInertiaFlash('toast.type', 'success');

    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer sk-or-prueba'));
});
