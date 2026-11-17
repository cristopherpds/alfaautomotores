<?php

namespace Database\Factories;

use App\Enums\DireccionMensaje;
use App\Enums\EstadoMensaje;
use App\Enums\TipoMensaje;
use App\Models\Conversacion;
use App\Models\MensajeWhatsapp;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MensajeWhatsapp>
 */
class MensajeWhatsappFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversacion_id' => Conversacion::factory(),
            'direccion' => DireccionMensaje::Entrante,
            'tipo' => TipoMensaje::Texto,
            'cuerpo' => fake()->sentence(),
            'wa_message_id' => 'wamid.'.fake()->unique()->bothify('????????????????'),
            'estado' => EstadoMensaje::Recibido,
            'wa_timestamp' => now(),
        ];
    }

    /**
     * Indicate that the message was sent by the dealership.
     */
    public function saliente(EstadoMensaje $estado = EstadoMensaje::Enviado): static
    {
        return $this->state(fn (array $attributes): array => [
            'direccion' => DireccionMensaje::Saliente,
            'estado' => $estado,
        ]);
    }
}
