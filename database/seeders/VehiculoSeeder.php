<?php

namespace Database\Seeders;

use App\Models\Vehiculo;
use Illuminate\Database\Seeder;

class VehiculoSeeder extends Seeder
{
    /**
     * Cargar el stock de ejemplo desde `database/data/vehiculos.json`.
     *
     * El archivo era la fuente de datos antes de que existiera la tabla y hoy
     * queda como semilla. Se respeta su orden: la primera fila es la más
     * reciente, así el catálogo se ve igual que antes de migrar.
     */
    public function run(): void
    {
        $filas = json_decode(
            (string) file_get_contents(database_path('data/vehiculos.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $momento = now();

        foreach (is_array($filas) ? $filas : [] as $indice => $fila) {
            if (! is_array($fila)) {
                continue;
            }

            $vehiculo = Vehiculo::firstOrNew(['slug' => (string) $fila['slug']]);

            $vehiculo->fill([
                'marca' => $fila['marca'],
                'modelo' => $fila['modelo'],
                'version' => $fila['version'] ?? null,
                'anio' => $fila['anio'],
                'km' => $fila['km'],
                'precio' => $fila['precio'],
                'moneda' => $fila['moneda'],
                'comb' => $fila['comb'],
                'trans' => $fila['trans'],
                'tipo' => $fila['tipo'],
                'estado' => $fila['estado'],
                'desc' => $fila['desc'],
            ]);

            $vehiculo->created_at = $momento->copy()->subMinutes($indice);
            $vehiculo->updated_at = $vehiculo->created_at;

            $vehiculo->save();
        }
    }
}
