<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\EstadoProducto;
use App\Enums\FamiliaProducto;
use App\Enums\Moneda;
use Database\Factories\ProductoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Un rodado del catálogo de movilidad: motos y scooters eléctricos,
 * monopatines, triciclos, bicicletas eléctricas y bicicletas comunes.
 *
 * La API estática — `deSeccion()`, `buscar()`, `similares()`, `contar()` — es
 * el contrato que consume `ProductoController`, igual que la de `Vehiculo`
 * para el catálogo de autos: devuelve productos listos para el sitio público,
 * con sus fotos precargadas.
 *
 * `#[Hidden]` deja la serialización en los campos que espera el tipo
 * `Producto` de `resources/js/types/alfa.ts`, más el accessor `imagenes`.
 *
 * @property int $id
 * @property string $slug
 * @property string $nombre
 * @property FamiliaProducto $familia
 * @property string|null $codigo
 * @property int|null $precio
 * @property Moneda $moneda
 * @property EstadoProducto $estado
 * @property string $resumen
 * @property string $desc
 * @property array<int, array{0: string, 1: string}> $specs
 * @property array<int, string> $colores
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, ProductoImagen> $fotos
 * @property-read array<int, string> $imagenes
 */
#[Fillable([
    'slug', 'nombre', 'familia', 'codigo', 'precio', 'moneda',
    'estado', 'resumen', 'desc', 'specs', 'colores',
])]
#[Hidden(['id', 'fotos', 'created_at', 'updated_at'])]
class Producto extends Model
{
    /** @use HasFactory<ProductoFactory> */
    use Auditable, HasFactory;

    /**
     * La clave del registro en la auditoría.
     */
    public function tipoDeAuditoria(): string
    {
        return 'producto';
    }

    /**
     * Cómo figura el producto en la auditoría.
     */
    public function etiquetaDeAuditoria(): string
    {
        return $this->nombre;
    }

    /**
     * Cuántas fotos admite la galería de un producto.
     */
    public const MAX_IMAGENES = 8;

    /**
     * `familia` y `estado` viajan como el valor del enum y los traduce el
     * front (`familiaLegible()` y `estadoLegible()` en `lib/productos.ts`),
     * igual que hace el catálogo de autos con el estado del vehículo.
     *
     * @var list<string>
     */
    protected $appends = ['imagenes'];

    /**
     * Al borrar el producto se van también los archivos de su galería: las
     * filas las arrastra la foreign key, los archivos no.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $producto): void {
            Storage::disk('public')->deleteDirectory($producto->carpetaDeFotos());
        });
    }

    /**
     * Carpeta del disco `public` donde viven las fotos del producto.
     */
    public function carpetaDeFotos(): string
    {
        return 'productos/'.$this->getKey();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'precio' => 'integer',
            'moneda' => Moneda::class,
            'familia' => FamiliaProducto::class,
            'estado' => EstadoProducto::class,
            'specs' => 'array',
            'colores' => 'array',
        ];
    }

    /**
     * Las fotos del producto, de la portada a la última.
     *
     * @return HasMany<ProductoImagen, $this>
     */
    public function fotos(): HasMany
    {
        return $this->hasMany(ProductoImagen::class)->orderBy('orden')->orderBy('id');
    }

    /**
     * Las URLs de las fotos, que es lo único que necesita el sitio público.
     *
     * Se llama distinto que la relación a propósito: un accessor y una relación
     * no pueden compartir nombre.
     *
     * @return Attribute<array<int, string>, never>
     */
    protected function imagenes(): Attribute
    {
        return Attribute::get(
            fn (): array => $this->fotos->map(fn (ProductoImagen $foto): string => $foto->url())->all(),
        );
    }

    /**
     * Si el producto se publica con precio.
     *
     * Las bicicletas se cotizan por WhatsApp, así que van sin precio y las dos
     * grillas muestran «Consultar precio» en su lugar.
     */
    public function tienePrecio(): bool
    {
        return $this->precio !== null;
    }

    /**
     * Todo el catálogo de una sección, del más nuevo al más viejo.
     *
     * `$bicicletas` elige la página: false son las seis familias de movilidad,
     * true la bicicleta sola. Es la misma frontera que define
     * `FamiliaProducto::esBicicleta()`.
     *
     * @return Collection<int, self>
     */
    public static function deSeccion(bool $bicicletas): Collection
    {
        return self::query()
            ->whereNot('estado', EstadoProducto::Borrador)
            ->where(
                'familia',
                $bicicletas ? '=' : '!=',
                FamiliaProducto::Bicicleta,
            )
            ->with('fotos')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    public static function buscar(string $slug): ?self
    {
        return self::query()
            ->whereNot('estado', EstadoProducto::Borrador)
            ->where('slug', $slug)
            ->with('fotos')
            ->first();
    }

    /**
     * Hasta tres productos de la misma familia, excluyendo el actual.
     *
     * @return Collection<int, self>
     */
    public static function similares(self $producto, int $cantidad = 3): Collection
    {
        return self::query()
            ->whereNot('estado', EstadoProducto::Borrador)
            ->where('familia', $producto->familia)
            ->whereKeyNot($producto->getKey())
            ->with('fotos')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->take($cantidad)
            ->get();
    }

    /**
     * Cuántos productos de una sección ve el público.
     */
    public static function contar(bool $bicicletas): int
    {
        return self::query()
            ->whereNot('estado', EstadoProducto::Borrador)
            ->where(
                'familia',
                $bicicletas ? '=' : '!=',
                FamiliaProducto::Bicicleta,
            )
            ->count();
    }
}
