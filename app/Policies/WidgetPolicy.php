<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Widget;

class WidgetPolicy
{
    /**
     * Determine whether the user can view the widget.
     */
    public function view(User $user, Widget $widget): bool
    {
        return $this->owns($user, $widget);
    }

    /**
     * Determine whether the user can update the widget.
     */
    public function update(User $user, Widget $widget): bool
    {
        return $this->owns($user, $widget);
    }

    /**
     * Determine whether the user can delete the widget.
     */
    public function delete(User $user, Widget $widget): bool
    {
        return $this->owns($user, $widget);
    }

    /**
     * A widget belongs to the account that owns its space.
     *
     * The space is read through the nullsafe operator because a space
     * that has been soft deleted leaves the relation empty. Such a
     * widget belongs to nobody, so the request is denied rather than
     * running into a missing relation.
     */
    protected function owns(User $user, Widget $widget): bool
    {
        return $user->id === $widget->space?->user_id;
    }
}
