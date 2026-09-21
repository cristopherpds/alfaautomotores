<?php

namespace App\Models;

use Database\Factories\EntregaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Una entrega: la foto de un cliente retirando su vehículo del local.
 *
 * Alimentan la tira «Nuestros clientes» de la portada. El archivo vive en el
 * disco `public`, bajo `entregas/`; `ruta` es la ruta relativa dentro de ese
 * disco. `fecha` es la de la entrega —la que se imprime sobre la foto—, no la
 * de carga: las fotos viejas se suben con su fecha.
 *
 * `paraLaTira()` es el contrato de `HomeController`: devuelve exactamente los
 * cuatro campos del tipo `Entrega` de `resources/js/types/alfa.ts`. No se
 * cambia de firma.
 *
 * @property int $id
 * @property string $ruta
 * @property Carbon $fecha
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['ruta', 'fecha'])]
class Entrega extends Model
{
    /** @use HasFactory<EntregaFactory> */
    use HasFactory;

    /**
     * Cuántas fotos admite una misma carga.
     *
     * El tope no es del negocio sino de PHP: doce archivos de 4 MB ya piden un
     * `post_max_size` generoso, y cuando se pasa, el POST llega vacío y el
     * error que se ve ("el campo fotos es obligatorio") no explica nada.
     */
    public const MAX_POR_LOTE = 12;

    /**
     * Carpeta de las fotos dentro del disco `public`.
     *
     * @var string
     */
    public const CARPETA = 'entregas';

    /**
     * Los meses como los escribe el local: «setiembre», no «septiembre».
     *
     * Por eso van a mano y no con `Carbon::translatedFormat()` en locale es.
     *
     * @var list<string>
     */
    private const MESES = [
        'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
        'julio', 'agosto', 'setiembre', 'octubre', 'noviembre', 'diciembre',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
        ];
    }

    /**
     * De la más nueva a la más vieja.
     *
     * Única definición del orden: la consultan la portada y el panel. El id
     * desempata las del mismo día, que es lo que antes hacía el número de
     * adelante en el nombre del archivo.
     *
     * @return Builder<static>
     */
    public static function ordenadas(): Builder
    {
        return static::query()
            ->orderByDesc('fecha')
            ->orderByDesc('id');
    }

    /**
     * La tira de la portada, lista para Inertia.
     *
     * @return list<array{url: string, fecha: string, etiqueta: string, legible: string}>
     */
    public static function paraLaTira(): array
    {
        return array_values(
            static::ordenadas()
                ->get()
                ->map(fn (self $entrega): array => $entrega->datos())
                ->all()
        );
    }

    /**
     * Los datos que consume la tira.
     *
     * @return array{url: string, fecha: string, etiqueta: string, legible: string}
     */
    public function datos(): array
    {
        return [
            'url' => $this->url(),
            'fecha' => $this->fecha->toDateString(),
            'etiqueta' => $this->fecha->format('d.m.y'),
            'legible' => $this->legible(),
        ];
    }

    /**
     * URL pública de la foto.
     */
    public function url(): string
    {
        return Storage::disk('public')->url($this->ruta);
    }

    /**
     * La fecha en palabras, para el texto alternativo: `14 de noviembre de 2025`.
     */
    public function legible(): string
    {
        return $this->fecha->day.' de '.self::MESES[$this->fecha->month - 1].' de '.$this->fecha->year;
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
