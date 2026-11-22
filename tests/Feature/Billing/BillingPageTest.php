<?php

use App\Enums\PlanName;
use App\Models\Plan;
use App\Models\Space;
use App\Models\User;

beforeEach(function () {
    $plans = seedPlans();

    $this->proPriceId = 'price_pro_test_123';

    $plans['pro']->forceFill(['stripe_price_id' => $this->proPriceId])->save();
});

it('shows the billing page to a signed in account', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard.billing'))
        ->assertOk()
        ->assertSee('Plan and billing')
        ->assertSee('Compare plans');
});

it('does not show the billing page to a guest', function () {
    $this->get(route('dashboard.billing'))->assertRedirect(route('login'));
});

it('never grants pro from a browser redirect', function () {
    $user = User::factory()->create();

    expect($user->fresh()->plan->isFree())->toBeTrue();

    // The success redirect a customer returns on after paying carries this
    // flag. It must not change the plan.
    $this->actingAs($user)
        ->get(route('dashboard.billing', ['upgraded' => 1]))
        ->assertOk();

    expect($user->fresh()->plan->isFree())->toBeTrue();
});

it('never grants pro from the upgrade redirect with a manipulated query string', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard.billing', ['upgraded' => 1, 'plan' => 'pro']))
        ->assertOk();

    expect($user->fresh()->plan->isFree())->toBeTrue();
});

it('shows the processing notice after an upgrade redirect', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard.billing', ['upgraded' => 1]))
        ->assertOk()
        ->assertSeeText("We're processing your upgrade", false);
});

it('does not show the processing notice on a normal visit', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard.billing'))
        ->assertOk()
        ->assertDontSeeText("We're processing your upgrade", false);
});

it('offers the upgrade button when the pro price is configured', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard.billing'))
        ->assertOk()
        ->assertSee('Upgrade to Pro')
        ->assertSee(route('dashboard.billing.upgrade'), false);
});

it('disables the upgrade button when the pro price is not configured', function () {
    Plan::query()->where('name', PlanName::Pro)->firstOrFail()
        ->forceFill(['stripe_price_id' => null])->save();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard.billing'))
        ->assertOk()
        ->assertSee('Pro checkout is not available in this environment');
});

it('refuses to start a checkout when the pro price is not configured', function () {
    Plan::query()->where('name', PlanName::Pro)->firstOrFail()
        ->forceFill(['stripe_price_id' => null])->save();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('dashboard.billing.upgrade'))
        ->assertRedirect();

    expect($user->fresh()->plan->isFree())->toBeTrue();
});

it('refuses to start a checkout for an account that is already on pro', function () {
    $pro = Plan::query()->where('name', PlanName::Pro)->firstOrFail();

    $user = User::factory()->create(['plan_id' => $pro->id]);

    $this->actingAs($user)
        ->post(route('dashboard.billing.upgrade'))
        ->assertRedirect();

    expect($user->fresh()->plan->isPro())->toBeTrue();
});

it('does not let a guest reach the upgrade endpoint', function () {
    $this->post(route('dashboard.billing.upgrade'))->assertRedirect(route('login'));
});

it('does not let a guest reach the billing portal endpoint', function () {
    $this->get(route('dashboard.billing.portal'))->assertRedirect(route('login'));
});

it('shows the upgrade banner to a free account that is below its limit', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard.overview'))
        ->assertOk()
        ->assertSeeText('Unlock more with Pro');
});

it('shows the limit notice to a free account that has used all its spaces', function () {
    $user = User::factory()->create();

    Space::factory()->for($user)->count(3)->create();

    $this->actingAs($user)
        ->get(route('dashboard.overview'))
        ->assertOk()
        ->assertSeeText("You've used all 3 spaces", false);
});

it('does not show the upgrade banner to an account on pro', function () {
    $pro = Plan::query()->where('name', PlanName::Pro)->firstOrFail();

    $user = User::factory()->create(['plan_id' => $pro->id]);

    $this->actingAs($user)
        ->get(route('dashboard.overview'))
        ->assertOk()
        ->assertDontSeeText('Unlock more with Pro');
});

it('does not show the upgrade banner to a guest', function () {
    $this->get(route('dashboard.overview'))
        ->assertRedirect(route('login'));
});
