<?php

namespace Database\Factories;

use App\Enums\EstadoPlantilla;
use App\Models\PlantillaWhatsapp;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlantillaWhatsapp>
 */
class PlantillaWhatsappFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'wa_template_id' => fake()->numerify('##########'),
            'nombre' => 'retomar_'.fake()->unique()->word(),
            'idioma' => 'es',
            'categoria' => 'UTILITY',
            'cuerpo' => 'Hola {{1}}, te escribimos de Alfa Automotores por tu consulta.',
            'variables' => 1,
            'estado' => EstadoPlantilla::Aprobada,
        ];
    }
}
