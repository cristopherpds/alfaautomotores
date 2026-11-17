<?php

namespace App\Enums;

/**
 * El estado de una plantilla en Meta. Sólo las aprobadas se pueden mandar.
 */
enum EstadoPlantilla: string
{
    case Aprobada = 'aprobada';
    case Pendiente = 'pendiente';
    case Rechazada = 'rechazada';
    case Pausada = 'pausada';
    case Deshabilitada = 'deshabilitada';

    /**
     * The case for a Meta template status (`APPROVED`, `REJECTED`…).
     */
    public static function desdeMeta(string $estado): self
    {
        return match (strtoupper($estado)) {
            'APPROVED' => self::Aprobada,
            'REJECTED' => self::Rechazada,
            'PAUSED' => self::Pausada,
            'DISABLED', 'DELETED' => self::Deshabilitada,
            default => self::Pendiente,
        };
    }

    /**
     * Get the human readable label for the state.
     */
    public function label(): string
    {
        return match ($this) {
            self::Aprobada => 'Aprobada',
            self::Pendiente => 'En revisión',
            self::Rechazada => 'Rechazada',
            self::Pausada => 'Pausada',
            self::Deshabilitada => 'Deshabilitada',
        };
    }
}
