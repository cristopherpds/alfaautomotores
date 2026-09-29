<?php

namespace App\Policies;

use App\Concerns\GestionaCatalogo;
use App\Models\Cliente;
use App\Models\User;

class ClientePolicy
{
    use GestionaCatalogo;

    /**
     * Determine whether the user can see the client list.
     *
     * Todo el equipo: son los mismos datos que ya ve en cada turno.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can open a client's file.
     */
    public function view(User $user, Cliente $cliente): bool
    {
        return true;
    }

    /**
     * Determine whether the user can add a client by hand.
     */
    public function create(User $user): bool
    {
        return $this->gestiona($user);
    }

    /**
     * Determine whether the user can edit a client, notes and consent included.
     */
    public function update(User $user, Cliente $cliente): bool
    {
        return $this->gestiona($user);
    }

    /**
     * Determine whether the user can download the client list.
     *
     * Sale del sistema con datos personales: la misma gente que los edita.
     */
    public function export(User $user): bool
    {
        return $this->gestiona($user);
    }

    /**
     * Determine whether the user can delete a client.
     *
     * Sólo un admin: es el pedido de supresión de datos de la ley 18.331. Los
     * turnos del cliente quedan, con su propia copia de los datos.
     */
    public function delete(User $user, Cliente $cliente): bool
    {
        return $user->isAdmin();
    }
}
