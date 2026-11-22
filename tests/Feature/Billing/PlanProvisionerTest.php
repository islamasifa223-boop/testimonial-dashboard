<?php

use App\Enums\PlanName;
use App\Models\Plan;
use App\Models\User;
use App\Services\PlanProvisioner;

beforeEach(function () {
    $plans = seedPlans();

    $this->proPriceId = 'price_pro_test_123';
    $this->otherPriceId = 'price_someone_else_999';

    $plans['pro']->forceFill(['stripe_price_id' => $this->proPriceId])->save();
});

/**
 * Build a Stripe subscription payload.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function subscriptionPayload(array $overrides = []): array
{
    $object = array_merge([
        'id' => 'sub_test_123',
        'customer' => 'cus_test_123',
        'status' => 'active',
        'cancel_at_period_end' => false,
        'items' => [
            'data' => [
                [
                    'id' => 'si_test_123',
                    'price' => [
                        'id' => 'price_pro_test_123',
                        'product' => 'prod_test_123',
                    ],
                ],
            ],
        ],
    ], $overrides);

    return [
        'id' => 'evt_test_123',
        'type' => 'customer.subscription.updated',
        'data' => ['object' => $object],
    ];
}

it('grants pro for an active subscription on the configured pro price', function () {
    $user = User::factory()->create(['stripe_id' => 'cus_test_123']);

    app(PlanProvisioner::class)->handle(subscriptionPayload());

    expect($user->fresh()->plan->isPro())->toBeTrue();
});

it('keeps pro for a subscription that is past due', function () {
    $user = User::factory()->create(['stripe_id' => 'cus_test_123']);

    $pro = Plan::query()->where('name', PlanName::Pro)->firstOrFail();
    $user->forceFill(['plan_id' => $pro->id])->save();

    app(PlanProvisioner::class)->handle(subscriptionPayload(['status' => 'past_due']));

    expect($user->fresh()->plan->isPro())->toBeTrue();
});

it('keeps pro for a subscription that is unpaid', function () {
    $user = User::factory()->create(['stripe_id' => 'cus_test_123']);

    $pro = Plan::query()->where('name', PlanName::Pro)->firstOrFail();
    $user->forceFill(['plan_id' => $pro->id])->save();

    app(PlanProvisioner::class)->handle(subscriptionPayload(['status' => 'unpaid']));

    expect($user->fresh()->plan->isPro())->toBeTrue();
});

it('keeps pro for a trialing subscription', function () {
    $user = User::factory()->create(['stripe_id' => 'cus_test_123']);

    app(PlanProvisioner::class)->handle(subscriptionPayload(['status' => 'trialing']));

    expect($user->fresh()->plan->isPro())->toBeTrue();
});

it('keeps pro for an incomplete subscription', function () {
    $user = User::factory()->create(['stripe_id' => 'cus_test_123']);

    app(PlanProvisioner::class)->handle(subscriptionPayload(['status' => 'incomplete']));

    expect($user->fresh()->plan->isPro())->toBeTrue();
});

it('returns the account to free when the subscription is cancelled', function () {
    $pro = Plan::query()->where('name', PlanName::Pro)->firstOrFail();

    $user = User::factory()->create([
        'stripe_id' => 'cus_test_123',
        'plan_id' => $pro->id,
    ]);

    app(PlanProvisioner::class)->handle(subscriptionPayload(['status' => 'canceled']));

    expect($user->fresh()->plan->isFree())->toBeTrue();
});

it('returns the account to free when the subscription has expired incomplete', function () {
    $pro = Plan::query()->where('name', PlanName::Pro)->firstOrFail();

    $user = User::factory()->create([
        'stripe_id' => 'cus_test_123',
        'plan_id' => $pro->id,
    ]);

    app(PlanProvisioner::class)->handle(subscriptionPayload(['status' => 'incomplete_expired']));

    expect($user->fresh()->plan->isFree())->toBeTrue();
});

it('leaves the plan unchanged when the subscription is paused', function () {
    $pro = Plan::query()->where('name', PlanName::Pro)->firstOrFail();

    $user = User::factory()->create([
        'stripe_id' => 'cus_test_123',
        'plan_id' => $pro->id,
    ]);

    app(PlanProvisioner::class)->handle(subscriptionPayload(['status' => 'paused']));

    expect($user->fresh()->plan->isPro())->toBeTrue();
});

it('leaves the plan unchanged for an unknown subscription status', function () {
    $pro = Plan::query()->where('name', PlanName::Pro)->firstOrFail();

    $user = User::factory()->create([
        'stripe_id' => 'cus_test_123',
        'plan_id' => $pro->id,
    ]);

    app(PlanProvisioner::class)->handle(subscriptionPayload(['status' => 'something_new']));

    expect($user->fresh()->plan->isPro())->toBeTrue();
});

it('never changes the plan for a subscription on another price', function () {
    $user = User::factory()->create(['stripe_id' => 'cus_test_123']);

    $payload = subscriptionPayload();
    $payload['data']['object']['items']['data'][0]['price']['id'] = 'price_someone_else_999';

    app(PlanProvisioner::class)->handle($payload);

    expect($user->fresh()->plan->isFree())->toBeTrue();
});

it('never changes the plan for a subscription on another price that is cancelled', function () {
    $pro = Plan::query()->where('name', PlanName::Pro)->firstOrFail();

    $user = User::factory()->create([
        'stripe_id' => 'cus_test_123',
        'plan_id' => $pro->id,
    ]);

    $payload = subscriptionPayload(['status' => 'canceled']);
    $payload['data']['object']['items']['data'][0]['price']['id'] = 'price_someone_else_999';

    app(PlanProvisioner::class)->handle($payload);

    expect($user->fresh()->plan->isPro())->toBeTrue();
});

it('only changes the plan of the account that owns the stripe customer', function () {
    $owner = User::factory()->create(['stripe_id' => 'cus_test_123']);
    $other = User::factory()->create(['stripe_id' => 'cus_someone_else']);

    app(PlanProvisioner::class)->handle(subscriptionPayload());

    expect($owner->fresh()->plan->isPro())->toBeTrue()
        ->and($other->fresh()->plan->isFree())->toBeTrue();
});

it('ignores a subscription for a customer that is unknown', function () {
    $user = User::factory()->create(['stripe_id' => 'cus_test_123']);

    $payload = subscriptionPayload();
    $payload['data']['object']['customer'] = 'cus_does_not_exist';

    app(PlanProvisioner::class)->handle($payload);

    expect($user->fresh()->plan->isFree())->toBeTrue();
});

it('is idempotent when the same event is delivered more than once', function () {
    $user = User::factory()->create(['stripe_id' => 'cus_test_123']);

    $provisioner = app(PlanProvisioner::class);
    $payload = subscriptionPayload();

    $provisioner->handle($payload);
    $provisioner->handle($payload);
    $provisioner->handle($payload);

    expect($user->fresh()->plan->isPro())->toBeTrue()
        ->and(User::query()->where('stripe_id', 'cus_test_123')->count())->toBe(1);
});

it('does not write anything when the plan is already correct', function () {
    $pro = Plan::query()->where('name', PlanName::Pro)->firstOrFail();

    $user = User::factory()->create([
        'stripe_id' => 'cus_test_123',
        'plan_id' => $pro->id,
    ]);

    $updatedAt = $user->updated_at;

    app(PlanProvisioner::class)->handle(subscriptionPayload());

    expect($user->fresh()->updated_at->equalTo($updatedAt))->toBeTrue();
});

it('ignores a payload with no subscription object', function () {
    $user = User::factory()->create(['stripe_id' => 'cus_test_123']);

    app(PlanProvisioner::class)->handle(['id' => 'evt_1', 'type' => 'customer.subscription.updated']);

    expect($user->fresh()->plan->isFree())->toBeTrue();
});

it('ignores a payload with no customer', function () {
    $user = User::factory()->create(['stripe_id' => 'cus_test_123']);

    $payload = subscriptionPayload();
    unset($payload['data']['object']['customer']);

    app(PlanProvisioner::class)->handle($payload);

    expect($user->fresh()->plan->isFree())->toBeTrue();
});

it('ignores a payload with no price on the subscription', function () {
    $user = User::factory()->create(['stripe_id' => 'cus_test_123']);

    $payload = subscriptionPayload();
    $payload['data']['object']['items'] = ['data' => []];

    app(PlanProvisioner::class)->handle($payload);

    expect($user->fresh()->plan->isFree())->toBeTrue();
});

it('does not change any plan when the pro price is not configured', function () {
    $user = User::factory()->create(['stripe_id' => 'cus_test_123']);

    Plan::query()->where('name', PlanName::Pro)->firstOrFail()
        ->forceFill(['stripe_price_id' => null])->save();

    app(PlanProvisioner::class)->handle(subscriptionPayload());

    expect($user->fresh()->plan->isFree())->toBeTrue();
});

it('returns the owner to free when a deletion is revoked even though the payload status is still active', function () {
    $pro = Plan::query()->where('name', PlanName::Pro)->firstOrFail();

    $user = User::factory()->create([
        'stripe_id' => 'cus_test_123',
        'plan_id' => $pro->id,
    ]);

    // Stripe sends the status the subscription had before the deletion, so
    // the payload can still say active.
    $payload = subscriptionPayload(['status' => 'active']);
    $payload['type'] = 'customer.subscription.deleted';

    app(PlanProvisioner::class)->revoke($payload);

    expect($user->fresh()->plan->isFree())->toBeTrue();
});

it('does not revoke the plan of an account that does not own the subscription', function () {
    $pro = Plan::query()->where('name', PlanName::Pro)->firstOrFail();

    $owner = User::factory()->create([
        'stripe_id' => 'cus_test_123',
        'plan_id' => $pro->id,
    ]);

    $other = User::factory()->create([
        'stripe_id' => 'cus_someone_else',
        'plan_id' => $pro->id,
    ]);

    $payload = subscriptionPayload();
    $payload['type'] = 'customer.subscription.deleted';

    app(PlanProvisioner::class)->revoke($payload);

    expect($owner->fresh()->plan->isFree())->toBeTrue()
        ->and($other->fresh()->plan->isPro())->toBeTrue();
});

it('does not revoke a plan for a deletion on another price', function () {
    $pro = Plan::query()->where('name', PlanName::Pro)->firstOrFail();

    $user = User::factory()->create([
        'stripe_id' => 'cus_test_123',
        'plan_id' => $pro->id,
    ]);

    $payload = subscriptionPayload();
    $payload['type'] = 'customer.subscription.deleted';
    $payload['data']['object']['items']['data'][0]['price']['id'] = 'price_someone_else_999';

    app(PlanProvisioner::class)->revoke($payload);

    expect($user->fresh()->plan->isPro())->toBeTrue();
});

it('resolves the pro plan by the backed enum in a query', function () {
    $pro = Plan::query()->where('name', PlanName::Pro)->first();

    expect($pro)->not->toBeNull()
        ->and($pro->isPro())->toBeTrue()
        ->and($pro->stripePriceId())->toBe('price_pro_test_123');
});
