<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Vendedor = 'vendedor';
    case Equipo = 'equipo';

    /**
     * Get the human readable label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Vendedor => 'Vendedor',
            self::Equipo => 'Equipo',
        };
    }

    /**
     * Get a short description of what the role is allowed to do.
     */
    public function description(): string
    {
        return match ($this) {
            self::Admin => 'Acceso total, incluyendo la gestión de usuarios y roles.',
            self::Vendedor => 'Gestiona vehículos, clientes y sus propias ventas.',
            self::Equipo => 'Acceso general de consulta, sin gestión de usuarios.',
        };
    }

    /**
     * Get every role formatted for the frontend select inputs.
     *
     * @return array<int, array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $role): array => [
            'value' => $role->value,
            'label' => $role->label(),
            'description' => $role->description(),
        ], self::cases());
    }
}
