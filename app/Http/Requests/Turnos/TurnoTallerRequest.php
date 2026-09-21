<?php

namespace App\Http\Requests\Turnos;

use App\Enums\Rubro;

class TurnoTallerRequest extends TurnoPublicoRequest
{
    protected function rubro(): Rubro
    {
        return Rubro::Taller;
    }
}
