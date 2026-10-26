<?php

use App\Models\Auditoria;
use App\Models\Cliente;
use App\Models\Conversacion;
use App\Models\Cotizacion;
use App\Models\MensajeWhatsapp;
use App\Models\Turno;
use App\Models\User;
use App\Support\Cifrado;
use App\Support\IndiceCiego;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

test('personal data is stored encrypted with its own key, not in plain text', function () {
    $cliente = Cliente::factory()->create([
        'nombre' => 'Ana', 'apellido' => 'Pérez', 'email' => 'ana@example.com',
        'celular' => '099 123 456', 'notas' => 'Prefiere la mañana',
    ]);
    $turno = Turno::factory()->create(['nombre' => 'Ana', 'matricula' => 'SBC 1234', 'comentario' => 'Hace ruido']);
    $cotizacion = Cotizacion::factory()->create(['celular' => '099 555 444', 'detalles' => 'Un rayón']);
    $conversacion = Conversacion::factory()->create(['wa_id' => '59899123456', 'nombre_perfil' => 'Anita', 'ficha' => ['auto' => 'Gol']]);
    $mensaje = MensajeWhatsapp::factory()->for($conversacion)->create(['cuerpo' => 'Hola, ¿tienen turno?', 'meta' => ['secreto' => 'x']]);

    $crudo = fn (string $tabla, int $id, string $columna): string => (string) DB::table($tabla)->where('id', $id)->value($columna);

    expect($crudo('clientes', $cliente->id, 'nombre'))->not->toContain('Ana')
        ->and($crudo('clientes', $cliente->id, 'email'))->not->toContain('ana@')
        ->and($crudo('clientes', $cliente->id, 'celular_normalizado'))->not->toContain('099123456')
        ->and($crudo('clientes', $cliente->id, 'notas'))->not->toContain('mañana')
        ->and($crudo('turnos', $turno->id, 'matricula'))->not->toContain('SBC')
        ->and($crudo('turnos', $turno->id, 'comentario'))->not->toContain('ruido')
        ->and($crudo('cotizaciones', $cotizacion->id, 'detalles'))->not->toContain('rayón')
        ->and($crudo('conversaciones', $conversacion->id, 'wa_id'))->not->toContain('59899123456')
        ->and($crudo('conversaciones', $conversacion->id, 'ficha'))->not->toContain('Gol')
        ->and($crudo('mensajes_whatsapp', $mensaje->id, 'cuerpo'))->not->toContain('Hola')
        ->and($crudo('mensajes_whatsapp', $mensaje->id, 'meta'))->not->toContain('secreto');

    // Con la clave propia y GCM; la APP_KEY no lo abre.
    $payload = json_decode(base64_decode($crudo('clientes', $cliente->id, 'nombre')), true);
    expect($payload['tag'])->not->toBe('')
        ->and(app(Cifrado::class)->decryptString($crudo('clientes', $cliente->id, 'nombre')))->toBe('Ana')
        ->and(Model::currentEncrypter())->toBeInstanceOf(Cifrado::class)
        ->and(fn () => decrypt($crudo('clientes', $cliente->id, 'nombre'), false))->toThrow(Exception::class);

    // Y el modelo lo lee en claro.
    expect($cliente->fresh())
        ->nombre->toBe('Ana')
        ->celular_normalizado->toBe('099123456')
        ->and($conversacion->fresh()->ficha)->toBe(['auto' => 'Gol'])
        ->and($mensaje->fresh()->cuerpo)->toBe('Hola, ¿tienen turno?');
});

