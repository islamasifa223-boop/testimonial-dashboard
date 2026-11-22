<?php

namespace App\Services;

use App\Exceptions\PlanLimitExceeded;
use App\Models\Plan;
use App\Models\Space;
use App\Models\User;

/**
 * Server-side enforcement of plan limits.
 *
 * Every limit check lives here so the rules are applied identically
 * regardless of which part of the application triggers them.
 */
class PlanEntitlement
{
    /**
     * Number of spaces the account currently owns.
     */
    public function spacesUsed(User $user): int
    {
        return $user->spaces()->count();
    }

    /**
     * Maximum number of spaces the account may own.
     */
    public function spacesLimit(User $user): int
    {
        return $user->spacesLimit();
    }

    /**
     * Remaining spaces available to the account.
     */
    public function spacesRemaining(User $user): int
    {
        return max(0, $this->spacesLimit($user) - $this->spacesUsed($user));
    }

    public function canCreateSpace(User $user): bool
    {
        return $this->spacesRemaining($user) > 0;
    }

    /**
     * @throws PlanLimitExceeded
     */
    public function assertCanCreateSpace(User $user): void
    {
        if (! $this->canCreateSpace($user)) {
            throw new PlanLimitExceeded('space', $this->spacesLimit($user));
        }
    }

    /**
     * Maximum number of testimonials allowed within a single space.
     */
    public function testimonialsLimit(User $user): int
    {
        return $user->testimonialsLimit();
    }

    /**
     * Number of testimonials already collected by the given space.
     */
    public function testimonialsUsed(Space $space): int
    {
        return $space->testimonials()->count();
    }

    /**
     * Whether the owner of the space may collect another testimonial.
     */
    public function canSubmitTestimonial(Space $space): bool
    {
        $owner = $space->user;

        if ($owner === null) {
            return false;
        }

        return $this->testimonialsUsed($space) < $this->testimonialsLimitFor($space);
    }

    /**
     * Ensure the owner of the space may collect another testimonial.
     *
     * @throws PlanLimitExceeded
     */
    public function assertCanSubmitTestimonial(Space $space): void
    {
        if ($this->canSubmitTestimonial($space)) {
            return;
        }

        $owner = $space->user;

        // A space without an owner can never collect a testimonial, so
        // the limit reported in the exception is zero.
        if ($owner === null) {
            throw new PlanLimitExceeded('testimonial', 0);
        }

        throw new PlanLimitExceeded('testimonial', $this->testimonialsLimitFor($space));
    }

    /**
     * The maximum number of testimonials allowed for the owner of the space.
     *
     * The owner's plan id is read through a fresh relationship query and
     * the plan is read straight from the database, so a limit that
     * changed while the request was running is always seen, rather than
     * a cached relation that may be out of date.
     */
    public function testimonialsLimitFor(Space $space): int
    {
        $planId = $space->user()->value('plan_id');

        if ($planId === null) {
            return 0;
        }

        $limit = Plan::query()
            ->whereKey($planId)
            ->value('max_testimonials_per_space');

        return $limit === null ? 0 : (int) $limit;
    }
}
