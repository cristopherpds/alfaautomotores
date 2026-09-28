<?php

namespace App\Policies;

use App\Concerns\GestionaCatalogo;
use App\Models\Producto;
use App\Models\User;

class ProductoPolicy
{
    use GestionaCatalogo;

    /**
     * Determine whether the user can see the mobility catalogue.
     *
     * Mismo criterio que el stock de autos: consultarlo es parte del trabajo
     * de todo el local, incluso de quien no lo carga.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can add a product to the catalogue.
     */
    public function create(User $user): bool
    {
        return $this->gestiona($user);
    }

    /**
     * Determine whether the user can edit a product.
     *
     * Cubre también administrar sus fotos.
     */
    public function update(User $user, Producto $producto): bool
    {
        return $this->gestiona($user);
    }

    /**
     * Determine whether the user can remove a product from the catalogue.
     */
    public function delete(User $user, Producto $producto): bool
    {
        return $this->gestiona($user);
    }
}
