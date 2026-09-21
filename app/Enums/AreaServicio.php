<?php

namespace App\Enums;

use App\Concerns\ProvidesSelectOptions;

/**
 * Área del taller a la que pertenece un servicio.
 *
 * Son los filtros de la grilla del sitio público. El valor guardado es el
 * slug; la etiqueta visible sale de `label()`.
 */
enum AreaServicio: string
{
    use ProvidesSelectOptions;

    case Mantenimiento = 'mantenimiento';
    case Frenos = 'frenos';
    case Motor = 'motor';
    case Clima = 'clima';
    case Diagnostico = 'diagnostico';

    /**
     * Get the human readable label for the area.
     */
    public function label(): string
    {
        return match ($this) {
            self::Mantenimiento => 'Mantenimiento',
            self::Frenos => 'Frenos y tren',
            self::Motor => 'Motor',
            self::Clima => 'Clima',
            self::Diagnostico => 'Diagnóstico',
        };
    }
}
