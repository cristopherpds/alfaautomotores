<?php

namespace Database\Factories;

use App\Models\Conocimiento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conocimiento>
 */
class ConocimientoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tipo' => Conocimiento::PREGUNTA,
            'pregunta' => '¿Reciben usados como parte de pago?',
            'respuesta' => 'Sí, tasamos tu usado en el día y lo tomamos como parte de pago.',
            'orden' => 0,
        ];
    }

    /**
     * A free-form block instead of a question.
     */
    public function bloque(string $contenido = 'Financiamos hasta en 60 cuotas en pesos.'): static
    {
        return $this->state(fn (array $attributes): array => [
            'tipo' => Conocimiento::BLOQUE,
            'pregunta' => null,
            'respuesta' => null,
            'contenido' => $contenido,
        ]);
    }
}
