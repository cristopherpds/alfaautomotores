<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un horario que el agente le ofreció a una conversación.
 *
 * Es la garantía de nea-agent: `ReservarTurno` sólo acepta un horario de
 * esta tabla, ofrecido hace poco, para el mismo servicio. El modelo no puede
 * inventar una hora ni reservar la que el cliente tiró de memoria.
 *
 * @property int $id
 * @property int $conversacion_id
 * @property int $servicio_id
 * @property Carbon $inicia_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Servicio $servicio
 */
#[Fillable(['conversacion_id', 'servicio_id', 'inicia_at'])]
class HorarioOfrecido extends Model
{
    protected $table = 'horarios_ofrecidos';

    /**
     * Cuánto vale una oferta: pasado esto hay que volver a consultar la
     * agenda, que pudo cambiar.
     */
    public const HORAS_DE_VIGENCIA = 48;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'inicia_at' => 'datetime',
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
     * Si ese horario se le ofreció a esa conversación para ese servicio.
     */
    public static function fueOfrecido(Conversacion $conversacion, Servicio $servicio, CarbonInterface $inicio): bool
    {
        return self::query()
            ->where('conversacion_id', $conversacion->id)
            ->where('servicio_id', $servicio->id)
            ->where('inicia_at', $inicio->format('Y-m-d H:i:s'))
            ->where('created_at', '>=', now()->subHours(self::HORAS_DE_VIGENCIA))
            ->exists();
    }

    /**
     * Los horarios vigentes ofrecidos en la conversación, para el prompt.
     *
     * @return Collection<int, self>
     */
    public static function vigentes(Conversacion $conversacion): Collection
    {
        return self::query()
            ->with('servicio')
            ->where('conversacion_id', $conversacion->id)
            ->where('created_at', '>=', now()->subHours(self::HORAS_DE_VIGENCIA))
            ->where('inicia_at', '>', now())
            ->orderBy('inicia_at')
            ->get();
    }
}
