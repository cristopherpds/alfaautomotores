<?php

namespace App\Console\Commands;

use App\Enums\Rubro;

class AgendarTaller extends AgendarPuestos
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

    protected function rubro(): Rubro
    {
        return Rubro::Taller;
    }

    protected function prefijo(): string
    {
        return 'Puesto';
    }
}
