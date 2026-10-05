<?php

namespace App\Policies;

use App\Concerns\GestionaCatalogo;
use App\Models\HeroSlide;
use App\Models\User;

class HeroSlidePolicy
{
    use GestionaCatalogo;

    /**
     * Determine whether the user can see the home page slides.
     *
     * Mismo criterio que las entregas: mirarlos es parte del trabajo de todo
     * el local, incluso de quien no los carga.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can add a slide.
     */
    public function create(User $user): bool
    {
        return $this->gestiona($user);
    }

    /**
     * Determine whether the user can edit a slide.
     */
    public function update(User $user, HeroSlide $heroSlide): bool
    {
        return $this->gestiona($user);
    }

    /**
     * Determine whether the user can remove a slide.
     */
    public function delete(User $user, HeroSlide $heroSlide): bool
    {
        return $this->gestiona($user);
    }
}
