<?php

namespace App\Enums;

use App\Concerns\ProvidesSelectOptions;

/**
 * Qué lleva de fondo un slide del hero de la portada.
 */
enum TipoFondo: string
{
    use ProvidesSelectOptions;

    case Imagen = 'imagen';
    case Video = 'video';

    /**
     * Get the human readable label for the background type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Imagen => 'Imagen',
            self::Video => 'Video',
        };
    }
}
