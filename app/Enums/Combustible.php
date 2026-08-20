<?php

namespace App\Enums;

use App\Concerns\ProvidesSelectOptions;

/**
 * Combustible del vehículo. El valor guardado es el texto visible.
 */
enum Combustible: string
{
    use ProvidesSelectOptions;

    case Nafta = 'Nafta';
    case Diesel = 'Diésel';
    case Hibrido = 'Híbrido';
    case Electrico = 'Eléctrico';

    public function label(): string
    {
        return $this->value;
    }
}
