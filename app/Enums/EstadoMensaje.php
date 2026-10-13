<?php

namespace App\Enums;

/**
 * Por dónde va un mensaje de WhatsApp.
 *
 * Los salientes avanzan pendiente → enviado → entregado → leído y nunca
 * retroceden: Meta manda los estados desordenados y un `delivered` tardío no
 * puede pisar un `read`. `Fallido` es aparte y se registra siempre.
 */
enum EstadoMensaje: string
{
    case Recibido = 'recibido';
    case Pendiente = 'pendiente';
    case Enviado = 'enviado';
    case Entregado = 'entregado';
    case Leido = 'leido';
    case Fallido = 'fallido';

    /**
     * The case for a Meta status, or null when it is not one we track.
     */
    public static function desdeMeta(string $estado): ?self
    {
        return match ($estado) {
            'sent' => self::Enviado,
            'delivered' => self::Entregado,
            'read' => self::Leido,
            'failed' => self::Fallido,
            default => null,
        };
    }

    /**
     * Whether moving from this state to the given one is an upgrade.
     */
    public function puedePasarA(self $nuevo): bool
    {
        if ($nuevo === self::Fallido) {
            return $this !== self::Leido;
        }

        return $nuevo->rango() > $this->rango();
    }

    private function rango(): int
    {
        return match ($this) {
            self::Fallido, self::Recibido => -1,
            self::Pendiente => 0,
            self::Enviado => 1,
            self::Entregado => 2,
            self::Leido => 3,
        };
    }
}
