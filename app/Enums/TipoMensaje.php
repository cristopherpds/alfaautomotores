<?php

namespace App\Enums;

/**
 * Qué trae un mensaje de WhatsApp.
 *
 * Los entrantes se traducen desde el `type` de Meta con `desdeMeta()`; lo que
 * no se reconoce entra como `NoSoportado` en vez de romper la ingesta.
 */
enum TipoMensaje: string
{
    case Texto = 'texto';
    case Imagen = 'imagen';
    case Audio = 'audio';
    case Video = 'video';
    case Documento = 'documento';
    case Sticker = 'sticker';
    case Ubicacion = 'ubicacion';
    case Contacto = 'contacto';
    case Interactivo = 'interactivo';
    case Reaccion = 'reaccion';
    case Plantilla = 'plantilla';
    case NoSoportado = 'no_soportado';

    /**
     * The case for a Meta message `type`.
     */
    public static function desdeMeta(string $tipo): self
    {
        return match ($tipo) {
            'text' => self::Texto,
            'image' => self::Imagen,
            'audio' => self::Audio,
            'video' => self::Video,
            'document' => self::Documento,
            'sticker' => self::Sticker,
            'location' => self::Ubicacion,
            'contacts' => self::Contacto,
            // Un botón de plantilla o una opción de lista: llega como texto.
            'interactive', 'button' => self::Interactivo,
            'reaction' => self::Reaccion,
            default => self::NoSoportado,
        };
    }

    /**
     * La clave del objeto con el adjunto en el payload de Meta, si la hay.
     */
    public function claveDeMedia(): ?string
    {
        return match ($this) {
            self::Imagen => 'image',
            self::Audio => 'audio',
            self::Video => 'video',
            self::Documento => 'document',
            self::Sticker => 'sticker',
            default => null,
        };
    }

    /**
     * El texto de la vista previa de la bandeja para un mensaje sin cuerpo.
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::Texto => 'Mensaje',
            self::Imagen => 'Foto',
            self::Audio => 'Audio',
            self::Video => 'Video',
            self::Documento => 'Documento',
            self::Sticker => 'Sticker',
            self::Ubicacion => 'Ubicación',
            self::Contacto => 'Contacto',
            self::Interactivo => 'Respuesta',
            self::Reaccion => 'Reacción',
            self::Plantilla => 'Plantilla',
            self::NoSoportado => 'Mensaje no soportado',
        };
    }
}
