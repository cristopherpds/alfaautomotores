<?php

namespace App\Policies;

use App\Models\User;

/**
 * La auditoría sólo se consulta: nadie la edita ni la borra, así que no hay
 * más habilidades que `viewAny`.
 */
class AuditoriaPolicy
{
    /**
     * Determine whether the user can read the audit log.
     *
     * Sólo los admins (el dueño incluido): muestra lo que hizo cada persona
     * del local.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
