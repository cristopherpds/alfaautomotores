<?php

namespace App\Models;

use App\Enums\Rubro;
use Carbon\CarbonInterface;
use Database\Factories\PuestoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Zap\Facades\Zap;
use Zap\Models\Concerns\HasSchedules;

/**
 * Un puesto de trabajo: un auto a la vez.
 *
 * La capacidad de cada negocio son N puestos, no un número en una columna: Zap
 * no maneja capacidad mayor que uno sobre un mismo recurso, así que atender dos
 * autos en paralelo se modela con dos puestos, cada uno con su propia agenda.
 * Los crea `taller:agenda` / `lavadero:agenda` a partir de la config del rubro.
 *
 * El `rubro` mantiene separadas las dos capacidades: un lavado nunca ocupa un
 * puesto de mecánica porque los horarios de un servicio salen únicamente de los
 * puestos de su propio rubro.
 *
 * `huecosDelDia()` y `libreEn()` son la única definición de la disponibilidad:
 * las consultan el sitio público y el panel.
 *
 * @property int $id
 * @property string $nombre
 * @property Rubro $rubro
 * @property bool $activo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Turno> $turnos
 */
#[Fillable(['nombre', 'rubro', 'activo'])]
class Puesto extends Model
{
    /** @use HasFactory<PuestoFactory> */
    use HasFactory;

    use HasSchedules;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rubro' => Rubro::class,
            'activo' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Turno, $this>
     */
    public function turnos(): HasMany
    {
        return $this->hasMany(Turno::class);
    }

    /**
     * Darle al puesto el horario de su rubro para un año entero.
     *
     * La disponibilidad no es una columna: son los períodos que le cuelgan, y
     * sin ellos el puesto no ofrece ningún hueco. El corte del mediodía no se
     * bloquea, simplemente son dos períodos y el hueco del medio no existe.
     *
     * El año en curso arranca hoy y no el 1 de enero: Zap rechaza las agendas
     * que empiezan en el pasado. Y va un horario por año porque el paquete
     * limita el rango a 365 días.
     *
     * Es idempotente: si el año ya está agendado, no hace nada.
     */
    public function agendar(int $anio): void
    {
        $nombre = "Horario del {$this->rubro->value} {$anio}";

        if ($this->schedules()->where('name', 'like', $nombre.'%')->exists()) {
            return;
        }

        /** @var array{semana: list<array{0: string, 1: string}>, sabado: list<array{0: string, 1: string}>} $horarios */
        $horarios = $this->rubro->config('horarios');

        $primero = Carbon::create($anio, 1, 1)->startOfDay();
        $ultimo = Carbon::create($anio, 12, 31)->startOfDay();

        if ($ultimo->lessThan(now()->startOfDay())) {
            return;
        }

        $desde = $primero->max(now()->startOfDay())->toDateString();
        $hasta = $ultimo->toDateString();

        $semana = Zap::for($this)
            ->named($nombre.' · semana')
            ->availability()
            ->from($desde)
            ->to($hasta)
            ->weekly(['monday', 'tuesday', 'wednesday', 'thursday', 'friday']);

        foreach ($horarios['semana'] as [$abre, $cierra]) {
            $semana->addPeriod($abre, $cierra);
        }

        $semana->save();

        $sabado = Zap::for($this)
            ->named($nombre.' · sábado')
            ->availability()
            ->from($desde)
            ->to($hasta)
            ->weekly(['saturday']);

        foreach ($horarios['sabado'] as [$abre, $cierra]) {
            $sabado->addPeriod($abre, $cierra);
        }

        $sabado->save();
    }

    /**
     * Los puestos de un rubro que se tienen en cuenta al calcular horarios.
     *
     * @return Collection<int, static>
     */
    public static function activos(Rubro $rubro): Collection
    {
        return static::query()
            ->where('rubro', $rubro)
            ->where('activo', true)
            ->orderBy('id')
            ->get();
    }

    /**
     * Las horas de inicio libres de un día para un servicio.
     *
     * Se pide a cada puesto del rubro del servicio su lista de huecos y se
     * unen: alcanza con que uno lo tenga libre para poder ofrecerlo. Zap
     * devuelve todos los huecos de la agenda con una marca `is_available`, así
     * que hay que filtrarla.
     *
     * @return list<string> horas `H:i`, de la más temprana a la más tardía
     */
    public static function huecosDelDia(CarbonInterface $fecha, Servicio $servicio): array
    {
        $dia = $fecha->format('Y-m-d');
        $buffer = (int) $servicio->rubro->config('buffer');

        $horas = [];

        foreach (static::activos($servicio->rubro) as $puesto) {
            foreach ($puesto->getBookableSlots($dia, $servicio->duracion, $buffer) as $hueco) {
                if ($hueco['is_available'] === true) {
                    $horas[] = (string) $hueco['start_time'];
                }
            }
        }

        $horas = array_values(array_unique($horas));
        sort($horas);

        return $horas;
    }

    /**
     * El primer puesto libre para un turno que arranca a esta hora.
     *
     * Devuelve `null` si no queda ninguno: es lo que decide si la reserva entra
     * o se rechaza. Se vuelve a llamar dentro de la transacción del alta, para
     * que dos personas no se queden con el mismo hueco.
     */
    public static function libreEn(CarbonInterface $inicio, Servicio $servicio): ?static
    {
        $dia = $inicio->format('Y-m-d');
        $desde = $inicio->format('H:i');
        $hasta = $inicio->copy()->addMinutes($servicio->duracion)->format('H:i');
        $buffer = (int) $servicio->rubro->config('buffer');

        foreach (static::activos($servicio->rubro) as $puesto) {
            if ($puesto->isBookableAtTime($dia, $desde, $hasta, null, $servicio->duracion, $buffer)) {
                return $puesto;
            }
        }

        return null;
    }
}
