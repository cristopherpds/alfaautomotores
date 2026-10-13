<?php

namespace App\Ai\Tools;

use App\Ai\Prompt;
use App\Enums\EstadoTurno;
use App\Enums\OrigenTurno;
use App\Models\Cliente;
use App\Models\Conversacion;
use App\Models\HorarioOfrecido;
use App\Models\Servicio;
use App\Models\Turno;
use Carbon\CarbonInterface;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Date;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Reservar un turno, sólo en un horario que el agente ofreció.
 *
 * Va por `Turno::reservar()`, el mismo camino de la web: misma transacción,
 * mismo chequeo de lugar y el mismo alta de `Cliente`. Si el hueco se ocupó
 * mientras tanto, devuelve alternativas frescas en vez de confirmar algo que
 * no existe.
 */
class ReservarTurno extends Herramienta
{
    public function name(): string
    {
        return 'reservar_turno';
    }

    public function description(): Stringable|string
    {
        return 'Reserva un turno del taller o el lavadero en un horario que ofreciste con proponer_horarios, después de que la persona confirmó el día y la hora completos. Necesita nombre y apellido.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'servicio' => $schema->string()->required()->description('El código del servicio.'),
            'inicio' => $schema->string()->required()->description('El inicio EXACTO de un horario ofrecido, AAAA-MM-DD HH:MM.'),
            'confirmacion' => $schema->string()->required()->description('Lo que escribió la persona para aceptar ESE día y hora.'),
            'nombre' => $schema->string()->description('Nombre de la persona, si no tiene ficha.'),
            'apellido' => $schema->string()->description('Apellido de la persona, si no tiene ficha.'),
            'vehiculo_marca' => $schema->string(),
            'vehiculo_modelo' => $schema->string(),
            'vehiculo_anio' => $schema->integer(),
            'matricula' => $schema->string(),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $servicio = Servicio::where('slug', (string) ($request['servicio'] ?? ''))->where('activo', true)->first();
        $inicio = self::inicio((string) ($request['inicio'] ?? ''));

        if ($servicio === null || $inicio === null) {
            return $this->json(['error' => 'Servicio u horario inválido. El inicio va como AAAA-MM-DD HH:MM.']);
        }

        if (! HorarioOfrecido::fueOfrecido($this->conversacion, $servicio, $inicio)) {
            return $this->json(['error' => 'Ese horario no se le ofreció a esta persona (o ya venció). Llamá proponer_horarios y ofrecé uno de esos.']);
        }

        if (blank($request['confirmacion'] ?? null)) {
            return $this->json(['error' => 'Falta la confirmación de la persona: preguntale si te confirma ese día y hora.']);
        }

        $cliente = $this->conversacion->cliente;
        $nombre = $cliente->nombre ?? trim((string) ($request['nombre'] ?? ''));
        $apellido = $cliente->apellido ?? trim((string) ($request['apellido'] ?? ''));

        if ($nombre === '' || $apellido === '') {
            return $this->json(['error' => 'Para reservar necesito nombre y apellido. Pedíselos y volvé a llamar reservar_turno.']);
        }

        $turno = Turno::reservar($servicio, $inicio, [
            'nombre' => $nombre,
            'apellido' => $apellido,
            'email' => $cliente?->email,
            'celular' => self::celular($this->conversacion),
            'vehiculo_marca' => $request['vehiculo_marca'] ?? null,
            'vehiculo_modelo' => $request['vehiculo_modelo'] ?? null,
            'vehiculo_anio' => isset($request['vehiculo_anio']) ? (int) $request['vehiculo_anio'] : null,
            'matricula' => $request['matricula'] ?? null,
            'comentario' => 'Reservado por WhatsApp con el asistente virtual.',
        ], EstadoTurno::Pendiente, OrigenTurno::Whatsapp);

        if ($turno === null) {
            return $this->json([
                'error' => 'Ese horario se acaba de ocupar. NO confirmes nada: ofrecé uno de estos.',
                'alternativas' => ProponerHorarios::buscar($this->conversacion, $servicio, $inicio->format('Y-m-d'))['horarios'],
            ]);
        }

        $this->conversacion->cliente()->associate($turno->cliente_id);
        $this->conversacion->saveQuietly();

        return $this->json([
            'reservado' => true,
            'turno' => $turno->id,
            'servicio' => $servicio->nombre,
            'cuando' => Prompt::etiqueta($turno->inicia_at),
            'nota' => 'Confirmale el día completo, la hora y el servicio. El turno queda pendiente hasta que el equipo lo confirme.',
        ]);
    }

    public static function inicio(string $texto): ?CarbonInterface
    {
        $inicio = Date::createFromFormat('Y-m-d H:i', trim($texto));

        return $inicio === null || $inicio->format('Y-m-d H:i') !== trim($texto) ? null : $inicio->startOfMinute();
    }

    /**
     * El celular como se anota en las fichas (`099…` para Uruguay).
     */
    public static function celular(Conversacion $conversacion): string
    {
        $posibles = Cliente::celularesPosiblesDeWaId($conversacion->wa_id);

        return $posibles[1] ?? $posibles[0];
    }
}
