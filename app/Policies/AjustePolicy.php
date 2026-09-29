<?php

namespace App\Policies;

use App\Models\User;

class AjustePolicy
{
    /**
     * Determine whether the user can change the site settings.
     *
     * Sólo admins: cambian lo que ve todo el sitio público, número de
     * WhatsApp incluido.
     */
    public function update(User $user): bool
    {
        return $user->isAdmin();
    }
}
