<?php

namespace App\Enums;

/**
 * Situación comercial de un vehículo del stock.
 */
enum EstadoVehiculo: string
{
    case Borrador = 'borrador';
    case Publicado = 'publicado';
    case Reservado = 'reservado';
    case Vendido = 'vendido';

    /**
     * Get the human readable label for the state.
     *
     * Es la etiqueta interna del panel: de cara al cliente `publicado` se
     * muestra como "Disponible" desde `estadoLegible()` en
     * `resources/js/lib/catalogo.ts`.
     */
    public function label(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::Publicado => 'Publicado',
            self::Reservado => 'Reservado',
            self::Vendido => 'Vendido',
        };
    }

    /**
     * Get a short description of what the state means for the public site.
     */
    public function description(): string
    {
        return match ($this) {
            self::Borrador => 'A medio cargar: no aparece en el sitio.',
            self::Publicado => 'A la venta y visible en el catálogo.',
            self::Reservado => 'Con seña: se muestra, pero no se ofrece.',
            self::Vendido => 'Ya se vendió: sale del catálogo, su ficha queda como referencia.',
        };
    }

    /**
     * Determine whether a vehicle in this state reaches the public site.
     */
    public function esPublico(): bool
    {
        return $this !== self::Borrador;
    }

    /**
     * Determine whether a vehicle in this state is offered in the listings:
     * the catalogue grid, the landing page and the stock count.
     *
     * Un vendido es público (su ficha sigue abriendo por un link viejo) pero
     * ya no se ofrece, así que no se lista ni se destaca.
     */
    public function esListable(): bool
    {
        return in_array($this, self::listables(), true);
    }

    /**
     * The states that make it into the public listings.
     *
     * @return list<self>
     */
    public static function listables(): array
    {
        return [self::Publicado, self::Reservado];
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
