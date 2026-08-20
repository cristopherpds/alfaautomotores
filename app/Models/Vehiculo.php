<?php

namespace App\Models;

use App\Enums\Combustible;
use App\Enums\EstadoVehiculo;
use App\Enums\Moneda;
use App\Enums\TipoVehiculo;
use App\Enums\Transmision;
use Database\Factories\VehiculoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Vehículo del stock en venta.
 *
 * La API estática — `publicos()`, `destacados()`, `buscar()`, `similares()`,
 * `contar()` — es el contrato que consumen `HomeController` y
 * `VehiculoController`: devuelve siempre vehículos ya listos para el sitio
 * público, con sus fotos precargadas.
 *
 * `#[Hidden]` deja la serialización en los mismos campos que espera el tipo
 * `Vehiculo` de `resources/js/types/alfa.ts`; el panel no depende de eso, mapea
 * a mano en `Panel\VehiculoController::toListItem()`.
 *
 * @property int $id
 * @property string $slug
 * @property string $marca
 * @property string $modelo
 * @property string|null $version
 * @property int $anio
 * @property int $km
 * @property int $precio
 * @property Moneda $moneda
 * @property Combustible $comb
 * @property Transmision $trans
 * @property TipoVehiculo $tipo
 * @property EstadoVehiculo $estado
 * @property bool $destacado
 * @property string $desc
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, VehiculoImagen> $fotos
 * @property-read array<int, string> $imagenes
 */
#[Fillable([
    'slug', 'marca', 'modelo', 'version', 'anio', 'km', 'precio',
    'moneda', 'comb', 'trans', 'tipo', 'estado', 'desc',
])]
#[Hidden(['id', 'destacado', 'fotos', 'created_at', 'updated_at'])]
class Vehiculo extends Model
{
    /** @use HasFactory<VehiculoFactory> */
    use HasFactory;

    /**
     * Cuántos vehículos se pueden destacar a la vez en la portada.
     */
    public const MAX_DESTACADOS = 6;

    /**
     * Cuántas fotos admite la galería de un vehículo.
     */
    public const MAX_IMAGENES = 12;

    /**
     * @var list<string>
     */
    protected $appends = ['imagenes'];

    /**
     * Dos invariantes que no se delegan al controlador.
     *
     * Al borrar el vehículo se van también los archivos de su galería: las
     * filas las arrastra la foreign key, los archivos no.
     *
     * Y un borrador nunca queda destacado: se limpia acá y no en
     * `Panel\VehiculoController` para que valga también cuando el estado
     * cambia desde un seeder, una factory o tinker.
     */
    protected static function booted(): void
    {
        static::saving(function (self $vehiculo): void {
            if (! $vehiculo->esDestacable()) {
                $vehiculo->destacado = false;
            }
        });

        static::deleting(function (self $vehiculo): void {
            Storage::disk('public')->deleteDirectory($vehiculo->carpetaDeFotos());
        });
    }

    /**
     * Si el vehículo puede ir a la portada.
     *
     * Es la única definición de la regla: la consultan el hook de arriba,
     * `DestacarVehiculoRequest` y los payloads del panel. Destacar un borrador
     * ocuparía uno de los `MAX_DESTACADOS` lugares que nadie llega a ver.
     */
    public function esDestacable(): bool
    {
        return $this->estado !== EstadoVehiculo::Borrador;
    }

    /**
     * Carpeta del disco `public` donde viven las fotos del vehículo.
     */
    public function carpetaDeFotos(): string
    {
        return 'vehiculos/'.$this->getKey();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'anio' => 'integer',
            'km' => 'integer',
            'precio' => 'integer',
            'moneda' => Moneda::class,
            'comb' => Combustible::class,
            'trans' => Transmision::class,
            'tipo' => TipoVehiculo::class,
            'estado' => EstadoVehiculo::class,
            'destacado' => 'boolean',
        ];
    }

    /**
     * Las fotos del vehículo, de la portada a la última.
     *
     * @return HasMany<VehiculoImagen, $this>
     */
    public function fotos(): HasMany
    {
        return $this->hasMany(VehiculoImagen::class)->orderBy('orden')->orderBy('id');
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
            fn (): array => $this->fotos->map(fn (VehiculoImagen $foto): string => $foto->url())->all(),
        );
    }

    /**
     * Nombre comercial: marca, modelo y versión cuando la hay.
     */
    public function titulo(): string
    {
        return implode(' ', array_filter([$this->marca, $this->modelo, $this->version]));
    }

    /**
     * Todo el stock que se muestra al público, del más nuevo al más viejo.
     *
     * @return Collection<int, self>
     */
    public static function publicos(): Collection
    {
        return self::query()
            ->whereNot('estado', EstadoVehiculo::Borrador)
            ->with('fotos')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Los destacados de la portada.
     *
     * Primero los marcados a mano; si no llegan a `$cantidad`, la lista se
     * completa sola con los publicados más recientes.
     *
     * El filtro tiene que dar el mismo conjunto que cuenta
     * `contarDestacados()`, o el panel diría "6 de 6" mientras la portada
     * muestra menos: un marcado a mano entra en cualquier estado público
     * (reservado y vendido incluidos), y el relleno automático sigue siendo
     * sólo de publicados.
     *
     * @return Collection<int, self>
     */
    public static function destacados(int $cantidad = self::MAX_DESTACADOS): Collection
    {
        return self::query()
            ->whereNot('estado', EstadoVehiculo::Borrador)
            ->where(function (Builder $query): void {
                $query->where('destacado', true)
                    ->orWhere('estado', EstadoVehiculo::Publicado);
            })
            ->with('fotos')
            ->orderByDesc('destacado')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->take($cantidad)
            ->get();
    }

    public static function buscar(string $slug): ?self
    {
        return self::query()
            ->whereNot('estado', EstadoVehiculo::Borrador)
            ->where('slug', $slug)
            ->with('fotos')
            ->first();
    }

    /**
     * Hasta tres vehículos del mismo tipo, a la venta, excluyendo el actual.
     *
     * @return Collection<int, self>
     */
    public static function similares(self $vehiculo, int $cantidad = 3): Collection
    {
        return self::query()
            ->where('estado', EstadoVehiculo::Publicado)
            ->where('tipo', $vehiculo->tipo)
            ->whereKeyNot($vehiculo->getKey())
            ->with('fotos')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->take($cantidad)
            ->get();
    }

    /**
     * Cuántos vehículos ve el público.
     */
    public static function contar(): int
    {
        return self::query()
            ->whereNot('estado', EstadoVehiculo::Borrador)
            ->count();
    }

    /**
     * Cuántos de los `MAX_DESTACADOS` lugares de la portada están ocupados.
     *
     * Excluye borradores: el hook `saving` ya impide dejarlos destacados, pero
     * el filtro también cubre las filas que quedaron marcadas de antes.
     */
    public static function contarDestacados(): int
    {
        return self::query()
            ->where('destacado', true)
            ->whereNot('estado', EstadoVehiculo::Borrador)
            ->count();
    }
}
