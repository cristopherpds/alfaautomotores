<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Agenda del taller
    |--------------------------------------------------------------------------
    |
    | Lo que gobierna la reserva de turnos sin tocar código ni diseño. Los datos
    | del local (dirección, WhatsApp, horarios como texto) siguen en
    | `config/alfa.php`.
    |
    */

    /*
    | Cuántos autos puede atender el taller a la vez. Cada puesto es un recurso
    | con su propia agenda: un horario sigue libre mientras quede alguno.
    |
    | Al cambiarlo hay que correr `php artisan taller:agenda`, que crea los
    | puestos que falten con su horario.
    */
    'puestos' => (int) env('TALLER_PUESTOS', 2),

    /*
    | Minutos de descanso entre un turno y el siguiente.
    |
    | Va en cero a propósito: con buffer los horarios ofrecidos caen en horas
    | raras (08:30, 10:15, 12:00…) y los trabajos largos dejan de entrar en la
    | ventana de la tarde, que mide exactamente cuatro horas.
    */
    'buffer' => (int) env('TALLER_BUFFER', 0),

    /*
    | Ventana de reserva del sitio público, en días. Desde mañana y hasta un
    | mes. El panel no tiene estos límites: el taller puede cargar un turno
    | para hoy mismo.
    */
    'anticipacion_dias' => 1,
    'horizonte_dias' => 30,

    /*
    | Horario de atención, en formato de máquina. Es el mismo del salón que
    | `config/alfa.php` imprime como texto en el pie: **si cambia uno hay que
    | cambiar el otro**. Cada par es un período; el hueco del mediodía
    | simplemente no existe, no hace falta bloquearlo.
    |
    | El domingo no se atiende: no figura.
    */
    'horarios' => [
        'semana' => [['08:30', '12:00'], ['14:00', '18:00']],
        'sabado' => [['08:30', '12:00']],
    ],

];
