<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Las agendas se crean por año y por rubro. La ventana de reserva llega a 30
 * días, así que en diciembre ya hay que tener cargado el año siguiente: sin
 * esto, el 1 de enero la web deja de ofrecer turnos y no da ningún error.
 */
Schedule::command('taller:agenda')->yearlyOn(12, 1, '03:00');
Schedule::command('lavadero:agenda')->yearlyOn(12, 1, '03:05');
