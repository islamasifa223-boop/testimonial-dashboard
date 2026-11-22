<?php

namespace Database\Seeders;

use App\Enums\PlanName;
use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Seed the canonical Free and Pro plans.
     *
     * Idempotent: re-running updates the existing rows rather than
     * duplicating them, so plan limits can be corrected in place.
     */
    public function run(): void
    {
        foreach ($this->plans() as $plan) {
            $plan = Plan::updateOrCreate(
                ['name' => $plan['name']],
                $plan,
            );

            $this->applyStripePriceId($plan);
        }
    }

    /**
     * Set the Stripe Price ID from the environment, but only for the Pro plan
     * and only when the plan does not already have one.
     *
     * A Price ID that is already stored is treated as authoritative and is
     * never overwritten, so re-seeding cannot unconfigure a working
     * environment. The Free plan is never given a Price ID.
     */
    protected function applyStripePriceId(Plan $plan): void
    {
        if ($plan->name !== PlanName::Pro || $plan->stripePriceId() !== null) {
            return;
        }

        $configured = config('services.stripe.pro_price_id');

        if (! is_string($configured) || trim($configured) === '') {
            return;
        }

        $plan->forceFill(['stripe_price_id' => trim($configured)])->save();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function plans(): array
    {
        return [
            [
                'name' => PlanName::Free,
                'label' => 'Free',
                'price_monthly_cents' => 0,
                'max_spaces' => 3,
                'max_testimonials_per_space' => 100,
                'features' => [
                    'Dashboard analytics',
                    'Wall of Love',
                    'Embed widgets',
                    'Themes',
                ],
            ],
            [
                'name' => PlanName::Pro,
                'label' => 'Pro',
                'price_monthly_cents' => 99900,
                'max_spaces' => 25,
                'max_testimonials_per_space' => 1000,
                'features' => [
                    'Dashboard analytics',
                    'Wall of Love',
                    'Embed widgets',
                    'Themes',
                    'Priority support',
                ],
            ],
        ];
    }
}
