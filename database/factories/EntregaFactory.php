<?php

namespace Database\Factories;

use App\Models\Entrega;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Entrega>
 */
class EntregaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ruta' => 'entregas/'.fake()->unique()->numberBetween(1, 100_000).'.jpg',
            'fecha' => fake()->dateTimeBetween('-1 year')->format('Y-m-d'),
        ];
    }

    /**
     * Indicate the day the vehicle was handed over.
     */
    public function fecha(string $fecha): static
    {
        return $this->state(fn (array $attributes): array => [
            'fecha' => $fecha,
        ]);
    }
}
