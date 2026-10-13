<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Client\Response;

/**
 * Un error de la Cloud API de Meta, con su código.
 *
 * `codigoMeta` es el `error.code` de Graph (131047: ventana de 24 h cerrada,
 * 131026: el número no tiene WhatsApp…). El mensaje nunca lleva el token.
 */
class WhatsappException extends Exception
{
    public const VENTANA_CERRADA = 131047;

    public function __construct(string $message, public readonly ?int $codigoMeta = null)
    {
        parent::__construct($message);
    }

    public static function desdeRespuesta(Response $respuesta): self
    {
        $error = (array) $respuesta->json('error', []);
        $detalle = $error['error_data']['details'] ?? $error['message'] ?? 'Error de WhatsApp ('.$respuesta->status().')';

        return new self((string) $detalle, isset($error['code']) ? (int) $error['code'] : null);
    }

    public static function sinConfigurar(): self
    {
        return new self('WhatsApp no está configurado: faltan las credenciales de Meta.');
    }

    /**
     * Lo que se le muestra al equipo en el panel.
     */
    public function paraElPanel(): string
    {
        return match ($this->codigoMeta) {
            self::VENTANA_CERRADA => 'Pasaron más de 24 h desde el último mensaje del cliente: sólo se puede mandar una plantilla.',
            131026 => 'Ese número no puede recibir mensajes de WhatsApp.',
            default => $this->getMessage(),
        };
    }
}
