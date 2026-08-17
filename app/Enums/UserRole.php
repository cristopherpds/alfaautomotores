<?php

namespace App\Enums;

use App\Models\User;

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
     * Get the roles the given user is allowed to assign.
     *
     * Only the account owner may hand out the administrator role.
     *
     * @return array<int, self>
     */
    public static function assignableBy(User $user): array
    {
        if ($user->isOwner()) {
            return self::cases();
        }

        return array_values(array_filter(
            self::cases(),
            fn (self $role): bool => $role !== self::Admin,
        ));
    }

    /**
     * Get the given roles formatted for the frontend select inputs.
     *
     * @param  array<int, self>|null  $roles
     * @return array<int, array{value: string, label: string, description: string}>
     */
    public static function options(?array $roles = null): array
    {
        return array_map(fn (self $role): array => [
            'value' => $role->value,
            'label' => $role->label(),
            'description' => $role->description(),
        ], $roles ?? self::cases());
    }
}
