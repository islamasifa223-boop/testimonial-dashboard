<?php

namespace App\Policies;

use App\Models\Testimonial;
use App\Models\User;

class TestimonialPolicy
{
    /**
     * Determine whether the user can view the testimonial.
     */
    public function view(User $user, Testimonial $testimonial): bool
    {
        return $this->owns($user, $testimonial);
    }

    /**
     * Determine whether the user can update the testimonial.
     */
    public function update(User $user, Testimonial $testimonial): bool
    {
        return $this->owns($user, $testimonial);
    }

    /**
     * Determine whether the user can delete the testimonial.
     */
    public function delete(User $user, Testimonial $testimonial): bool
    {
        return $this->owns($user, $testimonial);
    }

    /**
     * Determine whether the user can approve or unapprove the testimonial.
     */
    public function approve(User $user, Testimonial $testimonial): bool
    {
        return $this->owns($user, $testimonial);
    }

    /**
     * Determine whether the user can hide or unhide the testimonial.
     */
    public function hide(User $user, Testimonial $testimonial): bool
    {
        return $this->owns($user, $testimonial);
    }

    /**
     * Determine whether the user can favourite or unfavourite the testimonial.
     */
    public function favorite(User $user, Testimonial $testimonial): bool
    {
        return $this->owns($user, $testimonial);
    }

    /**
     * Determine whether the user can add or remove the testimonial from the Wall.
     */
    public function heart(User $user, Testimonial $testimonial): bool
    {
        return $this->owns($user, $testimonial);
    }

    /**
     * A testimonial belongs to the account that owns its space.
     *
     * The space is read through the nullsafe operator because a space that
     * has been soft deleted leaves the relation empty. Such a testimonial
     * belongs to nobody, so the request is denied rather than running into
     * a missing relation.
     */
    protected function owns(User $user, Testimonial $testimonial): bool
    {
        return $user->id === $testimonial->space?->user_id;
    }
}
