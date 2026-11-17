<?php

namespace App\Enums;

use App\Concerns\ProvidesSelectOptions;

/**
 * Por qué una conversación pasó a una persona y la IA dejó de contestar.
 */
enum MotivoDerivacion: string
{
    use ProvidesSelectOptions;

    case Cliente = 'cliente';
    case Modelo = 'modelo';
    case Hostilidad = 'hostilidad';
    case Error = 'error';
    case Manual = 'manual';

    /**
     * Get the human readable label for the reason.
     */
    public function label(): string
    {
        return match ($this) {
            self::Cliente => 'Pidió hablar con una persona',
            self::Modelo => 'La IA no supo resolverlo',
            self::Hostilidad => 'Mensajes hostiles',
            self::Error => 'Falló la respuesta automática',
            self::Manual => 'La tomó el equipo',
        };
    }
}
