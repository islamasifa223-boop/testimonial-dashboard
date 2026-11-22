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
            Plan::updateOrCreate(
                ['name' => $plan['name']],
                $plan,
            );
        }
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
                'stripe_price_id' => null,
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
                'stripe_price_id' => null,
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
