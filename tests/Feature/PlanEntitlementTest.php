<?php

use App\Enums\PlanName;
use App\Exceptions\PlanLimitExceeded;
use App\Models\Plan;
use App\Models\Space;
use App\Models\User;
use App\Services\PlanEntitlement;

beforeEach(function () {
    seedPlans();
});

test('a new account is placed on the free plan', function () {
    $user = User::factory()->create();

    expect($user->plan)->not->toBeNull()
        ->and($user->plan->isFree())->toBeTrue()
        ->and($user->onFreePlan())->toBeTrue()
        ->and($user->onProPlan())->toBeFalse();
});

test('the free plan limits are exposed on the account', function () {
    $user = User::factory()->create();

    expect($user->spacesLimit())->toBe(3)
        ->and($user->testimonialsLimit())->toBe(100);
});

test('the pro plan limits are exposed on the account', function () {
    $pro = Plan::query()->where('name', PlanName::Pro)->firstOrFail();

    $user = User::factory()->create(['plan_id' => $pro->id]);

    expect($user->onProPlan())->toBeTrue()
        ->and($user->onFreePlan())->toBeFalse()
        ->and($user->spacesLimit())->toBe(25)
        ->and($user->testimonialsLimit())->toBe(1000);
});

test('an account cannot change its own plan through mass assignment', function () {
    $pro = Plan::query()->where('name', PlanName::Pro)->firstOrFail();

    $user = User::factory()->create();

    $user->update(['plan_id' => $pro->id]);

    expect($user->fresh()->plan_id)->not->toBe($pro->id);
});

test('the number of spaces used is counted for the account', function () {
    $user = User::factory()->create();

    Space::factory()->for($user)->count(2)->create();

    $entitlement = app(PlanEntitlement::class);

    expect($entitlement->spacesUsed($user))->toBe(2)
        ->and($entitlement->spacesLimit($user))->toBe(3)
        ->and($entitlement->spacesRemaining($user))->toBe(1);
});

test('spaces belonging to another account are not counted', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    Space::factory()->for($other)->count(2)->create();

    $entitlement = app(PlanEntitlement::class);

    expect($entitlement->spacesUsed($user))->toBe(0)
        ->and($entitlement->spacesRemaining($user))->toBe(3);
});

test('a space can be created while the account is under the limit', function () {
    $user = User::factory()->create();

    Space::factory()->for($user)->count(2)->create();

    $entitlement = app(PlanEntitlement::class);

    expect($entitlement->canCreateSpace($user))->toBeTrue();

    $entitlement->assertCanCreateSpace($user);
});

test('a space cannot be created once the limit is reached', function () {
    $user = User::factory()->create();

    Space::factory()->for($user)->count(3)->create();

    $entitlement = app(PlanEntitlement::class);

    expect($entitlement->canCreateSpace($user))->toBeFalse()
        ->and($entitlement->spacesRemaining($user))->toBe(0);
});

test('the limit is enforced with a plan limit exception', function () {
    $user = User::factory()->create();

    Space::factory()->for($user)->count(3)->create();

    $entitlement = app(PlanEntitlement::class);

    expect(fn () => $entitlement->assertCanCreateSpace($user))
        ->toThrow(PlanLimitExceeded::class);
});

test('the plan limit exception carries the resource and the limit', function () {
    $user = User::factory()->create();

    Space::factory()->for($user)->count(3)->create();

    $entitlement = app(PlanEntitlement::class);

    try {
        $entitlement->assertCanCreateSpace($user);

        $this->fail('Expected a PlanLimitExceeded exception.');
    } catch (PlanLimitExceeded $exception) {
        expect($exception->resource)->toBe('space')
            ->and($exception->limit)->toBe(3);
    }
});

test('the pro plan allows more spaces than the free plan', function () {
    $pro = Plan::query()->where('name', PlanName::Pro)->firstOrFail();

    $user = User::factory()->create(['plan_id' => $pro->id]);

    Space::factory()->for($user)->count(3)->create();

    $entitlement = app(PlanEntitlement::class);

    expect($entitlement->canCreateSpace($user))->toBeTrue()
        ->and($entitlement->spacesRemaining($user))->toBe(22);
});

test('the remaining allowance never drops below zero', function () {
    $pro = Plan::query()->where('name', PlanName::Pro)->firstOrFail();

    $user = User::factory()->create(['plan_id' => $pro->id]);

    Space::factory()->for($user)->count(30)->create();

    $entitlement = app(PlanEntitlement::class);

    expect($entitlement->spacesRemaining($user))->toBe(0)
        ->and($entitlement->canCreateSpace($user))->toBeFalse();
});

test('an account without a plan is treated as a free account', function () {
    $user = new User(['name' => 'No Plan', 'email' => 'noplan@example.com']);
    $user->id = 999;

    expect($user->onFreePlan())->toBeTrue()
        ->and($user->onProPlan())->toBeFalse()
        ->and($user->spacesLimit())->toBe(0);
});

test('the plan model reports its tier', function () {
    $free = Plan::query()->where('name', PlanName::Free)->firstOrFail();
    $pro = Plan::query()->where('name', PlanName::Pro)->firstOrFail();

    expect($free->isFree())->toBeTrue()
        ->and($free->isPro())->toBeFalse()
        ->and($pro->isPro())->toBeTrue()
        ->and($pro->isFree())->toBeFalse();
});

test('the plan model reports its monthly price', function () {
    $free = Plan::query()->where('name', PlanName::Free)->firstOrFail();
    $pro = Plan::query()->where('name', PlanName::Pro)->firstOrFail();

    expect($free->priceMonthly())->toBe('0.00')
        ->and($pro->priceMonthly())->toBe('999.00');
});

test('the plan name is cast to the plan name enum', function () {
    $free = Plan::query()->where('name', PlanName::Free)->firstOrFail();
    $pro = Plan::query()->where('name', PlanName::Pro)->firstOrFail();

    expect($free->name)->toBe(PlanName::Free)
        ->and($pro->name)->toBe(PlanName::Pro);
});

test('a plan can be fetched by its enum name', function () {
    $plan = Plan::query()->where('name', PlanName::Pro)->first();

    expect($plan)->not->toBeNull()
        ->and($plan->isPro())->toBeTrue();
});
