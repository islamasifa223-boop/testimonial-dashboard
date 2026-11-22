<?php

namespace App\Listeners;

use App\Services\PlanProvisioner;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Events\WebhookHandled;

/**
 * Applies plan changes from a verified Stripe webhook.
 *
 * This listener only ever runs for events that Cashier has already accepted
 * and handled, which means the request carried a valid Stripe signature.
 */
class HandleStripeSubscriptionEvent
{
    public function __construct(private readonly PlanProvisioner $provisioner) {}

    /**
     * Handle a Stripe webhook that Cashier has processed.
     */
    public function handle(WebhookHandled $event): void
    {
        // Without a configured webhook secret Cashier does not verify the
        // signature of incoming requests, so no event may be trusted.
        if (blank(config('cashier.webhook.secret'))) {
            Log::warning('A Stripe webhook was received but no webhook secret is configured. The event was ignored.');

            return;
        }

        $type = $event->payload['type'] ?? null;

        if (! is_string($type) || ! str_starts_with($type, 'customer.subscription.')) {
            // Only subscription events can change a plan.
            return;
        }

        if ($type === 'customer.subscription.deleted') {
            // A deletion is authoritative on its own. The status Stripe sends
            // with the event still reflects the subscription as it was before
            // the deletion, so it cannot be used to decide the plan.
            $this->provisioner->revoke($event->payload);

            return;
        }

        $this->provisioner->handle($event->payload);
    }
}
