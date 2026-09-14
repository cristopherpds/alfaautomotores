<?php

namespace App\Console\Commands;

use App\Enums\Rubro;
use App\Models\Puesto;
use Illuminate\Console\Command;

/**
 * Base de los comandos que crean los puestos de un rubro y los agendan.
 *
 * Cada negocio tiene el suyo (`taller:agenda`, `lavadero:agenda`) porque la
 * capacidad y el horario salen de la config del rubro y los puestos no se
 * comparten. La lógica es la misma, así que vive acá una sola vez.
 */
abstract class AgendarPuestos extends Command
{
    /**
     * El negocio cuyos puestos crea y agenda este comando.
     */
    abstract protected function rubro(): Rubro;

    /**
     * Cómo se llaman los puestos: `{prefijo} 1`, `{prefijo} 2`…
     *
     * Es la clave con la que se buscan, así que **cambiarlo crea puestos
     * nuevos en vez de renombrar los que hay**.
     */
    abstract protected function prefijo(): string;

    /**
     * Asegurar que el rubro tenga sus puestos y su horario.
     *
     * Es idempotente: correrlo de nuevo no duplica nada. Hay que correrlo al
     * cambiar la cantidad de puestos y una vez por año — de eso se encarga la
     * tarea de `routes/console.php`, porque la ventana de reserva de 30 días
     * cruza el fin de año y sin horario del año siguiente no se ofrece ningún
     * turno.
     */
    public function handle(): int
    {
        $rubro = $this->rubro();
        $cuantos = (int) $rubro->config('puestos');

        if ($cuantos < 1) {
            $this->error("`{$rubro->value}.puestos` tiene que ser al menos 1.");

            return self::FAILURE;
        }

        /** @var list<int> $anios */
        $anios = array_map(intval(...), (array) $this->option('anio'));

        if ($anios === []) {
            $anios = [(int) now()->year, (int) now()->addYear()->year];
        }

        for ($numero = 1; $numero <= $cuantos; $numero++) {
            $puesto = Puesto::firstOrCreate(
                ['nombre' => $this->prefijo()." {$numero}", 'rubro' => $rubro],
                ['activo' => true],
            );

            foreach ($anios as $anio) {
                $puesto->agendar($anio);
            }
        }

        $this->info(sprintf(
            '%d puesto(s) de %s con horario para %s.',
            $cuantos,
            $rubro->label(),
            implode(' y ', $anios),
        ));

        return self::SUCCESS;
    }
}
