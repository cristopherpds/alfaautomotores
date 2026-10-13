<?php

namespace App\Enums;

/**
 * Por dónde va una solicitud de cotización del sitio público.
 */
enum EstadoCotizacion: string
{
    case Nueva = 'nueva';
    case Contactada = 'contactada';
    case Descartada = 'descartada';

    /**
     * Get the human readable label for the state.
     */
    public function label(): string
    {
        return match ($this) {
            self::Nueva => 'Nueva',
            self::Contactada => 'Contactada',
            self::Descartada => 'Descartada',
        };
    }

    /**
     * Get a short description of what the state means for the dealership.
     */
    public function description(): string
    {
        return match ($this) {
            self::Nueva => 'Llegó desde la web y nadie la atendió todavía.',
            self::Contactada => 'Ya se le escribió o llamó con la cotización.',
            self::Descartada => 'No avanza: el auto no interesa o no respondió.',
        };
    }

    /**
     * Get the states formatted for the frontend select inputs.
     *
     * @return array<int, array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $estado): array => [
            'value' => $estado->value,
            'label' => $estado->label(),
            'description' => $estado->description(),
        ], self::cases());
    }
}
