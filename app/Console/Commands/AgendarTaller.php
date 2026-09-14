<?php

namespace App\Console\Commands;

use App\Models\Puesto;
use Illuminate\Console\Command;

class AgendarTaller extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'taller:agenda {--anio=* : Años a agendar; por defecto el actual y el siguiente}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crear los puestos del taller y darles el horario de atención';

    /**
     * Asegurar que el taller tenga sus puestos y su horario.
     *
     * Es idempotente: correrlo de nuevo no duplica nada. Hay que correrlo al
     * cambiar `taller.puestos` y una vez por año — de eso se encarga la tarea
     * de `routes/console.php`, porque la ventana de reserva de 30 días cruza el
     * fin de año y sin horario del año siguiente no se ofrece ningún turno.
     */
    public function handle(): int
    {
        $cuantos = (int) config('taller.puestos');

        if ($cuantos < 1) {
            $this->error('`taller.puestos` tiene que ser al menos 1.');

            return self::FAILURE;
        }

        /** @var list<int> $anios */
        $anios = array_map(intval(...), (array) $this->option('anio'));

        if ($anios === []) {
            $anios = [(int) now()->year, (int) now()->addYear()->year];
        }

        for ($numero = 1; $numero <= $cuantos; $numero++) {
            $puesto = Puesto::firstOrCreate(
                ['nombre' => "Puesto {$numero}"],
                ['activo' => true],
            );

            foreach ($anios as $anio) {
                $puesto->agendar($anio);
            }
        }

        $this->info(sprintf(
            '%d puesto(s) con horario para %s.',
            $cuantos,
            implode(' y ', $anios),
        ));

        return self::SUCCESS;
    }
}
