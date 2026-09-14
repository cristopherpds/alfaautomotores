<?php

use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Poner el reloj en un lunes fijo y abrir el taller con su agenda.
 *
 * Los turnos son la única parte del proyecto que depende de la hora: sin
 * congelar el reloj, un test que reserva «el próximo lunes a las 10» falla el
 * día que la suite corre un domingo a las 23:59. La agenda no es una fila que
 * se pueda crear con una factory: son los horarios que `taller:agenda` le
 * cuelga a cada puesto.
 *
 * Devuelve el lunes siguiente al congelado, que es el día que usan los tests.
 */
function abrirElTaller(int $puestos = 2): CarbonInterface
{
    test()->travelTo(Date::parse('2026-09-14 09:00'));

    config()->set('taller.puestos', $puestos);

    test()->artisan('taller:agenda');

    return Date::parse('2026-09-21')->startOfDay();
}
