<?php

namespace App\Models;

use App\Enums\AreaServicio;
use App\Enums\Rubro;
use Database\Factories\ServicioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Un trabajo que se vende y se agenda: nombre, cuánto lleva y su foto.
 *
 * El `rubro` dice de qué negocio es —taller o lavadero— y con él se decide qué
 * puestos miran sus horarios. El `area` sólo lo usa el taller: los servicios
 * del lavadero son tamaños de vehículo y van sin área, con `precio` cargado.
 *
 * `duracion` está en minutos y es lo que mide el turno: de ahí sale el largo de
 * los huecos que se ofrecen en la web.
 *
 * Los trabajos mayores llevan `agendable = false`: se muestran en la grilla
 * pero se cotizan con el vehículo en el taller, así que su tarjeta invita a
 * escribir por WhatsApp en vez de reservar.
 *
 * @property int $id
 * @property string $slug
 * @property string $nombre
 * @property Rubro $rubro
 * @property AreaServicio|null $area
 * @property int $duracion
 * @property int|null $precio
 * @property string $descripcion
 * @property string|null $foto
 * @property bool $activo
 * @property bool $agendable
 * @property int $orden
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Turno> $turnos
 */
#[Fillable([
    'slug', 'rubro', 'nombre', 'area', 'duracion', 'precio', 'descripcion',
    'foto', 'activo', 'agendable', 'orden',
])]
class Servicio extends Model
{
    /** @use HasFactory<ServicioFactory> */
    use HasFactory;

    /**
     * El pluralizador inglés no acierta con "servicio".
     *
     * @var string
     */
    protected $table = 'servicios';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rubro' => Rubro::class,
            'area' => AreaServicio::class,
            'duracion' => 'integer',
            'precio' => 'integer',
            'activo' => 'boolean',
            'agendable' => 'boolean',
            'orden' => 'integer',
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
     * Los servicios de un rubro que ve el sitio público, ya ordenados.
     *
     * El rubro es obligatorio a propósito: sin él los lavados aparecerían en la
     * grilla del taller y al revés.
     *
     * @return Collection<int, static>
     */
    public static function publicos(Rubro $rubro): Collection
    {
        return static::query()
            ->where('rubro', $rubro)
            ->where('activo', true)
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();
    }

    /**
     * URL pública de la foto, si tiene una cargada.
     */
    public function url(): ?string
    {
        return $this->foto === null ? null : Storage::disk('public')->url($this->foto);
    }

    /**
     * La duración en palabras: `1 h 30 min`.
     */
    public function duracionLegible(): string
    {
        $horas = intdiv($this->duracion, 60);
        $minutos = $this->duracion % 60;

        return match (true) {
            $horas === 0 => "{$minutos} min",
            $minutos === 0 => "{$horas} h",
            default => "{$horas} h {$minutos} min",
        };
    }

    /**
     * El precio en palabras: `$ 500`. Null cuando el servicio no lleva precio
     * de lista, que es el caso de todo el taller.
     */
    public function precioLegible(): ?string
    {
        return $this->precio === null
            ? null
            : '$ '.number_format($this->precio, 0, ',', '.');
    }

    /**
     * Borrar el archivo del disco junto con la fila.
     */
    public function borrarConArchivo(): void
    {
        if ($this->foto !== null) {
            Storage::disk('public')->delete($this->foto);
        }

        $this->delete();
    }
}
