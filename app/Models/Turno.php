<?php

namespace App\Models;

use App\Enums\EstadoTurno;
use App\Enums\OrigenTurno;
use Carbon\CarbonInterface;
use Database\Factories\TurnoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Zap\Facades\Zap;

/**
 * Un turno del taller.
 *
 * El turno es la fuente de verdad; la cita de Zap (`schedule_id`) es el espejo
 * que hace que el horario deje de ofrecerse. Se guarda así, y no todo dentro de
 * Zap, porque el panel filtra por estado, ordena por fecha y muestra datos del
 * cliente: contra las tablas genéricas del paquete eso sería JSON sin tipos.
 *
 * La regla que se sigue de eso: **el estado y la cita se mueven juntos**.
 * `reservar()` crea las dos cosas en una transacción y `liberar()` borra la
 * cita, que es lo que devuelve el hueco a la web.
 *
 * @property int $id
 * @property int $servicio_id
 * @property int $puesto_id
 * @property int|null $schedule_id
 * @property EstadoTurno $estado
 * @property OrigenTurno $origen
 * @property Carbon $inicia_at
 * @property Carbon $termina_at
 * @property string $nombre
 * @property string $apellido
 * @property string $email
 * @property string $celular
 * @property string|null $vehiculo_marca
 * @property string|null $vehiculo_modelo
 * @property int|null $vehiculo_anio
 * @property string|null $matricula
 * @property string|null $comentario
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Servicio $servicio
 * @property-read Puesto $puesto
 */
#[Fillable([
    'servicio_id', 'puesto_id', 'schedule_id', 'estado', 'origen',
    'inicia_at', 'termina_at', 'nombre', 'apellido', 'email', 'celular',
    'vehiculo_marca', 'vehiculo_modelo', 'vehiculo_anio', 'matricula', 'comentario',
])]
class Turno extends Model
{
    /** @use HasFactory<TurnoFactory> */
    use HasFactory;

    /**
     * Borrar un turno tiene que llevarse su cita: si no, el horario queda
     * ocupado para siempre por una reserva que ya no existe.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $turno): void {
            $turno->borrarLaCita();
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estado' => EstadoTurno::class,
            'origen' => OrigenTurno::class,
            'inicia_at' => 'datetime',
            'termina_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Servicio, $this>
     */
    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class);
    }

    /**
     * @return BelongsTo<Puesto, $this>
     */
    public function puesto(): BelongsTo
    {
        return $this->belongsTo(Puesto::class);
    }

    /**
     * Los turnos que caen dentro de un rango, para el calendario del panel.
     *
     * @return Builder<static>
     */
    public static function entre(CarbonInterface $desde, CarbonInterface $hasta): Builder
    {
        return static::query()
            ->with(['servicio', 'puesto'])
            ->whereBetween('inicia_at', [$desde, $hasta])
            ->orderBy('inicia_at');
    }

    /**
     * Reservar un horario: crea la cita en Zap y el turno, o devuelve `null`.
     *
     * Devuelve `null` cuando no queda ningún puesto libre a esa hora. La
     * comprobación se rehace **dentro** de la transacción para que dos personas
     * que aprietan «Confirmar» a la vez no se lleven el mismo hueco.
     *
     * @param  array<string, mixed>  $datos  los campos del cliente y su vehículo
     */
    public static function reservar(
        Servicio $servicio,
        CarbonInterface $inicio,
        array $datos,
        EstadoTurno $estado = EstadoTurno::Pendiente,
        OrigenTurno $origen = OrigenTurno::Web,
    ): ?self {
        return DB::transaction(function () use ($servicio, $inicio, $datos, $estado, $origen): ?self {
            /* El bloqueo no hace nada en SQLite, pero la transacción igual
               serializa las escrituras; en MySQL sí frena la carrera. Se acota
               al rubro del servicio: un lavado y un service no compiten por el
               mismo puesto, así que tampoco tienen por qué esperarse. */
            Puesto::query()
                ->where('activo', true)
                ->where('rubro', $servicio->rubro)
                ->lockForUpdate()
                ->get();

            $puesto = Puesto::libreEn($inicio, $servicio);

            if ($puesto === null) {
                return null;
            }

            $fin = $inicio->copy()->addMinutes($servicio->duracion);

            $cita = Zap::for($puesto)
                ->named($servicio->nombre.' · '.$datos['apellido'])
                ->appointment()
                ->from($inicio->format('Y-m-d'))
                ->addPeriod($inicio->format('H:i'), $fin->format('H:i'))
                ->withMetadata(['servicio' => $servicio->slug])
                ->save();

            return static::create([
                ...$datos,
                'servicio_id' => $servicio->id,
                'puesto_id' => $puesto->id,
                'schedule_id' => $cita->id,
                'estado' => $estado,
                'origen' => $origen,
                'inicia_at' => $inicio,
                'termina_at' => $fin,
            ]);
        });
    }

    /**
     * Cancelar el turno y devolver el horario a la web.
     */
    public function cancelar(): void
    {
        $this->borrarLaCita();

        $this->estado = EstadoTurno::Cancelado;
        $this->schedule_id = null;
        $this->save();
    }

    /**
     * El nombre completo del cliente.
     */
    public function cliente(): string
    {
        return trim($this->nombre.' '.$this->apellido);
    }

    /**
     * El vehículo en una línea, si lo dejaron anotado.
     */
    public function vehiculo(): ?string
    {
        $partes = array_filter([
            $this->vehiculo_marca,
            $this->vehiculo_modelo,
            $this->vehiculo_anio === null ? null : (string) $this->vehiculo_anio,
        ]);

        return $partes === [] ? null : implode(' ', $partes);
    }

    /**
     * Borrar la cita espejo, que es lo que libera el hueco.
     */
    private function borrarLaCita(): void
    {
        if ($this->schedule_id === null) {
            return;
        }

        $this->puesto->schedules()->whereKey($this->schedule_id)->delete();
    }
}
