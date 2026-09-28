<?php

namespace App\Enums;

use App\Concerns\ProvidesSelectOptions;

/**
 * Qué le pasó a un registro, para la auditoría del panel.
 */
enum AccionAuditoria: string
{
    use ProvidesSelectOptions;

    case Creado = 'creado';
    case Modificado = 'modificado';
    case Eliminado = 'eliminado';

    /**
     * Get the human readable label for the action.
     */
    public function label(): string
    {
        return match ($this) {
            self::Creado => 'Creó',
            self::Modificado => 'Modificó',
            self::Eliminado => 'Eliminó',
        };
    }
}
