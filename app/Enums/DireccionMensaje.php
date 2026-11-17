<?php

namespace App\Enums;

/**
 * Hacia dónde va un mensaje de WhatsApp.
 */
enum DireccionMensaje: string
{
    case Entrante = 'entrante';
    case Saliente = 'saliente';
}
