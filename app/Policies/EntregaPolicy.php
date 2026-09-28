<?php

namespace App\Policies;

use App\Concerns\GestionaCatalogo;
use App\Models\Entrega;
use App\Models\User;

class EntregaPolicy
{
    use GestionaCatalogo;

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
}
