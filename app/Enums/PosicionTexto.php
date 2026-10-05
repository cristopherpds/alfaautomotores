<?php

namespace App\Enums;

use App\Concerns\ProvidesSelectOptions;

/**
 * Dónde va el bloque de texto y botones de un slide del hero: una de las nueve
 * casillas de una grilla de 3×3.
 *
 * El valor es `{vertical}-{horizontal}` y la portada lo parte en dos atributos
 * (`data-vertical`, `data-horizontal`) que lee `alfa.css`. `AbajoIzquierda` es
 * la posición que tuvo el hero desde siempre.
 */
enum PosicionTexto: string
{
    use ProvidesSelectOptions;

    case ArribaIzquierda = 'arriba-izquierda';
    case ArribaCentro = 'arriba-centro';
    case ArribaDerecha = 'arriba-derecha';
    case CentroIzquierda = 'centro-izquierda';
    case CentroCentro = 'centro-centro';
    case CentroDerecha = 'centro-derecha';
    case AbajoIzquierda = 'abajo-izquierda';
    case AbajoCentro = 'abajo-centro';
    case AbajoDerecha = 'abajo-derecha';

    /**
     * La fila de la grilla: `arriba`, `centro` o `abajo`.
     */
    public function vertical(): string
    {
        return explode('-', $this->value)[0];
    }

    /**
     * La columna de la grilla: `izquierda`, `centro` o `derecha`.
     */
    public function horizontal(): string
    {
        return explode('-', $this->value)[1];
    }

    /**
     * Get the human readable label for the position.
     */
    public function label(): string
    {
        return match ($this) {
            self::ArribaIzquierda => 'Arriba a la izquierda',
            self::ArribaCentro => 'Arriba al centro',
            self::ArribaDerecha => 'Arriba a la derecha',
            self::CentroIzquierda => 'Al medio a la izquierda',
            self::CentroCentro => 'Al centro',
            self::CentroDerecha => 'Al medio a la derecha',
            self::AbajoIzquierda => 'Abajo a la izquierda',
            self::AbajoCentro => 'Abajo al centro',
            self::AbajoDerecha => 'Abajo a la derecha',
        };
    }
}
