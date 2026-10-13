<?php

namespace App\Models;

use App\Enums\DireccionMensaje;
use App\Enums\EstadoMensaje;
use App\Enums\TipoMensaje;
use Database\Factories\MensajeWhatsappFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Un mensaje de WhatsApp, entrante o saliente.
 *
 * No usa `Auditable`: los mensajes ya son el registro de la conversación.
 * `wa_message_id` es la clave de idempotencia del webhook (Meta reenvía) y
 * de los estados de entrega, que sólo avanzan (`EstadoMensaje::puedePasarA()`).
 *
 * @property int $id
 * @property int $conversacion_id
 * @property DireccionMensaje $direccion
 * @property TipoMensaje $tipo
 * @property string|null $cuerpo
 * @property string|null $media_id
 * @property string|null $media_ruta
 * @property string|null $media_mime
 * @property string|null $media_nombre
 * @property string|null $wa_message_id
 * @property EstadoMensaje $estado
 * @property string|null $error
 * @property bool $generado_por_ia
 * @property int|null $enviado_por
 * @property string|null $plantilla
 * @property Carbon|null $wa_timestamp
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Conversacion $conversacion
 * @property-read User|null $autor
 */
#[Fillable([
    'direccion', 'tipo', 'cuerpo', 'media_id', 'media_ruta', 'media_mime', 'media_nombre',
    'wa_message_id', 'estado', 'error', 'generado_por_ia', 'enviado_por', 'plantilla',
    'wa_timestamp', 'meta',
])]
class MensajeWhatsapp extends Model
{
    /** @use HasFactory<MensajeWhatsappFactory> */
    use HasFactory;

    protected $table = 'mensajes_whatsapp';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direccion' => DireccionMensaje::class,
            'tipo' => TipoMensaje::class,
            'estado' => EstadoMensaje::class,
            'generado_por_ia' => 'boolean',
            'wa_timestamp' => 'datetime',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Conversacion, $this>
     */
    public function conversacion(): BelongsTo
    {
        return $this->belongsTo(Conversacion::class);
    }

    /**
     * Quién del equipo lo mandó desde el panel.
     *
     * @return BelongsTo<User, $this>
     */
    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enviado_por');
    }

    public function esEntrante(): bool
    {
        return $this->direccion === DireccionMensaje::Entrante;
    }

    /**
     * La línea de la bandeja: el texto o qué tipo de adjunto es.
     */
    public function vistaPrevia(): string
    {
        $texto = trim((string) $this->cuerpo);

        return $texto !== '' ? $texto : $this->tipo->etiqueta();
    }

    /**
     * Aplicar un estado de entrega de Meta si es un avance.
     */
    public function avanzarA(EstadoMensaje $estado, ?string $error = null): void
    {
        if (! $this->estado->puedePasarA($estado)) {
            return;
        }

        $this->estado = $estado;

        if ($estado === EstadoMensaje::Fallido) {
            $this->error = $error;
        }

        $this->save();
    }
}
