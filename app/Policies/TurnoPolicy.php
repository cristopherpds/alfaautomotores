<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Turno;
use App\Models\User;

class TurnoPolicy
{
    /**
     * Determine whether the user can see the booked appointments.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can book an appointment.
     */
    public function create(User $user): bool
    {
        return $this->gestiona($user);
    }

    /**
     * Determine whether the user can change an appointment.
     */
    public function update(User $user, Turno $turno): bool
    {
        return $this->gestiona($user);
    }

    /**
     * Determine whether the user can delete an appointment.
     */
    public function delete(User $user, Turno $turno): bool
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
