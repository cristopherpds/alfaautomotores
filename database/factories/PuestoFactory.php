<?php

namespace Database\Factories;

use App\Models\Puesto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Puesto>
 */
class PuestoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => 'Puesto '.fake()->unique()->numberBetween(1, 1000),
            'activo' => true,
        ];
    }

    /**
     * Indicate a bay that is not taken into account when computing slots.
     */
    public function inactivo(): static
    {
        return $this->state(fn (array $attributes): array => [
            'activo' => false,
        ]);
    }

    /**
     * Give the bay the shop's opening hours, so it has slots to offer.
     *
     * Sin esto un puesto no tiene ningún hueco: la disponibilidad no es una
     * columna, son los horarios que le cuelgan.
     */
    public function conAgenda(?int $anio = null): static
    {
        return $this->afterCreating(function (Puesto $puesto) use ($anio): void {
            $puesto->agendar($anio ?? (int) now()->year);
        });
    }
}
