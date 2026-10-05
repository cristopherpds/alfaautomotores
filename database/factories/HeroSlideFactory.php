<?php

namespace Database\Factories;

use App\Enums\PosicionTexto;
use App\Enums\TipoFondo;
use App\Models\HeroSlide;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HeroSlide>
 */
class HeroSlideFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tipo_fondo' => TipoFondo::Imagen,
            'fondo' => HeroSlide::CARPETA.'/'.fake()->uuid().'.jpg',
            'eyebrow' => 'Rivera · Uruguay',
            'titulo' => fake()->sentence(4),
            'bajada' => fake()->sentence(),
            'posicion' => PosicionTexto::AbajoIzquierda,
            'boton1_texto' => 'Ver catálogo',
            'boton1_url' => '/catalogo',
            'activo' => true,
            'orden' => 0,
        ];
    }

    /**
     * Indicate a slide with a video background.
     */
    public function video(): static
    {
        return $this->state(fn (array $attributes): array => [
            'tipo_fondo' => TipoFondo::Video,
            'fondo' => HeroSlide::CARPETA.'/'.fake()->uuid().'.mp4',
        ]);
    }

    /**
     * Indicate where the text block sits over the background.
     */
    public function posicion(PosicionTexto $posicion): static
    {
        return $this->state(fn (array $attributes): array => [
            'posicion' => $posicion,
        ]);
    }

    /**
     * Indicate a slide switched off from the panel.
     */
    public function inactivo(): static
    {
        return $this->state(fn (array $attributes): array => [
            'activo' => false,
        ]);
    }

    /**
     * Indicate the dates the slide is shown between, both inclusive.
     */
    public function vigencia(?string $desde, ?string $hasta): static
    {
        return $this->state(fn (array $attributes): array => [
            'desde' => $desde,
            'hasta' => $hasta,
        ]);
    }
}
