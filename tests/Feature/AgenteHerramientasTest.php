<?php

use App\Ai\Prompt;
use App\Ai\Tools\ActualizarFicha;
use App\Ai\Tools\BuscarStock;
use App\Ai\Tools\DerivarAHumano;
use App\Ai\Tools\ProponerHorarios;
use App\Ai\Tools\RegistrarCotizacion;
use App\Ai\Tools\ReprogramarTurno;
use App\Ai\Tools\ReservarTurno;
use App\Enums\EstadoTurno;
use App\Enums\MotivoDerivacion;
use App\Enums\OrigenCotizacion;
use App\Enums\OrigenTurno;
use App\Enums\TipoMensaje;
use App\Models\Ajuste;
use App\Models\Cliente;
use App\Models\Conversacion;
use App\Models\Cotizacion;
use App\Models\HorarioOfrecido;
use App\Models\MensajeWhatsapp;
use App\Models\Servicio;
use App\Models\Turno;
use App\Models\Vehiculo;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Tools\Request;

/*
 * Las herramientas del agente, llamadas como las llama el modelo.
 */

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');
});

/**
 * @return array<string, mixed>
 */
function llamar(object $herramienta, array $argumentos = []): array
{
    return json_decode((string) $herramienta->handle(new Request($argumentos)), true);
}

test('the stock search forgives typos and never shows drafts', function () {
    Vehiculo::factory()->publicado()->create(['marca' => 'Toyota', 'modelo' => 'Hilux', 'anio' => 2018]);
    Vehiculo::factory()->borrador()->create(['marca' => 'Toyota', 'modelo' => 'Hilux', 'anio' => 2024]);
    Vehiculo::factory()->publicado()->create(['marca' => 'Fiat', 'modelo' => 'Strada']);

    $resultado = llamar(new BuscarStock(Conversacion::factory()->create()), ['consulta' => 'hay alguna hailux?', 'tipo' => 'vehiculo']);

    expect($resultado['resultados'])->toHaveCount(1)
        ->and($resultado['resultados'][0]['titulo'])->toContain('Hilux')
        ->and($resultado['resultados'][0]['anio'])->toBe(2018)
        ->and($resultado['resultados'][0]['link'])->toContain('/vehiculos/');
});

test('an empty search says so instead of inventing', function () {
    Vehiculo::factory()->publicado()->create(['marca' => 'Fiat', 'modelo' => 'Strada']);

    $resultado = llamar(new BuscarStock(Conversacion::factory()->create()), ['consulta' => 'ferrari', 'tipo' => 'vehiculo']);

    expect($resultado['resultados'])->toBe([])
        ->and($resultado['nota'])->toContain('No hay nada');
});

test('proposed slots are real and remembered as offered', function () {
    $lunes = abrirElTaller();
    $servicio = Servicio::factory()->duracion(30)->create(['slug' => 'service']);
    $conversacion = Conversacion::factory()->create();

    $resultado = llamar(new ProponerHorarios($conversacion), ['servicio' => 'service', 'fecha' => $lunes->format('Y-m-d')]);

    expect($resultado['horarios'])->not->toBeEmpty()
        ->and(HorarioOfrecido::where('conversacion_id', $conversacion->id)->count())->toBe(count($resultado['horarios']));
});

test('a slot that was never offered cannot be booked', function () {
    $lunes = abrirElTaller();
    Servicio::factory()->duracion(30)->create(['slug' => 'service']);
    $conversacion = Conversacion::factory()->create();

    $resultado = llamar(new ReservarTurno($conversacion), [
        'servicio' => 'service',
        'inicio' => $lunes->format('Y-m-d').' 10:00',
        'confirmacion' => 'sí',
        'nombre' => 'Ana',
        'apellido' => 'Pérez',
    ]);

    expect($resultado['error'])->toContain('no se le ofreció')
        ->and(Turno::count())->toBe(0);
});

