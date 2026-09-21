<?php

namespace Database\Factories;

use App\Enums\EstadoTurno;
use App\Enums\OrigenTurno;
use App\Models\Puesto;
use App\Models\Servicio;
use App\Models\Turno;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Turno>
 */
class TurnoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * Crea el turno sin cita en Zap: sirve para probar el panel, no la
     * disponibilidad. Para reservar de verdad está `Turno::reservar()`.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $inicio = now()->addDay()->setTime(9, 0);

        return [
            'servicio_id' => Servicio::factory(),
            'puesto_id' => Puesto::factory(),
            'schedule_id' => null,
            'estado' => EstadoTurno::Pendiente,
            'origen' => OrigenTurno::Web,
            'inicia_at' => $inicio,
            'termina_at' => $inicio->copy()->addHour(),
            'nombre' => fake()->firstName(),
            'apellido' => fake()->lastName(),
            'email' => fake()->safeEmail(),
            'celular' => '099 000 000',
            'vehiculo_marca' => 'Fiat',
            'vehiculo_modelo' => 'Cronos',
            'vehiculo_anio' => 2020,
            'comentario' => null,
        ];
    }

    /**
     * Indicate when the appointment starts. The end follows the hour.
     */
    public function inicia(CarbonInterface $inicio): static
    {
        return $this->state(fn (array $attributes): array => [
            'inicia_at' => $inicio,
            'termina_at' => $inicio->copy()->addHour(),
        ]);
    }

    /**
     * Indicate the state of the appointment.
     */
    public function estado(EstadoTurno $estado): static
    {
        return $this->state(fn (array $attributes): array => [
            'estado' => $estado,
        ]);
    }

    /**
     * Indicate an appointment loaded by the shop instead of booked online.
     */
    public function delPanel(): static
    {
        return $this->state(fn (array $attributes): array => [
            'origen' => OrigenTurno::Panel,
            'estado' => EstadoTurno::Confirmado,
        ]);
    }
}
