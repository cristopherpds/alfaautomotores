<?php

namespace App\Enums;

/**
 * Disponibilidad de un producto del catálogo de movilidad.
 *
 * Tres de los cuatro estados llegan al público, con distinta promesa:
 * `Publicado` está en el local, `SinStock` se muestra agotado —es lo que el
 * catálogo impreso marca «SIN STOCK»— y `PorEncargue` es el modelo que el
 * proveedor ya no publica en su web pero sigue consiguiendo a pedido. Sólo
 * `Borrador` no sale.
 *
 * Es el mismo reparto que `EstadoVehiculo` pero con otro vocabulario: allá el
 * eje es la venta del auto (reservado, vendido) y acá es el abastecimiento.
 */
enum EstadoProducto: string
{
    case Borrador = 'borrador';
    case Publicado = 'publicado';
    case SinStock = 'sin_stock';
    case PorEncargue = 'por_encargue';

    /**
     * Get the human readable label for the state.
     *
     * Es la etiqueta del panel, la misma del badge del listado. El cliente ve
     * otra: `estadoLegible()` de `lib/productos.ts` dice «Disponible».
     */
    public function label(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::Publicado => 'Publicado',
            self::SinStock => 'Sin stock',
            self::PorEncargue => 'Por encargue',
        };
    }

    /**
     * Get a short description of what the state means for the public site.
     */
    public function description(): string
    {
        return match ($this) {
            self::Borrador => 'A medio cargar: no aparece en el sitio.',
            self::Publicado => 'En el local, listo para entregar. El sitio lo muestra como disponible.',
            self::SinStock => 'Agotado: se muestra, pero no se ofrece.',
            self::PorEncargue => 'Se consigue a pedido, con demora.',
        };
    }

    /**
     * Determine whether a product in this state reaches the public site.
     */
    public function esPublico(): bool
    {
        return $this !== self::Borrador;
    }

    /**
     * Get the states formatted for the frontend select inputs.
     *
     * @return array<int, array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $estado): array => [
            'value' => $estado->value,
            'label' => $estado->label(),
            'description' => $estado->description(),
        ], self::cases());
    }
}
