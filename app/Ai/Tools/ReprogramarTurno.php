<?php

namespace App\Ai\Tools;

use App\Ai\Prompt;
use App\Enums\EstadoTurno;
use App\Models\HorarioOfrecido;
use App\Models\Turno;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Mover un turno de la persona a otro horario ofrecido.
 *
 * Como dice la regla de modelos, reprogramar es reservar de nuevo y cancelar
 * el viejo: primero se asegura el lugar nuevo, así nunca queda sin turno.
 */
class ReprogramarTurno extends Herramienta
{
    public function name(): string
    {
        return 'reprogramar_turno';
    }

    public function description(): Stringable|string
    {
        return 'Mueve un turno que la persona ya tiene a otro horario que ofreciste con proponer_horarios (del mismo servicio), después de que confirmó el día y la hora completos.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'turno' => $schema->integer()->required()->description('El id del turno, de CONTEXTO ACTUAL.'),
            'inicio' => $schema->string()->required()->description('El nuevo inicio EXACTO, AAAA-MM-DD HH:MM.'),
            'confirmacion' => $schema->string()->required()->description('Lo que escribió la persona para aceptar el nuevo horario.'),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $viejo = Turno::with('servicio')->find((int) ($request['turno'] ?? 0));
        $inicio = ReservarTurno::inicio((string) ($request['inicio'] ?? ''));

        if ($viejo === null
            || $this->conversacion->cliente_id === null
            || $viejo->cliente_id !== $this->conversacion->cliente_id
            || ! in_array($viejo->estado, [EstadoTurno::Pendiente, EstadoTurno::Confirmado], true)
            || $viejo->inicia_at->isPast()) {
            return $this->json(['error' => 'Ese turno no es de esta persona o ya no se puede mover.']);
        }

        if ($inicio === null || ! HorarioOfrecido::fueOfrecido($this->conversacion, $viejo->servicio, $inicio)) {
            return $this->json(['error' => 'Ese horario no se ofreció para este servicio. Llamá proponer_horarios primero.']);
        }

        $nuevo = DB::transaction(function () use ($viejo, $inicio): ?Turno {
            $nuevo = Turno::reservar($viejo->servicio, $inicio, [
                'nombre' => $viejo->nombre,
                'apellido' => $viejo->apellido,
                'email' => $viejo->email,
                'celular' => $viejo->celular,
                'vehiculo_marca' => $viejo->vehiculo_marca,
                'vehiculo_modelo' => $viejo->vehiculo_modelo,
                'vehiculo_anio' => $viejo->vehiculo_anio,
                'matricula' => $viejo->matricula,
                'comentario' => trim(($viejo->comentario ?? '').' Reprogramado por WhatsApp.'),
            ], $viejo->estado, $viejo->origen);

            if ($nuevo !== null) {
                $viejo->cancelar();
            }

            return $nuevo;
        });

        if ($nuevo === null) {
            return $this->json([
                'error' => 'Ese horario se acaba de ocupar; el turno sigue como estaba. Ofrecé uno de estos.',
                'alternativas' => ProponerHorarios::buscar($this->conversacion, $viejo->servicio, $inicio->format('Y-m-d'))['horarios'],
            ]);
        }

        return $this->json([
            'reprogramado' => true,
            'turno' => $nuevo->id,
            'servicio' => $viejo->servicio->nombre,
            'antes' => Prompt::etiqueta($viejo->inicia_at),
            'ahora' => Prompt::etiqueta($nuevo->inicia_at),
        ]);
    }
}
