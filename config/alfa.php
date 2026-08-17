<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Datos del local
    |--------------------------------------------------------------------------
    |
    | Todo lo que cambia sin tocar el diseño del sitio público vive acá.
    | Portado de `lib/site.ts` del proyecto Next `alfa.automotores`.
    |
    */

    'nombre' => 'Alfa Automotores',
    'ciudad' => 'Rivera',
    'pais' => 'Uruguay',
    'direccion' => 'Ituzaingó 779',
    'codigo_postal' => '40000',

    'horarios' => [
        'semana' => '08:30–12:00 · 14:00–18:00',
        'sabado' => '08:30–12:00',
        /* Forma corta, para la línea de contacto de la ficha. */
        'corto' => 'Lun a Vie 08:30–12:00 / 14:00–18:00 · Sáb 08:30–12:00',
    ],

    'instagram' => 'https://www.instagram.com/alfaautomoviles2023/',

    /*
    | Número de WhatsApp en formato internacional, sin "+" ni separadores.
    */
    'whatsapp' => env('ALFA_WHATSAPP', '59899000000'),

    'telefono' => env('ALFA_TELEFONO', '+59846222222'),

];
