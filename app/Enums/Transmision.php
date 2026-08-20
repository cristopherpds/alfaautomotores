<?php

namespace App\Enums;

use App\Concerns\ProvidesSelectOptions;

/**
 * Caja del vehículo. El valor guardado es el texto visible.
 */
enum Transmision: string
{
    use ProvidesSelectOptions;

    case Manual = 'Manual';
    case Automatica = 'Automática';

    public function label(): string
    {
        return $this->value;
    }
}
