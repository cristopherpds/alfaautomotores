<?php

namespace Database\Factories;

use App\Enums\Combustible;
use App\Enums\EstadoVehiculo;
use App\Enums\Moneda;
use App\Enums\TipoVehiculo;
use App\Enums\Transmision;
use App\Models\Vehiculo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehiculo>
 */
class VehiculoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Por defecto un vehículo publicado y sin destacar: es lo que necesita la
     * mayoría de los tests.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $marca = fake()->randomElement(['Fiat', 'Chevrolet', 'Chery', 'Volkswagen', 'Ford', 'Renault', 'Peugeot']);
        $modelo = fake()->randomElement(['Strada', 'Onix', 'Tiggo 2', 'Gol', 'EcoSport', 'Kwid', '208']);

        return [
            'slug' => fake()->unique()->slug(3),
            'marca' => $marca,
            'modelo' => $modelo,
            'version' => fake()->optional()->randomElement(['Freedom', 'Pro', 'Plus', 'Trend']),
            'anio' => fake()->numberBetween(2010, 2026),
            'km' => fake()->numberBetween(0, 200_000),
            'precio' => fake()->numberBetween(6_000, 45_000),
            'moneda' => Moneda::Usd,
            'comb' => fake()->randomElement(Combustible::cases()),
            'trans' => fake()->randomElement(Transmision::cases()),
            'tipo' => fake()->randomElement(TipoVehiculo::cases()),
            'estado' => EstadoVehiculo::Publicado,
            'destacado' => false,
            'desc' => fake()->sentence(12),
        ];
    }

    /**
     * Indicate that the vehicle is in the given state.
     */
    public function estado(EstadoVehiculo $estado): static
    {
        return $this->state(fn (array $attributes): array => [
            'estado' => $estado,
        ]);
    }

    public function borrador(): static
    {
        return $this->estado(EstadoVehiculo::Borrador);
    }

    public function publicado(): static
    {
        return $this->estado(EstadoVehiculo::Publicado);
    }

    public function reservado(): static
    {
        return $this->estado(EstadoVehiculo::Reservado);
    }

    public function vendido(): static
    {
        return $this->estado(EstadoVehiculo::Vendido);
    }

    /**
     * Indicate that the vehicle is pinned to the landing page.
     */
    public function destacado(): static
    {
        return $this->state(fn (array $attributes): array => [
            'destacado' => true,
        ]);
    }

    /**
     * Indicate the body type, which is what `similares()` groups by.
     */
    public function tipo(TipoVehiculo $tipo): static
    {
        return $this->state(fn (array $attributes): array => [
            'tipo' => $tipo,
        ]);
    }
}
