<?php

namespace App\Policies;

use App\Concerns\GestionaCatalogo;
use App\Models\User;
use App\Models\Vehiculo;

class VehiculoPolicy
{
    use GestionaCatalogo;

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
     * Determine whether the user can edit vehicles in bulk.
     *
     * Va aparte de `update()` porque la ruta del lote no tiene un `{vehiculo}`
     * que resolver: sin modelo, `can:update` no se puede evaluar.
     */
    public function updateAny(User $user): bool
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
     * Determine whether the user can remove vehicles in bulk.
     */
    public function deleteAny(User $user): bool
    {
        return $this->gestiona($user);
    }
}
