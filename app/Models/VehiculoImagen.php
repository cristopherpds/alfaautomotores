<?php

namespace App\Models;

use Database\Factories\VehiculoImagenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Una foto de la galería de un vehículo.
 *
 * El archivo vive en el disco `public`; `ruta` es la ruta relativa dentro de
 * ese disco. La portada es simplemente la de menor `orden`.
 *
 * @property int $id
 * @property int $vehiculo_id
 * @property string $ruta
 * @property int $orden
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Vehiculo $vehiculo
 */
#[Fillable(['ruta', 'orden'])]
class VehiculoImagen extends Model
{
    /** @use HasFactory<VehiculoImagenFactory> */
    use HasFactory;

    /**
     * El pluralizador inglés no acierta con "imagen".
     *
     * @var string
     */
    protected $table = 'vehiculo_imagenes';

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
     * @return BelongsTo<Vehiculo, $this>
     */
    public function vehiculo(): BelongsTo
    {
        return $this->belongsTo(Vehiculo::class);
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
