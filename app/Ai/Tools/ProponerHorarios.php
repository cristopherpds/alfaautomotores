<?php

namespace App\Ai\Tools;

use App\Ai\Prompt;
use App\Models\Conversacion;
use App\Models\HorarioOfrecido;
use App\Models\Puesto;
use App\Models\Servicio;
use Carbon\CarbonInterface;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Date;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Horarios libres reales para un servicio, que quedan registrados como
 * ofrecidos: son los únicos que `reservar_turno` acepta.
 *
 * Sin fecha devuelve un reparto (tres horarios en días distintos); con fecha,
 * las horas libres de ese día. Respeta la anticipación y el horizonte del
 * rubro, igual que la reserva web.
 */
class ProponerHorarios extends Herramienta
{
    /**
     * Horas de un día pedido que se devuelven como mucho.
     */
    private const MAX_DEL_DIA = 6;

    public function name(): string
    {
        return 'proponer_horarios';
    }

    public function description(): Stringable|string
    {
        return 'Busca horarios libres reales para un servicio del taller o del lavadero. Sin fecha devuelve tres opciones en días distintos; con fecha (AAAA-MM-DD) las horas libres de ese día. Los horarios devueltos quedan como ofrecidos: sólo esos se pueden reservar.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'servicio' => $schema->string()->required()->description('El código del servicio (campo "servicio" de consultar_servicios).'),
            'fecha' => $schema->string()->description('Un día concreto, AAAA-MM-DD, si la persona pidió uno.'),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $servicio = Servicio::where('slug', (string) ($request['servicio'] ?? ''))->where('activo', true)->first();

        if ($servicio === null) {
            return $this->json(['error' => 'Ese servicio no existe. Usá consultar_servicios para ver los códigos.']);
        }

        if (! $servicio->agendable) {
            return $this->json(['error' => 'Este trabajo no se agenda online: se coordina con el equipo. Usá derivar_a_humano.']);
        }

        $horarios = self::buscar($this->conversacion, $servicio, filled($request['fecha'] ?? null) ? (string) $request['fecha'] : null);

        /* El código va en `servicio`, que es lo que pide reservar_turno: con
           el nombre («Camioneta») el modelo reservaba con un código que no
           existe. */
        return $this->json([
            'servicio' => $servicio->slug,
            'nombre' => $servicio->nombre,
            'duracion' => $servicio->duracionLegible(),
            'horarios' => $horarios['horarios'],
            'nota' => $horarios['nota'],
        ]);
    }

    /**
     * Buscar y registrar los horarios. La usa también `reservar_turno` para
     * ofrecer alternativas frescas cuando el elegido se ocupó.
     *
     * @return array{horarios: list<array{etiqueta: string, inicio: string}>, nota: string|null}
     */
    public static function buscar(Conversacion $conversacion, Servicio $servicio, ?string $fecha = null): array
    {
        $desde = now()->addDays((int) $servicio->rubro->config('anticipacion_dias'))->startOfDay();
        $hasta = now()->addDays((int) $servicio->rubro->config('horizonte_dias'))->endOfDay();

        if ($fecha !== null) {
            $dia = Date::createFromFormat('Y-m-d', $fecha);

            if ($dia === null || $dia->format('Y-m-d') !== $fecha) {
                return ['horarios' => [], 'nota' => 'La fecha tiene que ser AAAA-MM-DD.'];
            }

            $dia = $dia->startOfDay();

            if ($dia->lessThan($desde) || $dia->greaterThan($hasta)) {
                return ['horarios' => [], 'nota' => 'Ese día no se puede reservar: se agenda del '.Prompt::fecha($desde).' al '.Prompt::fecha($hasta).'.'];
            }

            $inicios = array_slice(self::libres($dia, $servicio), 0, self::MAX_DEL_DIA);

            return [
                'horarios' => self::ofrecer($conversacion, $servicio, $inicios),
                'nota' => $inicios === [] ? 'Ese día no queda lugar (o el local está cerrado). Ofrecé el día más cercano que sí tenga.' : null,
            ];
        }

        /* Un reparto como el de nea: tres días distintos, alternando la mañana
           y la tarde para que haya de dónde elegir. */
        $preferidas = ['09:00', '15:00', '10:30'];
        $inicios = [];

        for ($dia = $desde->copy(); $dia->lessThanOrEqualTo($hasta) && count($inicios) < 3; $dia = $dia->addDay()) {
            $libres = self::libres($dia, $servicio);

            if ($libres !== []) {
                $inicios[] = self::masCercana($libres, $preferidas[count($inicios)]);
            }
        }

        return [
            'horarios' => self::ofrecer($conversacion, $servicio, $inicios),
            'nota' => $inicios === [] ? 'No hay lugar en los próximos días. Derivá para que el equipo lo coordine.' : null,
        ];
    }

    /**
     * @return list<CarbonInterface>
     */
    private static function libres(CarbonInterface $dia, Servicio $servicio): array
    {
        $ahora = now();

        return collect(Puesto::huecosDelDia($dia, $servicio))
            ->map(fn (string $hora): CarbonInterface => Date::parse($dia->format('Y-m-d').' '.$hora))
            ->filter(fn (CarbonInterface $inicio): bool => $inicio->greaterThan($ahora))
            ->values()
            ->all();
    }

    /**
     * @param  list<CarbonInterface>  $libres
     */
    private static function masCercana(array $libres, string $hora): CarbonInterface
    {
        [$h, $m] = array_map('intval', explode(':', $hora));
        $objetivo = $h * 60 + $m;

        return collect($libres)
            ->sortBy(fn (CarbonInterface $inicio): int => abs($inicio->hour * 60 + $inicio->minute - $objetivo))
            ->first();
    }

    /**
     * @param  list<CarbonInterface>  $inicios
     * @return list<array{etiqueta: string, inicio: string}>
     */
    private static function ofrecer(Conversacion $conversacion, Servicio $servicio, array $inicios): array
    {
        return array_map(function (CarbonInterface $inicio) use ($conversacion, $servicio): array {
            HorarioOfrecido::create([
                'conversacion_id' => $conversacion->id,
                'servicio_id' => $servicio->id,
                'inicia_at' => $inicio,
            ]);

            return ['etiqueta' => Prompt::etiqueta($inicio), 'inicio' => $inicio->format('Y-m-d H:i')];
        }, $inicios);
    }
}
