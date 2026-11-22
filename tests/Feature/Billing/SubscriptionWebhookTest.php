<?php

use App\Enums\PlanName;
use App\Models\Plan;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Testing\TestResponse;
use Laravel\Cashier\Cashier;
use Stripe\WebhookSignature;

beforeEach(function () {
    $plans = seedPlans();

    $this->proPriceId = 'price_pro_test_123';

    $plans['pro']->forceFill(['stripe_price_id' => $this->proPriceId])->save();

    $this->secret = config('cashier.webhook.secret');
});

/**
 * Build a Stripe subscription payload.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function webhookSubscriptionPayload(array $overrides = []): array
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

/**
 * POST a payload to the webhook route with a valid Stripe signature.
 *
 * @param  array<string, mixed>  $payload
 */
function postSignedWebhook(array $payload, string $secret, ?string $overrideSignature = null): TestResponse
{
    $body = json_encode($payload);

    $signature = $overrideSignature ?? WebhookSignature::generateSignatureHeader($body, $secret);

    // The signature is computed over the exact raw body Stripe sends, so the
    // request has to carry that body verbatim rather than form parameters.
    return test()->call('POST', '/stripe/webhook', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => $signature,
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
    ], $body);
}

it('accepts a signed webhook and grants pro', function () {
    $user = User::factory()->create(['stripe_id' => 'cus_test_123']);

    $response = postSignedWebhook(webhookSubscriptionPayload(), $this->secret);

    $response->assertOk();

    expect($user->fresh()->plan->isPro())->toBeTrue();
});

it('rejects a webhook with an invalid signature', function () {
    $user = User::factory()->create(['stripe_id' => 'cus_test_123']);

    $response = postSignedWebhook(
        webhookSubscriptionPayload(),
        $this->secret,
        't=1234567890,v1=deadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeef'
    );

    $response->assertForbidden();

    expect($user->fresh()->plan->isFree())->toBeTrue();
});

it('rejects a webhook with no signature at all', function () {
    $user = User::factory()->create(['stripe_id' => 'cus_test_123']);

    $body = json_encode(webhookSubscriptionPayload());

    $response = test()->call('POST', '/stripe/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
    ], $body);

    $response->assertForbidden();

    expect($user->fresh()->plan->isFree())->toBeTrue();
});

it('rejects a webhook signed with the wrong secret', function () {
    $user = User::factory()->create(['stripe_id' => 'cus_test_123']);

    $response = postSignedWebhook(webhookSubscriptionPayload(), 'whsec_a_different_secret');

    $response->assertForbidden();

    expect($user->fresh()->plan->isFree())->toBeTrue();
});

it('returns the account to free when a signed deletion arrives with an active status', function () {
    $pro = Plan::query()->where('name', PlanName::Pro)->firstOrFail();

    $user = User::factory()->create([
        'stripe_id' => 'cus_test_123',
        'plan_id' => $pro->id,
    ]);

    $payload = webhookSubscriptionPayload(['status' => 'active']);
    $payload['type'] = 'customer.subscription.deleted';

    $response = postSignedWebhook($payload, $this->secret);

    $response->assertOk();

    expect($user->fresh()->plan->isFree())->toBeTrue();
});

it('ignores a signed event that is not about a subscription', function () {
    $user = User::factory()->create(['stripe_id' => 'cus_test_123']);

    $payload = webhookSubscriptionPayload();
    $payload['type'] = 'customer.updated';

    $response = postSignedWebhook($payload, $this->secret);

    $response->assertOk();

    expect($user->fresh()->plan->isFree())->toBeTrue();
});

it('does not change the plan for a signed subscription on another price', function () {
    $user = User::factory()->create(['stripe_id' => 'cus_test_123']);

    $payload = webhookSubscriptionPayload();
    $payload['data']['object']['items']['data'][0]['price']['id'] = 'price_someone_else_999';

    $response = postSignedWebhook($payload, $this->secret);

    $response->assertOk();

    expect($user->fresh()->plan->isFree())->toBeTrue();
});

it('keeps pro when a signed past due event arrives', function () {
    $pro = Plan::query()->where('name', PlanName::Pro)->firstOrFail();

    $user = User::factory()->create([
        'stripe_id' => 'cus_test_123',
        'plan_id' => $pro->id,
    ]);

    $response = postSignedWebhook(webhookSubscriptionPayload(['status' => 'past_due']), $this->secret);

    $response->assertOk();

    expect($user->fresh()->plan->isPro())->toBeTrue();
});

it('is safe when the same signed event is delivered repeatedly', function () {
    $user = User::factory()->create(['stripe_id' => 'cus_test_123']);

    $payload = webhookSubscriptionPayload();

    postSignedWebhook($payload, $this->secret)->assertOk();
    postSignedWebhook($payload, $this->secret)->assertOk();
    postSignedWebhook($payload, $this->secret)->assertOk();

    expect($user->fresh()->plan->isPro())->toBeTrue()
        ->and(User::query()->where('stripe_id', 'cus_test_123')->count())->toBe(1);
});

it('registers the webhook route only when a webhook secret is configured', function () {
    // The application boots with a secret configured, so the route exists.
    $route = collect(app('router')->getRoutes()->getRoutes())
        ->first(fn ($r) => $r->getName() === 'cashier.webhook');

    expect($route)->not->toBeNull();

    // Without a secret the provider disables Cashier's routes, which is what
    // makes the endpoint fail closed.
    config(['cashier.webhook.secret' => null]);

    $provider = new AppServiceProvider(app());

    $method = new ReflectionMethod($provider, 'configureWebhookRoute');
    $method->setAccessible(true);
    $method->invoke($provider);

    expect(Cashier::$registersRoutes)->toBeFalse();

    // Leave the static in the state the running application booted with so
    // the next test starts from a known baseline.
    config(['cashier.webhook.secret' => $this->secret]);

    $method->invoke($provider);

    expect(Cashier::$registersRoutes)->toBeTrue();
});

it('keeps the webhook route registered when a secret is configured', function () {
    config(['cashier.webhook.secret' => 'whsec_another_test_secret']);

    $provider = new AppServiceProvider(app());

    $method = new ReflectionMethod($provider, 'configureWebhookRoute');
    $method->setAccessible(true);
    $method->invoke($provider);

    expect(Cashier::$registersRoutes)->toBeTrue();
});
