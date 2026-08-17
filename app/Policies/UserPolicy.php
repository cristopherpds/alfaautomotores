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
     *
     * The owner can only be edited by themselves, and administrators can only
     * be edited by the owner or by themselves.
     */
    public function update(User $user, User $target): bool
    {
        if ($target->isOwner()) {
            return $user->is($target);
        }

        if ($target->isAdmin()) {
            return $user->isOwner() || $user->is($target);
        }

        return $user->isAdmin();
    }

    /**
     * Determine whether the user can delete the given user.
     *
     * Nobody can delete themselves nor the owner, and only the owner can
     * delete an administrator.
     */
    public function delete(User $user, User $target): bool
    {
        if ($user->is($target) || $target->isOwner()) {
            return false;
        }

        if ($target->isAdmin()) {
            return $user->isOwner();
        }

        return $user->isAdmin();
    }
}
