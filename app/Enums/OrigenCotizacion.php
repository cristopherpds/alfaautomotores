<?php

namespace App\Enums;

use App\Concerns\ProvidesSelectOptions;

/**
 * Por dónde llegó una solicitud de «Cotizá tu auto»: el formulario del sitio
 * o una conversación de WhatsApp con el agente de IA.
 */
enum OrigenCotizacion: string
{
    use ProvidesSelectOptions;

    case Web = 'web';
    case Whatsapp = 'whatsapp';

    /**
     * Get the human readable label for the origin.
     */
    public function label(): string
    {
        return match ($this) {
            self::Web => 'Formulario web',
            self::Whatsapp => 'WhatsApp',
        };
    }
}
