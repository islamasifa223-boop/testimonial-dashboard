<?php

namespace Database\Factories;

use App\Enums\PlanName;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Plan>
     */
    protected $model = Plan::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => PlanName::Free,
            'label' => 'Free',
            'price_monthly_cents' => 0,
            'stripe_price_id' => null,
            'max_spaces' => 3,
            'max_testimonials_per_space' => 100,
            'features' => [],
        ];
    }

    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => PlanName::Free,
            'label' => 'Free',
            'price_monthly_cents' => 0,
            'max_spaces' => 3,
            'max_testimonials_per_space' => 100,
        ]);
    }

    public function pro(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => PlanName::Pro,
            'label' => 'Pro',
            'price_monthly_cents' => 99900,
            'max_spaces' => 25,
            'max_testimonials_per_space' => 1000,
        ]);
    }
}
