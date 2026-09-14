<?php

namespace App\Enums;

use App\Concerns\ProvidesSelectOptions;

/**
 * De dónde salió un turno: lo pidió un cliente o lo cargó el taller.
 */
enum OrigenTurno: string
{
    use ProvidesSelectOptions;

    case Web = 'web';
    case Panel = 'panel';

    /**
     * Get the human readable label for the origin.
     */
    public function label(): string
    {
        return match ($this) {
            self::Web => 'Reserva web',
            self::Panel => 'Carga interna',
        };
    }
}
