<?php

namespace App\Concerns;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Quién carga lo que el sitio público muestra: el stock de autos, las fotos
 * de entregas y el catálogo de movilidad.
 *
 * Estaba duplicado en `VehiculoPolicy` y `EntregaPolicy`; con `ProductoPolicy`
 * ya eran tres copias y se extrajo acá.
 */
trait GestionaCatalogo
{
    /**
     * Lo maneja quien vende; el rol Equipo sólo consulta.
     */
    private function gestiona(User $user): bool
    {
        return $user->isAdmin() || $user->hasRole(UserRole::Vendedor);
    }
}
