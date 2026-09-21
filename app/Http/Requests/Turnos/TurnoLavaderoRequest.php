<?php

namespace App\Http\Requests\Turnos;

use App\Enums\Rubro;

class TurnoLavaderoRequest extends TurnoPublicoRequest
{
    protected function rubro(): Rubro
    {
        return Rubro::Lavadero;
    }
}
