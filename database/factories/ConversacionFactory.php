<?php

namespace Database\Factories;

use App\Enums\MotivoDerivacion;
use App\Models\Conversacion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversacion>
 */
class ConversacionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'wa_id' => '5989'.fake()->unique()->numerify('#######'),
            'nombre_perfil' => fake()->firstName(),
            'ia_activa' => true,
            'ultimo_entrante_at' => now()->subMinutes(5),
            'ultimo_mensaje_at' => now()->subMinutes(5),
        ];
    }

    /**
     * Indicate that the 24-hour window is already closed.
     */
    public function ventanaCerrada(): static
    {
        return $this->state(fn (array $attributes): array => [
            'ultimo_entrante_at' => now()->subHours(Conversacion::HORAS_DE_VENTANA + 1),
            'ultimo_mensaje_at' => now()->subHours(Conversacion::HORAS_DE_VENTANA + 1),
        ]);
    }

    /**
     * Indicate that the conversation was handed to a person.
     */
    public function derivada(MotivoDerivacion $motivo = MotivoDerivacion::Cliente): static
    {
        return $this->state(fn (array $attributes): array => [
            'derivada_at' => now(),
            'motivo_derivacion' => $motivo,
        ]);
    }
}
