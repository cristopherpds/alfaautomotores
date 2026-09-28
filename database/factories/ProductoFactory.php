<?php

namespace Database\Factories;

use App\Enums\EstadoProducto;
use App\Enums\FamiliaProducto;
use App\Enums\Moneda;
use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Producto>
 */
class ProductoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Por defecto una moto eléctrica publicada y con precio: es lo que
     * necesita la mayoría de los tests de la sección de movilidad.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nombre = fake()->randomElement(['MAX 350', 'Chopper', 'Strada', 'Monster', 'Retro'])
            .' '.fake()->numberBetween(250, 1200).' W';

        return [
            'slug' => fake()->unique()->slug(3),
            'nombre' => $nombre,
            'familia' => FamiliaProducto::Moto,
            'codigo' => fake()->optional()->bothify('??-###'),
            'precio' => fake()->numberBetween(8_000, 70_000),
            'moneda' => Moneda::Uyu,
            'estado' => EstadoProducto::Publicado,
            'resumen' => '350 W · 30 km/h · 25–30 km',
            'desc' => fake()->sentence(12),
            'specs' => [['Potencia', '350 W'], ['Autonomía', '25–30 km']],
            'colores' => ['Negra', 'Roja'],
        ];
    }

    /**
     * Indicate that the product is in the given state.
     */
    public function estado(EstadoProducto $estado): static
    {
        return $this->state(fn (array $attributes): array => [
            'estado' => $estado,
        ]);
    }

    public function borrador(): static
    {
        return $this->estado(EstadoProducto::Borrador);
    }

    public function sinStock(): static
    {
        return $this->estado(EstadoProducto::SinStock);
    }

    public function porEncargue(): static
    {
        return $this->estado(EstadoProducto::PorEncargue);
    }

    /**
     * Indicate the family, which is what the two grids split by.
     */
    public function familia(FamiliaProducto $familia): static
    {
        return $this->state(fn (array $attributes): array => [
            'familia' => $familia,
        ]);
    }

    /**
     * Una bicicleta: va en la otra sección y siempre sin precio.
     */
    public function bicicleta(): static
    {
        return $this->state(fn (array $attributes): array => [
            'familia' => FamiliaProducto::Bicicleta,
            'precio' => null,
            'resumen' => 'Rodado 26 · acero · 21 velocidades',
            'specs' => [['Rodado', '26'], ['Velocidades', '21']],
        ]);
    }
}
