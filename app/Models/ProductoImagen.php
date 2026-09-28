<?php

namespace App\Models;

use App\Concerns\Auditable;
use Database\Factories\ProductoImagenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Una foto de la galería de un producto de movilidad.
 *
 * Calcada de `VehiculoImagen`: el archivo vive en el disco `public`, `ruta` es
 * la ruta relativa dentro de ese disco y la portada es la de menor `orden`.
 *
 * @property int $id
 * @property int $producto_id
 * @property string $ruta
 * @property int $orden
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Producto $producto
 */
#[Fillable(['ruta', 'orden'])]
class ProductoImagen extends Model
{
    /** @use HasFactory<ProductoImagenFactory> */
    use Auditable, HasFactory;

    /**
     * La clave del registro en la auditoría.
     */
    public function tipoDeAuditoria(): string
    {
        return 'foto_producto';
    }

    /**
     * Cómo figura la foto en la auditoría: por el producto al que pertenece.
     */
    public function etiquetaDeAuditoria(): string
    {
        return 'Foto de '.($this->producto->nombre ?? 'un producto borrado');
    }

    /**
     * El pluralizador inglés no acierta con "imagen".
     *
     * @var string
     */
    protected $table = 'producto_imagenes';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'orden' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Producto, $this>
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    /**
     * URL pública de la foto.
     */
    public function url(): string
    {
        return Storage::disk('public')->url($this->ruta);
    }

    /**
     * Borrar el archivo del disco junto con la fila.
     */
    public function borrarConArchivo(): void
    {
        Storage::disk('public')->delete($this->ruta);

        $this->delete();
    }
}
