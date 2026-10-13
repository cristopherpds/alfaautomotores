<?php

namespace App\Ai\Tools;

use App\Enums\Rubro;
use App\Models\Servicio;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Los servicios del taller y del lavadero, con lo que se publica en el sitio.
 */
class ConsultarServicios extends Herramienta
{
    public function name(): string
    {
        return 'consultar_servicios';
    }

    public function description(): Stringable|string
    {
        return 'Lista los servicios del taller mecánico y del lavadero: nombre, código (slug) para pedir horarios, duración, precio si lo tiene y si se puede agendar online.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'rubro' => $schema->string()->enum(['taller', 'lavadero', 'todos'])->description('De qué negocio.'),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $rubros = match ($request['rubro'] ?? 'todos') {
            'taller' => [Rubro::Taller],
            'lavadero' => [Rubro::Lavadero],
            default => Rubro::cases(),
        };

        $servicios = [];

        foreach ($rubros as $rubro) {
            foreach (Servicio::publicos($rubro) as $servicio) {
                $servicios[] = [
                    'servicio' => $servicio->slug,
                    'nombre' => $servicio->nombre,
                    'rubro' => $rubro->label(),
                    'duracion' => $servicio->duracionLegible(),
                    'precio' => $servicio->precioLegible() ?? 'se cotiza en el local',
                    'descripcion' => $servicio->descripcion,
                    'se_agenda_online' => $servicio->agendable,
                ];
            }
        }

        return $this->json([
            'servicios' => $servicios,
            'nota' => 'Los que tienen se_agenda_online=false se coordinan con el equipo: derivar_a_humano.',
        ]);
    }
}
