<?php

namespace App\Concerns;

trait ProvidesSiteInfo
{
    /**
     * Datos del local que consume el sitio público.
     *
     * @return array<string, mixed>
     */
    protected function siteInfo(): array
    {
        return [
            'nombre' => config('alfa.nombre'),
            'ciudad' => config('alfa.ciudad'),
            'pais' => config('alfa.pais'),
            'direccion' => config('alfa.direccion'),
            'codigoPostal' => config('alfa.codigo_postal'),
            'horarios' => config('alfa.horarios'),
            'instagram' => config('alfa.instagram'),
            'whatsapp' => config('alfa.whatsapp'),
            'telefono' => config('alfa.telefono'),
        ];
    }
}
