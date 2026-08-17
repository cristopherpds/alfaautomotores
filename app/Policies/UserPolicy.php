<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view the list of users.
     */
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can register new users.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can update the given user.
     */
    public function update(User $user, User $target): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can delete the given user.
     */
    public function delete(User $user, User $target): bool
    {
        return $user->isAdmin() && ! $user->is($target);
    }
}
