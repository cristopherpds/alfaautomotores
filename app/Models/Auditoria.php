<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\AccionAuditoria;
use Database\Factories\AuditoriaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Una entrada de la auditoría: quién creó, modificó o eliminó qué, y cuándo.
 *
 * Las escribe el trait `Auditable` desde los eventos de cada modelo; la tabla
 * es sólo de agregado y ninguna ruta la edita ni la borra.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $usuario
 * @property AccionAuditoria $accion
 * @property string $tipo
 * @property int $registro_id
 * @property string $etiqueta
 * @property array<string, array{0: mixed, 1: mixed}>|null $cambios
 * @property string|null $ip
 * @property Carbon $created_at
 * @property-read User|null $autor
 */
#[Fillable([
    'user_id', 'usuario', 'accion', 'tipo', 'registro_id',
    'etiqueta', 'cambios', 'ip',
])]
class Auditoria extends Model
{
    /** @use HasFactory<AuditoriaFactory> */
    use HasFactory;

    /**
     * Una entrada no se modifica: no hay `updated_at`.
     */
    public const UPDATED_AT = null;

    /**
     * Cómo figura quien reservó un turno desde el sitio público.
     */
    public const CLIENTE_WEB = 'Cliente (sitio web)';

    /**
     * Cómo figura lo que se hizo por consola (seeders sueltos, tinker, comandos).
     */
    public const SISTEMA = 'Sistema';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accion' => AccionAuditoria::class,
            'cambios' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * El usuario que hizo el cambio, si todavía existe.
     *
     * Se llama `autor` y no `usuario` porque esa es la columna con el nombre
     * copiado, que sobrevive a la baja del usuario.
     *
     * @return BelongsTo<User, $this>
     */
    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Anotar lo que le pasó a un registro, con el usuario de la sesión.
     *
     * Sin sesión, si hay un request enrutado es un cliente del sitio público
     * (la reserva de un turno); si no, es la consola.
     *
     * @param  Model&Auditable  $registro
     * @param  array<string, array{0: mixed, 1: mixed}>|null  $cambios
     */
    public static function registrar(Model $registro, AccionAuditoria $accion, ?array $cambios = null): self
    {
        $actor = auth()->user();
        $esRequest = request()->route() !== null;

        return self::create([
            'user_id' => $actor?->getKey(),
            'usuario' => $actor instanceof User
                ? $actor->name
                : ($esRequest ? self::CLIENTE_WEB : self::SISTEMA),
            'accion' => $accion,
            'tipo' => $registro->tipoDeAuditoria(),
            'registro_id' => $registro->getKey(),
            'etiqueta' => $registro->etiquetaDeAuditoria(),
            'cambios' => $cambios,
            'ip' => $esRequest ? request()->ip() : null,
        ]);
    }
}
