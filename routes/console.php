<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * La agenda del taller se crea por año. La ventana de reserva llega a 30 días,
 * así que en diciembre ya hay que tener cargado el año siguiente: sin esto, el
 * 1 de enero la web deja de ofrecer turnos y no da ningún error.
 */
Schedule::command('taller:agenda')->yearlyOn(12, 1, '03:00');
