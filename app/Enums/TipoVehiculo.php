<?php

namespace App\Enums;

use App\Concerns\ProvidesSelectOptions;

/**
 * Carrocería del vehículo.
 *
 * El valor guardado es el texto que ve el visitante: los filtros del catálogo
 * (`opcionesDe()` en `resources/js/lib/catalogo.ts`) los derivan del stock tal
 * cual vienen.
 */
enum TipoVehiculo: string
{
    use ProvidesSelectOptions;

    case Hatchback = 'Hatchback';
    case Sedan = 'Sedán';
    case Suv = 'SUV';
    case PickUp = 'Pick-up';
    case Utilitario = 'Utilitario';

    public function label(): string
    {
        return $this->value;
    }
}
