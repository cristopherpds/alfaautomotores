<?php

namespace App\Policies;

use App\Concerns\GestionaCatalogo;
use App\Models\Conversacion;
use App\Models\User;

class ConversacionPolicy
{
    use GestionaCatalogo;

    /**
     * Determine whether the user can see the WhatsApp inbox.
     *
     * Todo el equipo: el taller y el lavadero también contestan por ahí.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Conversacion $conversacion): bool
    {
        return true;
    }

    /**
     * Determine whether the user can reply, take it from the AI or give it back.
     */
    public function update(User $user, Conversacion $conversacion): bool
    {
        return true;
    }

    /**
     * Determine whether the user can delete a conversation, files included.
     */
    public function delete(User $user, Conversacion $conversacion): bool
    {
        return $this->gestiona($user);
    }

    /**
     * Determine whether the user can manage the account side: templates.
     */
    public function administrar(User $user): bool
    {
        return $this->gestiona($user);
    }
}
