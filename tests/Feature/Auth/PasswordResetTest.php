<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

beforeEach(function () {
    seedPlans();
});

test('the forgot password screen can be rendered', function () {
    $this->get(route('password.request'))->assertOk();
});

test('a reset link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'ada@example.com']);

    $response = $this->post(route('password.email'), ['email' => 'ada@example.com']);

    $response->assertSessionHas('status');
    $response->assertSessionHasNoErrors();

    Notification::assertSentTo($user, ResetPassword::class);
});

test('requesting a reset link requires an email address', function () {
    $response = $this->post(route('password.email'), []);

    $response->assertSessionHasErrors('email');
});

test('requesting a reset link validates the email format', function () {
    $response = $this->post(route('password.email'), ['email' => 'not-an-email']);

    $response->assertSessionHasErrors('email');
});

test('the reset password screen can be rendered with a token', function () {
    $this->get(route('password.reset', ['token' => 'a-token']))->assertOk();
});

test('a password can be reset with a valid token', function () {
    $user = User::factory()->create(['email' => 'ada@example.com']);

    $token = Password::broker()->createToken($user);

    $response = $this->post(route('password.store'), [
        'token' => $token,
        'email' => 'ada@example.com',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ]);

    $response->assertRedirect(route('login'));
    $response->assertSessionHas('status');

    expect(Hash::check('new-password-123', $user->fresh()->password))->toBeTrue();
});

test('a password reset fails with an invalid token', function () {
    $user = User::factory()->create(['email' => 'ada@example.com']);

    $response = $this->post(route('password.store'), [
        'token' => 'not-a-real-token',
        'email' => 'ada@example.com',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ]);

    $response->assertSessionHasErrors('email');

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

test('a password reset requires a confirmed password', function () {
    $user = User::factory()->create(['email' => 'ada@example.com']);

    $token = Password::broker()->createToken($user);

    $response = $this->post(route('password.store'), [
        'token' => $token,
        'email' => 'ada@example.com',
        'password' => 'new-password-123',
        'password_confirmation' => 'a-different-password',
    ]);

    $response->assertSessionHasErrors('password');

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

test('a password reset requires a token and an email address', function () {
    $response = $this->post(route('password.store'), []);

    $response->assertSessionHasErrors(['token', 'email', 'password']);
});

test('the user can sign in with the new password after a reset', function () {
    $user = User::factory()->create(['email' => 'ada@example.com']);

    $token = Password::broker()->createToken($user);

    $this->post(route('password.store'), [
        'token' => $token,
        'email' => 'ada@example.com',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ]);

    $this->post(route('login'), [
        'email' => 'ada@example.com',
        'password' => 'new-password-123',
    ]);

    $this->assertAuthenticatedAs($user->fresh());
});
