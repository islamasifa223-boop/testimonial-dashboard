<?php

use App\Http\Requests\Public\StoreTestimonialRequest;
use App\Models\Plan;
use App\Models\Space;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/*
|--------------------------------------------------------------------------
| Plan helpers
|--------------------------------------------------------------------------
|
| The test database is migrated but never seeded, so the plan rows the
| application relies on have to be created explicitly. Every Feature test
| that creates a user calls seedPlans() from its beforeEach() hook.
|
*/

function seedPlans(): array
{
    return [
        'free' => Plan::factory()->free()->create(),
        'pro' => Plan::factory()->pro()->create(),
    ];
}

/*
|--------------------------------------------------------------------------
| Space form helpers
|--------------------------------------------------------------------------
|
| The payload of a valid space form. It lives here rather than in the
| test file that uses it most, because several test files build a space
| this way and a helper defined in one of them is not available when
| another of them is run on its own.
|
*/

/**
 * A valid payload for the space form.
 *
 * @return array<string, mixed>
 */
function validSpacePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Acme Reviews',
        'title' => 'Share your experience with Acme',
        'subtitle' => 'It only takes a minute.',
        'request' => 'Tell us how we did.',
        'theme' => 'modern-dark',
        'rating_enabled' => 1,
        'company_name_enabled' => 1,
        'company_name_required' => 0,
        'social_url_enabled' => 0,
        'social_url_required' => 0,
        'photo_enabled' => 1,
        'photo_required' => 0,
        'field_order' => ['name', 'email', 'company_name', 'social_url', 'photo'],
    ], $overrides);
}

/*
|--------------------------------------------------------------------------
| Submission token helpers
|--------------------------------------------------------------------------
|
| A public submission carries an encrypted token that proves the request
| came from a rendered form. The helpers below build tokens that the guard
| accepts, so a test can focus on the behaviour it is actually about.
|
*/

/**
 * Build a token that the submission guard accepts.
 *
 * The guard rejects a token that was issued less than three seconds ago, so
 * the issue time is moved back by default. A test that wants to exercise the
 * minimum-age rule overrides the issue time itself.
 */
function validToken(Space $space, array $overrides = []): string
{
    $payload = array_merge([
        'iat' => now()->getTimestamp() - 10,
        'nonce' => Str::random(40),
        'sid' => $space->id,
    ], $overrides);

    return Crypt::encryptString(json_encode($payload) ?: '');
}

/**
 * Build an encrypted payload for tests that need the raw structure.
 */
function validPayload(array $overrides = []): string
{
    $payload = array_merge([
        'iat' => now()->getTimestamp() - 10,
        'nonce' => Str::random(40),
        'sid' => 0,
    ], $overrides);

    return Crypt::encryptString(json_encode($payload) ?: '');
}

/**
 * Build a full set of submission fields for a space.
 *
 * A token is generated for the space unless the caller supplies one, so a
 * test that wants to send a broken or foreign token can override it.
 */
function validSubmission(Space $space, array $overrides = []): array
{
    $fields = array_merge([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'body' => 'A wonderfully ordinary testimonial.',
    ], $overrides);

    $fields[StoreTestimonialRequest::TOKEN_ATTRIBUTE] ??= validToken($space);

    return $fields;
}

/*
|--------------------------------------------------------------------------
| Event listener helpers
|--------------------------------------------------------------------------
|
| A test that registers a model event listener has to remove it again, so
| a later test is not affected by a listener that throws.
|
*/

/**
 * Remove a single model event listener.
 *
 * The dispatcher keeps the raw listeners for an event in the order they
 * were registered, so the listener is located by identity. When the
 * listener is not registered at all, the dispatcher is left untouched.
 */
function removeModelEventListener(string $event, string $modelClass, Closure $listener): void
{
    $dispatcher = $modelClass::getEventDispatcher();

    if ($dispatcher === null) {
        return;
    }

    $name = "eloquent.{$event}: {$modelClass}";

    $listeners = $dispatcher->getRawListeners()[$name] ?? [];

    if (! in_array($listener, $listeners, true)) {
        return;
    }

    $remaining = [];

    foreach ($listeners as $registered) {
        if ($registered !== $listener) {
            $remaining[] = $registered;
        }
    }

    if ($remaining === []) {
        $dispatcher->forget($name);

        return;
    }

    foreach ($remaining as $registered) {
        $dispatcher->listen($name, $registered);
    }
}
