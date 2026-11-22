<?php

use App\Enums\PlanName;
use App\Models\Plan;
use Database\Seeders\PlanSeeder;

it('seeds the free and pro plans', function () {
    $this->seed(PlanSeeder::class);

    expect(Plan::query()->count())->toBe(2)
        ->and(Plan::query()->where('name', PlanName::Free)->firstOrFail()->max_spaces)->toBe(3)
        ->and(Plan::query()->where('name', PlanName::Pro)->firstOrFail()->max_spaces)->toBe(25);
});

it('sets the pro price id from the environment when it is not already configured', function () {
    config(['services.stripe.pro_price_id' => 'price_from_the_environment']);

    $this->seed(PlanSeeder::class);

    expect(Plan::query()->where('name', PlanName::Pro)->firstOrFail()->stripePriceId())
        ->toBe('price_from_the_environment');
});

it('never overwrites a pro price id that is already configured', function () {
    config(['services.stripe.pro_price_id' => 'price_from_the_environment']);

    $pro = Plan::factory()->pro()->create(['stripe_price_id' => 'price_already_configured']);

    $this->seed(PlanSeeder::class);

    expect($pro->fresh()->stripePriceId())->toBe('price_already_configured');
});

it('never gives the free plan a price id', function () {
    config(['services.stripe.pro_price_id' => 'price_from_the_environment']);

    $this->seed(PlanSeeder::class);

    expect(Plan::query()->where('name', PlanName::Free)->firstOrFail()->stripePriceId())
        ->toBeNull();
});

it('does not write a price id when the environment value is blank', function () {
    config(['services.stripe.pro_price_id' => null]);

    $this->seed(PlanSeeder::class);

    expect(Plan::query()->where('name', PlanName::Pro)->firstOrFail()->stripePriceId())
        ->toBeNull();
});

it('does not write a price id when the environment value is only whitespace', function () {
    config(['services.stripe.pro_price_id' => '   ']);

    $this->seed(PlanSeeder::class);

    expect(Plan::query()->where('name', PlanName::Pro)->firstOrFail()->stripePriceId())
        ->toBeNull();
});

it('is safe to run more than once', function () {
    config(['services.stripe.pro_price_id' => 'price_from_the_environment']);

    $this->seed(PlanSeeder::class);
    $this->seed(PlanSeeder::class);

    expect(Plan::query()->count())->toBe(2)
        ->and(Plan::query()->where('name', PlanName::Pro)->firstOrFail()->stripePriceId())
        ->toBe('price_from_the_environment');
});

it('keeps the plan limits when it is run again', function () {
    $this->seed(PlanSeeder::class);

    Plan::query()->where('name', PlanName::Pro)->firstOrFail()
        ->forceFill(['max_spaces' => 99])->save();

    $this->seed(PlanSeeder::class);

    expect(Plan::query()->where('name', PlanName::Pro)->firstOrFail()->max_spaces)->toBe(25);
});
