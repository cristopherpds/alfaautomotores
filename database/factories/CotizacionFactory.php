<?php

namespace Database\Factories;

use App\Enums\EstadoCotizacion;
use App\Models\Cotizacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cotizacion>
 */
class CotizacionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'marca' => fake()->randomElement(['Toyota', 'Volkswagen', 'Chevrolet', 'Fiat', 'Renault']),
            'modelo' => fake()->randomElement(['Gol', 'Corolla', 'Onix', 'Cronos', 'Sandero']),
            'anio' => fake()->numberBetween(2008, (int) now()->year),
            'kilometros' => fake()->numberBetween(5_000, 250_000),
            'detalles' => null,
            'nombre' => fake()->firstName(),
            'apellido' => fake()->lastName(),
            'celular' => '09'.fake()->unique()->numerify('# ### ###'),
            'estado' => EstadoCotizacion::Nueva,
        ];
    }

    /**
     * Indicate that someone already got back to the seller.
     */
    public function contactada(): static
    {
        return $this->state(fn (array $attributes): array => [
            'estado' => EstadoCotizacion::Contactada,
        ]);
    }
}