test('the blind index finds a client by phone, email and plate without decrypting', function () {
    $ana = Cliente::factory()->create(['celular' => '099 123 456', 'email' => 'Ana@Example.com']);
    Turno::factory()->for($ana, 'ficha')->create(['matricula' => 'sbc-1234']);

    expect(DB::table('clientes')->where('id', $ana->id)->value('celular_hash'))->toBe(IndiceCiego::de('099123456'))
        ->and(Cliente::where('celular_hash', IndiceCiego::deCelular('099123456'))->sole()->is($ana))->toBeTrue()
        ->and(Cliente::where('email_hash', IndiceCiego::deEmail(' ana@example.com '))->sole()->is($ana))->toBeTrue()
        ->and(Turno::where('matricula_hash', IndiceCiego::deMatricula('SBC 1234'))->sole()->cliente_id)->toBe($ana->id)
        ->and(Cliente::delWaId('59899123456')?->is($ana))->toBeTrue()
        ->and(Conversacion::delNumero('59899123456')->wa_id_hash)->toBe(IndiceCiego::de('59899123456'))
        ->and(Conversacion::delNumero('59899123456', 'Ana'))->nombre_perfil->toBe('Ana')
        ->and(Conversacion::count())->toBe(1);
});

test('the phone and the WhatsApp number stay unique through their hashes', function () {
    Cliente::factory()->create(['celular' => '099 123 456']);

    expect(fn () => Cliente::factory()->create(['celular' => '099-123-456']))
        ->toThrow(UniqueConstraintViolationException::class);

    Conversacion::factory()->create(['wa_id' => '59899123456']);

    expect(fn () => Conversacion::factory()->create(['wa_id' => '59899123456']))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('the audit trail stores plain values inside the encrypted changes', function () {
    $this->actingAs(User::factory()->admin()->create());

    $cliente = Cliente::factory()->create(['nombre' => 'Ana', 'apellido' => 'Pérez']);
    $cliente->update(['nombre' => 'Anabel']);

    $entrada = Auditoria::where('tipo', 'cliente')->where('accion', 'modificado')->sole();

    expect($entrada->cambios)->toBe(['nombre' => ['Ana', 'Anabel']])
        ->and($entrada->etiqueta)->toBe('Anabel Pérez')
        ->and((string) DB::table('auditorias')->where('id', $entrada->id)->value('cambios'))->not->toContain('Anabel')
        ->and(Auditoria::where('tipo', 'cliente')->where('accion', 'creado')->sole()->cambios)
        ->toHaveKey('nombre', [null, 'Ana'])
        ->not->toHaveKey('celular_hash');
});

test('the backfill encrypts existing rows, fills the hashes and can run twice', function () {
    $ahora = now();
    $clienteId = DB::table('clientes')->insertGetId([
        'nombre' => 'Ana', 'apellido' => 'Pérez', 'email' => 'ana@example.com',
        'celular' => '099 123 456', 'celular_normalizado' => '099123456',
        'acepta_novedades' => false, 'created_at' => $ahora, 'updated_at' => $ahora,
    ]);
    $conversacionId = DB::table('conversaciones')->insertGetId([
        'wa_id' => '59899123456', 'nombre_perfil' => 'Anita', 'ficha' => '{"auto":"Gol"}',
        'ia_activa' => true, 'no_leidos' => 0, 'created_at' => $ahora, 'updated_at' => $ahora,
    ]);
    $yaCifrado = Cliente::factory()->create(['nombre' => 'Juan', 'celular' => '099 000 111']);

    $backfill = require database_path('migrations/2026_10_26_000002_encrypt_existing_personal_data.php');
    $backfill->up();
    $backfill->up();

    expect((string) DB::table('clientes')->where('id', $clienteId)->value('nombre'))->not->toBe('Ana')
        ->and(Cliente::find($clienteId))
        ->nombre->toBe('Ana')
        ->email->toBe('ana@example.com')
        ->celular_hash->toBe(IndiceCiego::de('099123456'))
        ->email_hash->toBe(IndiceCiego::deEmail('ana@example.com'))
        ->and(Conversacion::find($conversacionId))
        ->wa_id->toBe('59899123456')
        ->ficha->toBe(['auto' => 'Gol'])
        ->wa_id_hash->toBe(IndiceCiego::de('59899123456'))
        ->and($yaCifrado->fresh()->nombre)->toBe('Juan');

    $backfill->down();

    expect(DB::table('clientes')->where('id', $clienteId)->value('nombre'))->toBe('Ana');
});
