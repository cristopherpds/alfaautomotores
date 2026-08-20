<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Vehiculo;

class VehiculoPolicy
{
    /**
     * Determine whether the user can see the stock list.
     *
     * Consultar el stock es parte del trabajo de todo el local, incluso de
     * quien no lo gestiona.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can add a vehicle to the stock.
     */
    public function create(User $user): bool
    {
        return $this->gestiona($user);
    }

    /**
     * Determine whether the user can edit a vehicle.
     *
     * Cubre también destacarlo y administrar sus fotos.
     */
    public function update(User $user, Vehiculo $vehiculo): bool
    {
        return $this->gestiona($user);
    }

    /**
     * Determine whether the user can remove a vehicle from the stock.
     */
    public function delete(User $user, Vehiculo $vehiculo): bool
    {
        return $this->gestiona($user);
    }

    /**
     * El stock lo maneja quien vende; el rol Equipo sólo consulta.
     */
    private function gestiona(User $user): bool
    {
        return $user->isAdmin() || $user->hasRole(UserRole::Vendedor);
    }
}
