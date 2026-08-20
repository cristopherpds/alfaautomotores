<?php

namespace App\Enums;

use App\Concerns\ProvidesSelectOptions;

/**
 * Moneda en la que se publica el precio.
 */
enum Moneda: string
{
    use ProvidesSelectOptions;

    case Usd = 'USD';
    case Uyu = 'UYU';

    public function label(): string
    {
        return match ($this) {
            self::Usd => 'Dólares (USD)',
            self::Uyu => 'Pesos uruguayos (UYU)',
        };
    }
}
