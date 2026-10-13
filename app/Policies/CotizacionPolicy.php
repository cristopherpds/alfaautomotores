<?php

namespace App\Policies;

use App\Concerns\GestionaCatalogo;
use App\Models\Cotizacion;
use App\Models\User;

class CotizacionPolicy
{
    use GestionaCatalogo;

    /**
     * Determine whether the user can see the quote requests.
     *
     * Todo el equipo, como los clientes: son los mismos datos de contacto.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can open a quote request, photos included.
     */
    public function view(User $user, Cotizacion $cotizacion): bool
    {
        return true;
    }

    /**
     * Determine whether the user can move a quote request along.
     */
    public function update(User $user, Cotizacion $cotizacion): bool
    {
        return $this->gestiona($user);
    }

    /**
     * Determine whether the user can delete a quote request.
     */
    public function delete(User $user, Cotizacion $cotizacion): bool
    {
        return $this->gestiona($user);
    }
}
