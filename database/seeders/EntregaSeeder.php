<?php

namespace Database\Seeders;

use App\Models\Entrega;
use Illuminate\Database\Seeder;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;

class EntregaSeeder extends Seeder
{
    /**
     * Importar las fotos históricas desde `database/data/entregas/`.
     *
     * La carpeta era la fuente de datos antes de que existiera la tabla y hoy
     * queda como semilla, igual que `database/data/vehiculos.json`. El nombre
     * del archivo lleva la fecha de la entrega.
     *
     * Es idempotente: se puede correr de nuevo sin duplicar nada.
     */
    public function run(): void
    {
        /* `<orden>_<aaaa-mm-dd>.<ext>`, la convención vieja de la carpeta. */
        $convencion = '/^(\d+)_(\d{4}-\d{2}-\d{2})\.(?:jpe?g|png|webp|avif)$/i';

        $archivos = glob(database_path('data/entregas/*')) ?: [];

        /* Ascendente a propósito: importadas en este orden, los ids desempatan
           las entregas del mismo día igual que lo hacía el número de adelante
           en el nombre, y la tira se ve como antes de la tabla. */
        sort($archivos);

        foreach ($archivos as $origen) {
            $nombre = basename($origen);

            /* Se ignora en silencio lo que no siga la convención: el README, sin
               ir más lejos. */
            if (! preg_match($convencion, $nombre, $partes)) {
                continue;
            }

            $ruta = Entrega::CARPETA.'/'.$nombre;

            /* Se conserva el nombre original en vez del hash de `store()`: es la
               clave de idempotencia y deja el origen a la vista. Las fotos que
               llegan por el panel sí van con nombre hasheado. */
            if (! Storage::disk('public')->exists($ruta)) {
                Storage::disk('public')->putFileAs(Entrega::CARPETA, new File($origen), $nombre);
            }

            Entrega::firstOrCreate(['ruta' => $ruta], ['fecha' => $partes[2]]);
        }
    }
}
