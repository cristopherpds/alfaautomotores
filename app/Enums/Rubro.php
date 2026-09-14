<?php

namespace App\Enums;

use App\Concerns\ProvidesSelectOptions;

/**
 * El negocio al que pertenece un servicio y un puesto.
 *
 * Es un eje distinto de `AreaServicio`: el rubro dice de qué negocio es el
 * trabajo (taller o lavadero) y el área, la especialidad dentro del taller. El
 * lavadero no se clasifica por área.
 *
 * Separa la capacidad además del catálogo: los huecos de cada rubro salen
 * únicamente de sus propios puestos, así que un lavado nunca ocupa un puesto
 * de mecánica. Ver `Puesto::huecosDelDia()`.
 *
 * El valor coincide a propósito con el nombre del archivo de configuración
 * (`config/taller.php`, `config/lavadero.php`): `config($rubro->value.'.buffer')`.
 */
enum Rubro: string
{
    use ProvidesSelectOptions;

    case Taller = 'taller';
    case Lavadero = 'lavadero';

    /**
     * Get the human readable label for the line of business.
     */
    public function label(): string
    {
        return match ($this) {
            self::Taller => 'Taller',
            self::Lavadero => 'Lavadero',
        };
    }

    /**
     * Leer un valor de la configuración del rubro.
     */
    public function config(string $clave): mixed
    {
        return config("{$this->value}.{$clave}");
    }
}
