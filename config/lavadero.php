<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Agenda del lavadero
    |--------------------------------------------------------------------------
    |
    | Misma forma que `config/taller.php`, y a propósito en un archivo aparte:
    | el nombre del archivo es el valor de `App\Enums\Rubro`, así que el rubro
    | de un servicio direcciona su propia configuración. Los dos negocios pueden
    | cambiar de horario o de capacidad sin tocarse.
    |
    */

    /*
    | Cuántos autos puede lavar el lavadero a la vez. Cada box es un recurso con
    | su propia agenda, independiente de los puestos del taller: un lavado nunca
    | ocupa un puesto de mecánica.
    |
    | Al cambiarlo hay que correr `php artisan lavadero:agenda`.
    */
    'puestos' => (int) env('LAVADERO_PUESTOS', 1),

    /*
    | Minutos de descanso entre un lavado y el siguiente. Va en cero por la
    | misma razón que en el taller: con buffer los horarios ofrecidos caen en
    | horas raras.
    */
    'buffer' => (int) env('LAVADERO_BUFFER', 0),

    /*
    | Ventana de reserva del sitio público, en días. Desde mañana y hasta un
    | mes. El panel no tiene estos límites.
    */
    'anticipacion_dias' => 1,
    'horizonte_dias' => 30,

    /*
    | Horario de atención, en formato de máquina. Hoy es el mismo del taller y
    | del salón — **si cambia uno hay que cambiar el otro**. El domingo no se
    | atiende: no figura.
    */
    'horarios' => [
        'semana' => [['08:30', '12:00'], ['14:00', '18:00']],
        'sabado' => [['08:30', '12:00']],
    ],

];
