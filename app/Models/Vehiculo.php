<?php

namespace App\Models;

use Illuminate\Support\Collection;
use JsonSerializable;

/**
 * Vehículo del stock en venta.
 *
 * Provisorio: todavía no hay tabla. Las filas salen de
 * `database/data/vehiculos.json`, el stock mockeado que se trajo del proyecto
 * Next (`scripts/vehiculos-mock.ts`). Cuando exista la migración esta clase
 * pasa a ser el modelo Eloquent: las páginas consumen estos mismos métodos y
 * no cambian.
 */
class Vehiculo implements JsonSerializable
{
    /**
     * Estado interno de un vehículo a medio cargar: nunca sale al público.
     */
    public const ESTADO_BORRADOR = 'borrador';

    /**
     * Todo el stock del archivo, cacheado por request.
     *
     * @var Collection<int, self>|null
     */
    private static ?Collection $stock = null;

    public function __construct(
        public readonly string $slug,
        public readonly string $marca,
        public readonly string $modelo,
        public readonly ?string $version,
        public readonly int $anio,
        public readonly int $km,
        public readonly int $precio,
        public readonly string $moneda,
        public readonly string $comb,
        public readonly string $trans,
        public readonly string $tipo,
        public readonly string $estado,
        public readonly string $desc,
    ) {}

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
        return self::stock()
            ->reject(fn (self $vehiculo): bool => $vehiculo->estado === self::ESTADO_BORRADOR)
            ->values();
    }

    /**
     * Los destacados de la portada: lo último que entró y está a la venta.
     *
     * @return Collection<int, self>
     */
    public static function destacados(int $cantidad = 6): Collection
    {
        return self::publicos()
            ->filter(fn (self $vehiculo): bool => $vehiculo->estado === 'publicado')
            ->take($cantidad)
            ->values();
    }

    public static function buscar(string $slug): ?self
    {
        return self::publicos()->first(fn (self $vehiculo): bool => $vehiculo->slug === $slug);
    }

    /**
     * Hasta tres vehículos del mismo tipo, a la venta, excluyendo el actual.
     *
     * @return Collection<int, self>
     */
    public static function similares(self $vehiculo, int $cantidad = 3): Collection
    {
        return self::publicos()
            ->filter(fn (self $otro): bool => $otro->estado === 'publicado'
                && $otro->tipo === $vehiculo->tipo
                && $otro->slug !== $vehiculo->slug)
            ->take($cantidad)
            ->values();
    }

    public static function contar(): int
    {
        return self::publicos()->count();
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'slug' => $this->slug,
            'marca' => $this->marca,
            'modelo' => $this->modelo,
            'version' => $this->version,
            'anio' => $this->anio,
            'km' => $this->km,
            'precio' => $this->precio,
            'moneda' => $this->moneda,
            'comb' => $this->comb,
            'trans' => $this->trans,
            'tipo' => $this->tipo,
            'estado' => $this->estado,
            'desc' => $this->desc,
        ];
    }

    /**
     * @return Collection<int, self>
     */
    private static function stock(): Collection
    {
        if (self::$stock instanceof Collection) {
            return self::$stock;
        }

        $filas = json_decode(
            (string) file_get_contents(database_path('data/vehiculos.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $stock = new Collection;

        foreach (is_array($filas) ? $filas : [] as $fila) {
            if (is_array($fila)) {
                $stock->push(self::desdeFila($fila));
            }
        }

        return self::$stock = $stock;
    }

    /**
     * @param  array<mixed, mixed>  $fila
     */
    private static function desdeFila(array $fila): self
    {
        return new self(
            slug: (string) $fila['slug'],
            marca: (string) $fila['marca'],
            modelo: (string) $fila['modelo'],
            version: isset($fila['version']) ? (string) $fila['version'] : null,
            anio: (int) $fila['anio'],
            km: (int) $fila['km'],
            precio: (int) $fila['precio'],
            moneda: (string) $fila['moneda'],
            comb: (string) $fila['comb'],
            trans: (string) $fila['trans'],
            tipo: (string) $fila['tipo'],
            estado: (string) $fila['estado'],
            desc: (string) $fila['desc'],
        );
    }
}
