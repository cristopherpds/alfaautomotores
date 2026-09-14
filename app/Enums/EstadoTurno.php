<?php

namespace App\Enums;

/**
 * Situación de un turno del taller.
 *
 * Un turno pendiente ya ocupa el horario: se reserva primero y se confirma
 * después, por WhatsApp. Cancelarlo devuelve el hueco a la web.
 */
enum EstadoTurno: string
{
    case Pendiente = 'pendiente';
    case Confirmado = 'confirmado';
    case Cancelado = 'cancelado';
    case Completado = 'completado';

    /**
     * Get the human readable label for the state.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Confirmado => 'Confirmado',
            self::Cancelado => 'Cancelado',
            self::Completado => 'Completado',
        };
    }

    /**
     * Get a short description of what the state means for the shop.
     */
    public function description(): string
    {
        return match ($this) {
            self::Pendiente => 'Reservado desde la web, falta confirmarlo.',
            self::Confirmado => 'Confirmado con el cliente.',
            self::Cancelado => 'Se dio de baja: el horario volvió a quedar libre.',
            self::Completado => 'El trabajo ya se hizo.',
        };
    }

    /**
     * Determine whether a turn in this state still holds its slot.
     */
    public function ocupaElHorario(): bool
    {
        return $this !== self::Cancelado;
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
