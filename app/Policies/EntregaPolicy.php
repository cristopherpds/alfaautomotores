<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Entrega;
use App\Models\User;

class EntregaPolicy
{
    /**
     * Determine whether the user can see the delivery photos.
     *
     * Mismo criterio que el stock: mirarlas es parte del trabajo de todo el
     * local, incluso de quien no las carga.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can upload delivery photos.
     */
    public function create(User $user): bool
    {
        return $this->gestiona($user);
    }

    /**
     * Determine whether the user can fix the date of a delivery.
     */
    public function update(User $user, Entrega $entrega): bool
    {
        return $this->gestiona($user);
    }

    /**
     * Determine whether the user can remove a delivery from the strip.
     */
    public function delete(User $user, Entrega $entrega): bool
    {
        return $this->gestiona($user);
    }

    /**
     * La portada la maneja quien vende; el rol Equipo sólo consulta.
     *
     * Duplicado a propósito con `VehiculoPolicy::gestiona()`: son tres líneas y
     * extraerlas a un trait recién vale la pena con una tercera policy.
     */
    private function gestiona(User $user): bool
    {
        return $user->isAdmin() || $user->hasRole(UserRole::Vendedor);
    }
}
