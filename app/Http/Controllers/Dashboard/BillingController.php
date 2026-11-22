<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\PlanName;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\User;
use App\Services\PlanEntitlement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Laravel\Cashier\Subscription;

class BillingController extends Controller
{
    public function __construct(private readonly PlanEntitlement $entitlement) {}

    /**
     * Show the billing page with the current plan and real usage.
     */
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        return view('dashboard.billing', [
            'currentPlan' => $user->plan,
            'spacesUsed' => $this->entitlement->spacesUsed($user),
            'spacesLimit' => $this->entitlement->spacesLimit($user),
            'plans' => Plan::query()->orderBy('price_monthly_cents')->get(),
            'proFeatures' => Plan::query()
                ->where('name', PlanName::Pro)
                ->first()
                ?->features ?? [],
            'subscription' => $this->currentSubscription($user),
            'upgradePending' => $request->boolean('upgraded'),
            'proCheckoutAvailable' => $this->proCheckoutAvailable(),
        ]);
    }

    /**
     * Start a Stripe Checkout session for the Pro plan.
     *
     * The redirect back to the billing page never changes the plan. Access is
     * granted only by a verified Stripe webhook.
     */
    public function upgrade(Request $request): RedirectResponse
    {
        $user = $request->user();

        $pro = Plan::query()->where('name', PlanName::Pro)->first();

        if ($pro === null || $pro->stripePriceId() === null) {
            return back()->with('error', 'Pro checkout is not available right now. Please try again later.');
        }

        if ($user->onProPlan()) {
            return back()->with('error', 'You are already on the Pro plan.');
        }

        $checkout = $user->checkout([
            [
                'price' => $pro->stripePriceId(),
                'quantity' => 1,
            ],
        ], [
            'mode' => 'subscription',
            'success_url' => route('dashboard.billing', ['upgraded' => 1]),
            'cancel_url' => route('dashboard.billing'),
        ]);

        return $checkout->redirect();
    }

    /**
     * Send the customer to the Stripe billing portal to manage their
     * subscription.
     */
    public function portal(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasStripeId()) {
            return back()->with('error', 'There is no billing account to manage yet.');
        }

        return redirect()->away($user->billingPortalUrl(route('dashboard.billing')));
    }

    /**
     * The account's most recent locally stored subscription.
     *
     * This is the last state Cashier recorded from a verified webhook. It is
     * displayed as-is and is never used to change the plan.
     */
    protected function currentSubscription(User $user): ?Subscription
    {
        return $user->subscriptions()->first();
    }

    /**
     * Whether the Pro plan can be bought in this environment.
     *
     * Checkout needs a Stripe Price ID for the Pro plan, which is configured
     * per environment.
     */
    protected function proCheckoutAvailable(): bool
    {
        return Plan::query()
            ->where('name', PlanName::Pro)
            ->first()
            ?->stripePriceId() !== null;
    }
}
