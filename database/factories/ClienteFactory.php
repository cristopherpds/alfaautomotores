<?php

namespace Database\Factories;

use App\Models\Cliente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cliente>
 */
class ClienteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => fake()->firstName(),
            'apellido' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'celular' => '09'.fake()->unique()->numerify('# ### ###'),
            'acepta_novedades' => false,
            'notas' => null,
        ];
    }

    /**
     * Indicate that the client agreed to receive news and reminders.
     */
    public function aceptaNovedades(): static
    {
        return $this->state(fn (array $attributes): array => [
            'acepta_novedades' => true,
        ]);
    }
}
