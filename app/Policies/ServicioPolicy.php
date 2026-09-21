<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Servicio;
use App\Models\User;

class ServicioPolicy
{
    /**
     * Determine whether the user can see the workshop services.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can add a service.
     */
    public function create(User $user): bool
    {
        return $this->gestiona($user);
    }

    /**
     * Determine whether the user can edit a service.
     */
    public function update(User $user, Servicio $servicio): bool
    {
        return $this->gestiona($user);
    }

    /**
     * Determine whether the user can remove a service.
     */
    public function delete(User $user, Servicio $servicio): bool
    {
        return $this->gestiona($user);
    }

    /**
     * El taller lo maneja quien vende; el rol Equipo sólo consulta.
     *
     * Duplicado a propósito con `VehiculoPolicy` y `EntregaPolicy`: son tres
     * líneas y extraerlas a un trait recién valdría con una cuarta policy.
     */
    private function gestiona(User $user): bool
    {
        return $user->isAdmin() || $user->hasRole(UserRole::Vendedor);
    }
}
