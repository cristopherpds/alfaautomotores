<?php

namespace Database\Factories;

use App\Enums\AccionAuditoria;
use App\Models\Auditoria;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Auditoria>
 */
class AuditoriaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Por defecto, un vendedor que modificó el precio de un vehículo.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $autor = User::factory();

        return [
            'user_id' => $autor,
            'usuario' => fn (array $atributos): string => User::find($atributos['user_id'])?->name ?? fake()->name(),
            'accion' => AccionAuditoria::Modificado,
            'tipo' => 'vehiculo',
            'registro_id' => fake()->numberBetween(1, 500),
            'etiqueta' => 'Fiat Strada Freedom',
            'cambios' => ['precio' => [23900, 22900]],
            'ip' => fake()->ipv4(),
        ];
    }

    /**
     * Indicate the action that was performed.
     */
    public function accion(AccionAuditoria $accion): static
    {
        return $this->state(fn (array $attributes): array => [
            'accion' => $accion,
        ]);
    }
}
