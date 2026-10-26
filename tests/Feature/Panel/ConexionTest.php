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

test('the openrouter model list is filtered by what the agent needs', function () {
    Http::fake(['openrouter.ai/api/v1/models' => Http::response(['data' => [
        ['id' => 'google/gemini-2.5-flash', 'name' => 'Gemini 2.5 Flash', 'supported_parameters' => ['tools'], 'architecture' => ['input_modalities' => ['text', 'image', 'audio']]],
        ['id' => 'z-ai/glm-5.3-flash', 'name' => 'GLM', 'supported_parameters' => ['tools'], 'architecture' => ['input_modalities' => ['text']]],
        ['id' => 'openai/whisper', 'name' => 'Whisper', 'supported_parameters' => [], 'architecture' => ['input_modalities' => ['audio']]],
    ]])]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('panel.ajustes.conexiones'))
        ->assertInertia(fn ($page) => $page
            ->missing('modelos')
            ->reloadOnly('modelos', fn ($recarga) => $recarga
                ->where('modelos.agente', [['id' => 'google/gemini-2.5-flash', 'nombre' => 'Gemini 2.5 Flash']])
                ->where('modelos.audio.0.id', 'google/gemini-2.5-flash')
                ->where('modelos.audio.1.id', 'openai/whisper')
            )
        );
});

test('the public url is saved and warns when whatsapp will not link it', function () {
    config()->set('app.url', 'http://localhost:8000');
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('panel.ajustes.conexiones'))
        ->assertInertia(fn ($page) => $page
            ->where('sitio.urlPublica', null)
            ->where('sitio.urlEfectiva', 'http://localhost:8000')
            ->where('sitio.enlazable', false)
        );

    $this->actingAs($admin)
        ->put(route('panel.ajustes.conexiones.sitio'), ['url_publica' => 'https://alfa.ngrok.app/'])
        ->assertSessionHasNoErrors();

    expect(Ajuste::urlPublica())->toBe('https://alfa.ngrok.app');

    $this->actingAs($admin)
        ->get(route('panel.ajustes.conexiones'))
        ->assertInertia(fn ($page) => $page
            ->where('sitio.urlPublica', 'https://alfa.ngrok.app')
            ->where('sitio.enlazable', true)
            ->where('sitio.ejemplo', 'https://alfa.ngrok.app/catalogo')
        );

    $this->actingAs($admin)
        ->put(route('panel.ajustes.conexiones.sitio'), ['url_publica' => 'alfa sin protocolo'])
        ->assertSessionHasErrors('url_publica');

    $this->actingAs(User::factory()->role(UserRole::Vendedor)->create())
        ->put(route('panel.ajustes.conexiones.sitio'), ['url_publica' => 'https://otro.com'])
        ->assertForbidden();
});

test('the public url only takes the origin, not a path or a query', function (string $url) {
    $this->actingAs(User::factory()->admin()->create())
        ->put(route('panel.ajustes.conexiones.sitio'), ['url_publica' => $url])
        ->assertSessionHasErrors(['url_publica' => 'Sólo la dirección del sitio, sin nada después del dominio (https://alfaautomotores.com.uy, no https://alfaautomotores.com.uy/catalogo).']);
})->with([
    'https://alfaautomotores.com.uy/catalogo',
    'https://alfaautomotores.com.uy/?ref=wa',
    'https://alfaautomotores.com.uy/#inicio',
]);

test('the public url keeps the port and drops a path saved before', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put(route('panel.ajustes.conexiones.sitio'), ['url_publica' => 'http://alfa.ngrok.app:8080'])
        ->assertSessionHasNoErrors();

    expect(Ajuste::urlPublica())->toBe('http://alfa.ngrok.app:8080');

    Ajuste::guardar(Ajuste::SITIO_PUBLICO, ['url_publica' => 'https://alfa.ngrok.app/viejo?x=1']);

    expect(Ajuste::urlPublica())->toBe('https://alfa.ngrok.app')
        ->and(Ajuste::enlacePublico('home'))->toBe('https://alfa.ngrok.app/');
});

test('only urls with a public domain count as linkable', function (string $url, bool $enlazable) {
    expect(Ajuste::esUrlEnlazable($url))->toBe($enlazable);
})->with([
    ['http://localhost:8000', false],
    ['http://127.0.0.1:8000', false],
    ['http://alfa.test', false],
    ['http://intranet', false],
    ['https://alfa.ngrok.app', true],
    ['https://alfaautomotores.com.uy', true],
]);

test('the transcription model has to be a speech to text model', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->put(route('panel.ajustes.conexiones.ia'), ['modelo' => 'google/gemini-2.5-flash', 'modelo_transcripcion' => 'google/gemini-2.5-flash'])
        ->assertSessionHasErrors('modelo_transcripcion');

    $this->actingAs($admin)
        ->put(route('panel.ajustes.conexiones.ia'), ['modelo' => 'google/gemini-2.5-flash', 'modelo_transcripcion' => 'openai/whisper-1'])
        ->assertSessionHasNoErrors();

    expect(Ajuste::conexionIa()['modelo_transcripcion'])->toBe('openai/whisper-1');
});

test('whisper is the default transcription model', function () {
    expect(config('services.openrouter.modelo_transcripcion'))->toBe('openai/whisper-1');
});
