<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    seedPlans();
});

test('the registration screen can be rendered', function () {
    $this->get(route('register'))->assertOk();
});

test('a new account can be registered', function () {
    Event::fake([Registered::class]);

    $response = $this->post(route('register'), [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'password-123',
        'password_confirmation' => 'password-123',
    ]);

    $response->assertRedirect(route('dashboard.overview'));

    $user = User::query()->where('email', 'ada@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Ada Lovelace')
        ->and(Hash::check('password-123', $user->password))->toBeTrue();

    Event::assertDispatched(Registered::class);
});

test('a newly registered user is signed in', function () {
    $this->post(route('register'), [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'password-123',
        'password_confirmation' => 'password-123',
    ]);

    $this->assertAuthenticated();
});

test('a newly registered account is not marked as email verified', function () {
    $this->post(route('register'), [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'password-123',
        'password_confirmation' => 'password-123',
    ]);

    $user = User::query()->where('email', 'ada@example.com')->first();

    expect($user->email_verified_at)->toBeNull();
});

test('a newly registered account can reach the dashboard immediately', function () {
    $this->post(route('register'), [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'password-123',
        'password_confirmation' => 'password-123',
    ]);

    $this->assertAuthenticated();

    $this->get(route('dashboard.overview'))->assertOk();
});

test('a newly registered account is placed on the free plan', function () {
    $this->post(route('register'), [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'password-123',
        'password_confirmation' => 'password-123',
    ]);

    $user = User::query()->where('email', 'ada@example.com')->first();

    expect($user->plan)->not->toBeNull()
        ->and($user->onFreePlan())->toBeTrue()
        ->and($user->onProPlan())->toBeFalse();
});

test('registration fails when the email is already taken', function () {
    User::factory()->create(['email' => 'ada@example.com']);

    $response = $this->post(route('register'), [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'password-123',
        'password_confirmation' => 'password-123',
    ]);

    $response->assertSessionHasErrors('email');

    expect(User::query()->where('email', 'ada@example.com')->count())->toBe(1);
});

test('registration fails when the password is not confirmed', function () {
    $response = $this->post(route('register'), [
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'password' => 'password-123',
        'password_confirmation' => 'different-password',
    ]);

    $response->assertSessionHasErrors('password');

    expect(User::query()->count())->toBe(0);
});

test('registration fails when required fields are missing', function () {
    $response = $this->post(route('register'), []);

    $response->assertSessionHasErrors(['name', 'email', 'password']);

    expect(User::query()->count())->toBe(0);
});

test('registration fails when the email is invalid', function () {
    $response = $this->post(route('register'), [
        'name' => 'Ada Lovelace',
        'email' => 'not-an-email',
        'password' => 'password-123',
        'password_confirmation' => 'password-123',
    ]);

    $response->assertSessionHasErrors('email');

    expect(User::query()->count())->toBe(0);
});

test('authenticated users are redirected away from the registration screen', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('register'))->assertRedirect();
});