test('an offered slot is booked as a whatsapp turn and links the client', function () {
    $lunes = abrirElTaller();
    Servicio::factory()->duracion(30)->create(['slug' => 'service']);
    $conversacion = Conversacion::factory()->create(['wa_id' => '59899123456']);

    $horario = llamar(new ProponerHorarios($conversacion), ['servicio' => 'service', 'fecha' => $lunes->format('Y-m-d')])['horarios'][0];

    $resultado = llamar(new ReservarTurno($conversacion), [
        'servicio' => 'service',
        'inicio' => $horario['inicio'],
        'confirmacion' => 'dale, ese',
        'nombre' => 'Ana',
        'apellido' => 'Pérez',
    ]);

    $turno = Turno::sole();

    expect($resultado['reservado'])->toBeTrue()
        ->and($turno->origen)->toBe(OrigenTurno::Whatsapp)
        ->and($turno->estado)->toBe(EstadoTurno::Pendiente)
        ->and($turno->celular)->toBe('099123456')
        ->and($turno->inicia_at->format('Y-m-d H:i'))->toBe($horario['inicio'])
        ->and($conversacion->fresh()->cliente_id)->toBe($turno->cliente_id);
});

test('the offered slots carry the service code that booking asks for', function () {
    $lunes = abrirElLavadero();
    Servicio::factory()->lavadero()->duracion(60)->create(['slug' => 'lavado-camioneta', 'nombre' => 'Camioneta']);
    $conversacion = Conversacion::factory()->create();

    $resultado = llamar(new ProponerHorarios($conversacion), ['servicio' => 'lavado-camioneta', 'fecha' => $lunes->format('Y-m-d')]);

    expect($resultado['servicio'])->toBe('lavado-camioneta')
        ->and($resultado['nombre'])->toBe('Camioneta')
        ->and(Prompt::construir($conversacion->fresh(), Ajuste::agenteIa()))->toContain('servicio=lavado-camioneta, inicio='.$resultado['horarios'][0]['inicio']);
});

/*
 * Pasó en vivo: el modelo mandó el nombre («Camioneta») en vez del código, la
 * reserva falló y le dijo al cliente que el turno estaba confirmado.
 */
test('booking with the service name still books the offered slot', function () {
    $lunes = abrirElLavadero();
    Servicio::factory()->lavadero()->duracion(60)->create(['slug' => 'lavado-camioneta', 'nombre' => 'Camioneta']);
    $conversacion = Conversacion::factory()->create(['wa_id' => '59899123456']);

    $horario = llamar(new ProponerHorarios($conversacion), ['servicio' => 'lavado-camioneta', 'fecha' => $lunes->format('Y-m-d')])['horarios'][0];

    $resultado = llamar(new ReservarTurno($conversacion), [
        'servicio' => 'Camioneta',
        'inicio' => $horario['inicio'],
        'confirmacion' => 'Si confirma',
        'nombre' => 'Cristopher',
        'apellido' => 'Paiva',
    ]);

    expect($resultado['reservado'] ?? null)->toBeTrue()
        ->and(Turno::sole()->servicio->slug)->toBe('lavado-camioneta');
});

test('a wrong service name outside the offered slots still fails with a hint', function () {
    abrirElLavadero();
    Servicio::factory()->lavadero()->duracion(60)->create(['slug' => 'lavado-camioneta', 'nombre' => 'Camioneta']);
    $conversacion = Conversacion::factory()->create();

    $resultado = llamar(new ReservarTurno($conversacion), [
        'servicio' => 'Camioneta',
        'inicio' => '2026-09-21 10:00',
        'confirmacion' => 'sí',
        'nombre' => 'Ana',
        'apellido' => 'Pérez',
    ]);

    expect($resultado['error'])->toContain('el código')
        ->and(Turno::count())->toBe(0);
});

