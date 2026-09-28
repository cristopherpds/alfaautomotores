<?php

namespace App\Enums;

use App\Concerns\ProvidesSelectOptions;

/**
 * Qué clase de rodado es un producto del catálogo de movilidad.
 *
 * Parte el catálogo en dos páginas públicas: `Bicicleta` —la bici sin motor—
 * va sola en `/bicicletas`, y todas las demás en `/movilidad`. Esa es la única
 * definición de la frontera: la consultan `esBicicleta()`, el scope
 * `Producto::deSeccion()` y los chips de familia de las dos grillas.
 *
 * La diferencia no es cosmética: las bicicletas se publican sin precio (se
 * cotizan por WhatsApp), así que la página no lleva filtro de precio.
 */
enum FamiliaProducto: string
{
    use ProvidesSelectOptions;

    case Moto = 'moto';
    case Scooter = 'scooter';
    case Monopatin = 'monopatin';
    case Hoverboard = 'hoverboard';
    case BiciElectrica = 'bici_electrica';
    case Triciclo = 'triciclo';
    case Bicicleta = 'bicicleta';

    /**
     * Get the human readable label for the family.
     */
    public function label(): string
    {
        return match ($this) {
            self::Moto => 'Motos eléctricas',
            self::Scooter => 'Scooters',
            self::Monopatin => 'Monopatines',
            self::Hoverboard => 'Hoverboards',
            self::BiciElectrica => 'Bicicletas eléctricas',
            self::Triciclo => 'Triciclos',
            self::Bicicleta => 'Bicicletas',
        };
    }

    /**
     * Si la familia va en `/bicicletas` en vez de en `/movilidad`.
     */
    public function esBicicleta(): bool
    {
        return $this === self::Bicicleta;
    }

    /**
     * Las familias de la sección de movilidad, en el orden de los chips.
     *
     * @return array<int, self>
     */
    public static function deMovilidad(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $familia): bool => ! $familia->esBicicleta(),
        ));
    }
}
