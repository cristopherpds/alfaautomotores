<?php

namespace Database\Factories;

use App\Enums\AreaServicio;
use App\Models\Servicio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Servicio>
 */
class ServicioFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nombre = fake()->unique()->word().' '.fake()->word();

        return [
            'slug' => str($nombre)->slug()->value(),
            'nombre' => ucfirst($nombre),
            'area' => fake()->randomElement(AreaServicio::cases()),
            /* Múltiplo de 30: es como se reparten las ventanas del taller. */
            'duracion' => fake()->randomElement([30, 45, 60, 90]),
            'descripcion' => fake()->sentence(),
            'activo' => true,
            'agendable' => true,
            'orden' => 0,
        ];
    }

    /**
     * Indicate the area the job belongs to.
     */
    public function area(AreaServicio $area): static
    {
        return $this->state(fn (array $attributes): array => [
            'area' => $area,
        ]);
    }

    /**
     * Indicate how long the job takes, in minutes.
     */
    public function duracion(int $minutos): static
    {
        return $this->state(fn (array $attributes): array => [
            'duracion' => $minutos,
        ]);
    }

    /**
     * Indicate a job that never reaches the public site.
     */
    public function inactivo(): static
    {
        return $this->state(fn (array $attributes): array => [
            'activo' => false,
        ]);
    }

    /**
     * Indicate a job that is shown but quoted at the shop instead of booked.
     */
    public function noAgendable(): static
    {
        return $this->state(fn (array $attributes): array => [
            'agendable' => false,
        ]);
    }
}
