<?php

namespace App\Concerns;

/**
 * Formatea los casos de un enum para los selects del panel.
 *
 * Espera que el enum exponga `label(): string`. Cuando además hace falta una
 * línea de ayuda por opción, el enum sobrescribe `options()` — ver
 * `App\Enums\EstadoVehiculo`.
 */
trait ProvidesSelectOptions
{
    /**
     * Get the cases formatted for the frontend select inputs.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $caso): array => [
            'value' => $caso->value,
            'label' => $caso->label(),
        ], self::cases());
    }
}
