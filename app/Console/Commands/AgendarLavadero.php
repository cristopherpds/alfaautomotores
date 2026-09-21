<?php

namespace App\Console\Commands;

use App\Enums\Rubro;

class AgendarLavadero extends AgendarPuestos
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lavadero:agenda {--anio=* : Años a agendar; por defecto el actual y el siguiente}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Crear los boxes del lavadero y darles el horario de atención';

    protected function rubro(): Rubro
    {
        return Rubro::Lavadero;
    }

    protected function prefijo(): string
    {
        return 'Box de lavado';
    }
}
