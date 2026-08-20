<?php

namespace Database\Factories;

use App\Models\Vehiculo;
use App\Models\VehiculoImagen;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VehiculoImagen>
 */
class VehiculoImagenFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'vehiculo_id' => Vehiculo::factory(),
            'ruta' => 'vehiculos/'.fake()->unique()->numberBetween(1, 100_000).'.jpg',
            'orden' => 0,
        ];
    }

    /**
     * Indicate the position of the photo inside the gallery.
     */
    public function orden(int $orden): static
    {
        return $this->state(fn (array $attributes): array => [
            'orden' => $orden,
        ]);
    }
}
