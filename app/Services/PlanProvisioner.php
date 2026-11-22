<?php

namespace App\Services;

use App\Enums\PlanName;
use App\Models\Plan;
use App\Models\User;
use Stripe\Subscription as StripeSubscription;

/**
 * Applies plan changes that originate from a verified Stripe webhook.
 *
 * The Pro plan is granted and revoked only from here. Nothing in this class
 * runs outside of a verified webhook, and nothing in this class trusts the
 * browser.
 */
class PlanProvisioner
{
    /**
     * Reconcile the user's plan with a Stripe subscription.
     *
     * The Price ID on the subscription is matched against the Price ID
     * configured for the Pro plan. A subscription on any other Price never
     * changes the user's plan.
     *
     * @param  array<string, mixed>  $payload  The raw Stripe webhook payload.
     */
    public function handle(array $payload): void
    {
        $object = $payload['data']['object'] ?? null;

        if (! is_array($object)) {
            return;
        }

        $user = $this->userForProSubscription($object);

        if ($user === null) {
            return;
        }

        $target = $this->targetPlan($object);

        if ($target === null) {
            // The subscription state does not map to a plan change.
            return;
        }

        $this->sync($user, $target);
    }

    /**
     * Return the account to Free because Stripe deleted the subscription.
     *
     * The deletion itself is the authority. The status carried by the event
     * still describes the subscription as it was before the deletion, so it
     * cannot be used to decide the plan.
     *
     * @param  array<string, mixed>  $payload  The raw Stripe webhook payload.
     */
    public function revoke(array $payload): void
    {
        $object = $payload['data']['object'] ?? null;

        if (! is_array($object)) {
            return;
        }

        $user = $this->userForProSubscription($object);

        if ($user === null) {
            return;
        }

        $this->sync($user, PlanName::Free);
    }

    /**
     * The account that owns the given subscription, if the subscription is
     * billed against the configured Pro Price.
     *
     * @param  array<string, mixed>  $object  The Stripe subscription object.
     */
    protected function userForProSubscription(array $object): ?User
    {
        $customer = $object['customer'] ?? null;

        if (! is_string($customer) || $customer === '') {
            return null;
        }

        $user = User::query()->where('stripe_id', $customer)->first();

        if ($user === null) {
            return null;
        }

        $priceId = $this->priceIdFromPayload($object);

        if ($priceId === null || ! $this->isProPrice($priceId)) {
            // A subscription on a Price that is not the configured Pro Price
            // must never change the plan.
            return null;
        }

        return $user;
    }

    /**
     * Move the user onto the plan the subscription state calls for.
     *
     * Idempotent: the plan is only written when it differs from the plan the
     * user already has, so repeated deliveries of the same event are safe.
     */
    public function sync(User $user, PlanName $target): void
    {
        $plan = Plan::query()->where('name', $target)->first();

        if ($plan === null) {
            return;
        }

        if ($user->plan_id === $plan->id) {
            return;
        }

        $user->forceFill(['plan_id' => $plan->id])->save();
    }

    /**
     * Determine the plan a subscription entitles the user to.
     *
     * A cancellation scheduled for the end of the paid period keeps the user
     * on Pro until that period actually ends. A payment failure keeps the user
     * on Pro until Stripe cancels the subscription. Only a subscription Stripe
     * has actually ended returns the user to Free.
     *
     * Returns null when the state does not call for a plan change.
     *
     * @param  array<string, mixed>  $object  The Stripe subscription object.
     */
    protected function targetPlan(array $object): ?PlanName
    {
        $status = $object['status'] ?? null;

        return match ($status) {
            // Stripe has ended the subscription outright.
            StripeSubscription::STATUS_CANCELED,
            StripeSubscription::STATUS_INCOMPLETE_EXPIRED => PlanName::Free,

            // Payment failed, or the first payment has not completed yet.
            // Pro is retained until Stripe cancels the subscription.
            StripeSubscription::STATUS_ACTIVE,
            StripeSubscription::STATUS_TRIALING,
            StripeSubscription::STATUS_PAST_DUE,
            StripeSubscription::STATUS_INCOMPLETE,
            StripeSubscription::STATUS_UNPAID => PlanName::Pro,

            // Any other state, including paused, leaves the plan unchanged
            // until a policy for it is explicitly defined.
            default => null,
        };
    }

    /**
     * The Stripe Price ID the subscription is billed against.
     *
     * @param  array<string, mixed>  $object  The Stripe subscription object.
     */
    protected function priceIdFromPayload(array $object): ?string
    {
        $priceId = $object['items']['data'][0]['price']['id'] ?? null;

        if (! is_string($priceId) || $priceId === '') {
            return null;
        }

        return $priceId;
    }

    /**
     * Determine whether the given Price ID is the configured Pro Price.
     *
     * The Price ID stored against the Pro plan is the only authoritative
     * source. No live Stripe API call is made to verify it.
     */
    protected function isProPrice(string $priceId): bool
    {
        $pro = Plan::query()->where('name', PlanName::Pro)->first();

        if ($pro === null) {
            return false;
        }

        $configured = $pro->stripePriceId();

        return $configured !== null && hash_equals($configured, $priceId);
    }
}