test('booking needs a name', function () {
    $lunes = abrirElTaller();
    Servicio::factory()->duracion(30)->create(['slug' => 'service']);
    $conversacion = Conversacion::factory()->create();
    $horario = llamar(new ProponerHorarios($conversacion), ['servicio' => 'service', 'fecha' => $lunes->format('Y-m-d')])['horarios'][0];

    $resultado = llamar(new ReservarTurno($conversacion), ['servicio' => 'service', 'inicio' => $horario['inicio'], 'confirmacion' => 'sí']);

    expect($resultado['error'])->toContain('nombre y apellido')
        ->and(Turno::count())->toBe(0);
});

test('rescheduling books the new slot and cancels the old one', function () {
    $lunes = abrirElTaller();
    $servicio = Servicio::factory()->duracion(30)->create(['slug' => 'service']);
    $conversacion = Conversacion::factory()->create(['wa_id' => '59899123456']);

    $primero = llamar(new ProponerHorarios($conversacion), ['servicio' => 'service', 'fecha' => $lunes->format('Y-m-d')])['horarios'];
    llamar(new ReservarTurno($conversacion), ['servicio' => 'service', 'inicio' => $primero[0]['inicio'], 'confirmacion' => 'sí', 'nombre' => 'Ana', 'apellido' => 'Pérez']);
    $viejo = Turno::sole();

    $resultado = llamar(new ReprogramarTurno($conversacion->fresh()), [
        'turno' => $viejo->id,
        'inicio' => $primero[1]['inicio'],
        'confirmacion' => 'mejor ese otro',
    ]);

    expect($resultado['reprogramado'])->toBeTrue()
        ->and($viejo->fresh()->estado)->toBe(EstadoTurno::Cancelado)
        ->and(Turno::where('estado', EstadoTurno::Pendiente)->sole()->inicia_at->format('Y-m-d H:i'))->toBe($primero[1]['inicio']);
});

test('nobody can move a turn that is not theirs', function () {
    abrirElTaller();
    $ajeno = Turno::factory()->create();
    $conversacion = Conversacion::factory()->create();
    $conversacion->cliente()->associate(Cliente::factory()->create())->save();

    $resultado = llamar(new ReprogramarTurno($conversacion), ['turno' => $ajeno->id, 'inicio' => '2026-09-22 10:00', 'confirmacion' => 'sí']);

    expect($resultado['error'])->toContain('no es de esta persona');
});

test('a quote takes the photos sent in the chat', function () {
    $conversacion = Conversacion::factory()->create(['wa_id' => '59899123456']);

    foreach (['uno', 'dos'] as $nombre) {
        Storage::disk('local')->put("whatsapp/{$conversacion->id}/{$nombre}.jpg", 'jpg');
        MensajeWhatsapp::factory()->for($conversacion)->create([
            'tipo' => TipoMensaje::Imagen,
            'media_ruta' => "whatsapp/{$conversacion->id}/{$nombre}.jpg",
        ]);
    }

    $resultado = llamar(new RegistrarCotizacion($conversacion), [
        'marca' => 'Chevrolet', 'modelo' => 'Onix', 'anio' => 2020, 'kilometros' => 60000,
        'nombre' => 'Ana', 'apellido' => 'Pérez',
    ]);

    $cotizacion = Cotizacion::sole();

    expect($resultado['fotos'])->toBe(2)
        ->and($cotizacion->ladosConFoto())->toBe(['derecha', 'izquierda'])
        ->and($cotizacion->celular)->toBe('099123456')
        ->and($cotizacion->origen)->toBe(OrigenCotizacion::Whatsapp)
        ->and($cotizacion->conversacion_id)->toBe($conversacion->id)
        ->and($conversacion->fresh()->cliente_id)->toBe($cotizacion->cliente_id);

    Storage::disk('local')->assertExists($cotizacion->foto_derecha);
});

test('the profile notes and the hand over are saved on the conversation', function () {
    $conversacion = Conversacion::factory()->create();

    llamar(new ActualizarFicha($conversacion), ['dato' => 'busca', 'valor' => 'camioneta 4x4']);
    llamar(new DerivarAHumano($conversacion), ['motivo' => 'cliente', 'resumen' => 'Quiere negociar la Hilux']);

    expect($conversacion->fresh())
        ->ficha->toBe(['busca' => 'camioneta 4x4', 'para el equipo' => 'Quiere negociar la Hilux'])
        ->motivo_derivacion->toBe(MotivoDerivacion::Cliente);
});

