<?php

namespace App\Policies;

use App\Models\Space;
use App\Models\User;

class SpacePolicy
{
    /**
     * Determine whether the user can view the space.
     */
    public function view(User $user, Space $space): bool
    {
        return $this->owns($user, $space);
    }

    /**
     * Determine whether the user can update the space.
     */
    public function update(User $user, Space $space): bool
    {
        return $this->owns($user, $space);
    }

    /**
     * Determine whether the user can delete the space.
     */
    public function delete(User $user, Space $space): bool
    {
        return $this->owns($user, $space);
    }

    /**
     * A space is only ever accessible to the account that owns it.
     */
    protected function owns(User $user, Space $space): bool
    {
        return $user->id === $space->user_id;
    }
}
