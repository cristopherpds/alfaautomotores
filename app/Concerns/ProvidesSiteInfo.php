<?php

namespace App\Concerns;

use App\Models\Ajuste;

trait ProvidesSiteInfo
{
    /**
     * Datos del local que consume el sitio público.
     *
     * @return array<string, mixed>
     */
    protected function siteInfo(): array
    {
        $boton = Ajuste::botonWhatsapp();
        $disponible = Ajuste::whatsappDisponible(now());

        return [
            'nombre' => config('alfa.nombre'),
            'ciudad' => config('alfa.ciudad'),
            'pais' => config('alfa.pais'),
            'direccion' => config('alfa.direccion'),
            'codigoPostal' => config('alfa.codigo_postal'),
            'horarios' => config('alfa.horarios'),
            'instagram' => config('alfa.instagram'),
            // Los links sueltos de WhatsApp (cabecera, pie, fichas) van al
            // primer contacto disponible ahora: siguen el mismo horario que
            // el botón flotante.
            'whatsapp' => $disponible['contactos'][0]['numero'],
            'telefono' => config('alfa.telefono'),
            'botonWhatsapp' => [
                'activo' => $boton['activo'],
                'titulo' => $boton['titulo'],
                'subtitulo' => $boton['subtitulo'],
                'fueraDeHorario' => $disponible['fueraDeHorario'],
                'contactos' => $disponible['contactos'],
            ],
        ];
    }
}