test('booking the same slot twice confirms the existing turn instead of a second one', function () {
    $lunes = abrirElTaller();
    Servicio::factory()->duracion(30)->create(['slug' => 'service']);
    $conversacion = Conversacion::factory()->create(['wa_id' => '59899123456']);
    $horario = llamar(new ProponerHorarios($conversacion), ['servicio' => 'service', 'fecha' => $lunes->format('Y-m-d')])['horarios'][0];
    $reserva = ['servicio' => 'service', 'inicio' => $horario['inicio'], 'confirmacion' => 'sí', 'nombre' => 'Ana', 'apellido' => 'Pérez'];

    llamar(new ReservarTurno($conversacion), $reserva);
    $otraVez = llamar(new ReservarTurno($conversacion->fresh()), [...$reserva, 'confirmacion' => 'sí, dale']);

    expect(Turno::count())->toBe(1)
        ->and($otraVez['ya_estaba'])->toBeTrue();
});

test('a second turn of the same service is pointed to rescheduling', function () {
    $lunes = abrirElTaller();
    Servicio::factory()->duracion(30)->create(['slug' => 'service']);
    $conversacion = Conversacion::factory()->create(['wa_id' => '59899123456']);
    $horarios = llamar(new ProponerHorarios($conversacion), ['servicio' => 'service', 'fecha' => $lunes->format('Y-m-d')])['horarios'];

    llamar(new ReservarTurno($conversacion), ['servicio' => 'service', 'inicio' => $horarios[0]['inicio'], 'confirmacion' => 'sí', 'nombre' => 'Ana', 'apellido' => 'Pérez']);
    $segundo = llamar(new ReservarTurno($conversacion->fresh()), ['servicio' => 'service', 'inicio' => $horarios[1]['inicio'], 'confirmacion' => 'sí']);

    expect(Turno::count())->toBe(1)
        ->and($segundo['error'])->toContain('reprogramar_turno');
});

test('a client already on file is recognized by phone before the chat is linked', function () {
    $lunes = abrirElTaller();
    $servicio = Servicio::factory()->duracion(30)->create(['slug' => 'service']);
    $cliente = Cliente::factory()->create(['celular' => '099 123 456']);
    Turno::factory()->create([
        'cliente_id' => $cliente->id,
        'servicio_id' => $servicio->id,
        'estado' => EstadoTurno::Pendiente,
        'inicia_at' => $lunes->copy()->setTime(15, 0),
        'termina_at' => $lunes->copy()->setTime(15, 30),
    ]);
    $conversacion = Conversacion::factory()->create(['wa_id' => '59899123456']);
    $horario = llamar(new ProponerHorarios($conversacion), ['servicio' => 'service', 'fecha' => $lunes->format('Y-m-d')])['horarios'][0];

    $resultado = llamar(new ReservarTurno($conversacion), ['servicio' => 'service', 'inicio' => $horario['inicio'], 'confirmacion' => 'sí']);

    expect($resultado['error'])->toContain('ya tiene un turno')
        ->and(Turno::count())->toBe(1);
});

test('stock links are built on the public url', function () {
    $vehiculo = Vehiculo::factory()->publicado()->create(['marca' => 'Toyota', 'modelo' => 'Hilux', 'anio' => 2018]);
    config()->set('app.url', 'https://alfa.ngrok.app/');

    $resultado = llamar(new BuscarStock(Conversacion::factory()->create()), ['consulta' => 'hilux', 'tipo' => 'vehiculo']);

    expect($resultado['resultados'][0]['link'])->toBe('https://alfa.ngrok.app/vehiculos/'.$vehiculo->slug)
        ->and(Prompt::construir(Conversacion::factory()->create(), Ajuste::agenteIa()))->toContain('https://alfa.ngrok.app/catalogo');
});
