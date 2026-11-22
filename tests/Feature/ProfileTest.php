<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    seedPlans();
});

test('the settings screen can be rendered', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard.settings'))->assertOk();
});

test('the profile information can be updated', function () {
    $user = User::factory()->create([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
    ]);

    $response = $this->actingAs($user)->patch(route('dashboard.settings.profile.update'), [
        'name' => 'Grace Hopper',
        'email' => 'grace@example.com',
    ]);

    $response->assertSessionHas('status');
    $response->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->name)->toBe('Grace Hopper')
        ->and($user->email)->toBe('grace@example.com');
});

test('the email address can be left unchanged', function () {
    $user = User::factory()->create([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
    ]);

    $this->actingAs($user)->patch(route('dashboard.settings.profile.update'), [
        'name' => 'Grace Hopper',
        'email' => 'ada@example.com',
    ])->assertSessionHasNoErrors();

    expect($user->fresh()->email)->toBe('ada@example.com');
});

test('the email address must be unique across accounts', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $user = User::factory()->create(['email' => 'ada@example.com']);

    $response = $this->actingAs($user)->patch(route('dashboard.settings.profile.update'), [
        'name' => 'Grace Hopper',
        'email' => 'taken@example.com',
    ]);

    $response->assertSessionHasErrors('email');

    expect($user->fresh()->email)->toBe('ada@example.com');
});

test('the profile information requires a name and an email address', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->patch(route('dashboard.settings.profile.update'), []);

    $response->assertSessionHasErrors(['name', 'email']);
});

test('the email address must be a valid email address', function () {
    $user = User::factory()->create(['email' => 'ada@example.com']);

    $response = $this->actingAs($user)->patch(route('dashboard.settings.profile.update'), [
        'name' => 'Grace Hopper',
        'email' => 'not-an-email',
    ]);

    $response->assertSessionHasErrors('email');

    expect($user->fresh()->email)->toBe('ada@example.com');
});

test('the password can be changed', function () {
    $user = User::factory()->create(['email' => 'ada@example.com']);

    $response = $this->actingAs($user)->put(route('dashboard.settings.password.update'), [
        'current_password' => 'password',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ]);

    $response->assertSessionHas('status');
    $response->assertSessionHasNoErrors();

    expect(Hash::check('new-password-123', $user->fresh()->password))->toBeTrue();
});

test('changing the password requires the correct current password', function () {
    $user = User::factory()->create(['email' => 'ada@example.com']);

    $response = $this->actingAs($user)->put(route('dashboard.settings.password.update'), [
        'current_password' => 'not-the-current-password',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ]);

    $response->assertSessionHasErrorsIn('updatePassword', 'current_password');

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

test('changing the password requires a confirmation', function () {
    $user = User::factory()->create(['email' => 'ada@example.com']);

    $response = $this->actingAs($user)->put(route('dashboard.settings.password.update'), [
        'current_password' => 'password',
        'password' => 'new-password-123',
        'password_confirmation' => 'a-different-password',
    ]);

    $response->assertSessionHasErrorsIn('updatePassword', 'password');

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

test('the current password is required to change the password', function () {
    $user = User::factory()->create(['email' => 'ada@example.com']);

    $response = $this->actingAs($user)->put(route('dashboard.settings.password.update'), [
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ]);

    $response->assertSessionHasErrorsIn('updatePassword', 'current_password');
});

test('the old password stops working after the password is changed', function () {
    $user = User::factory()->create(['email' => 'ada@example.com']);

    $this->actingAs($user)->put(route('dashboard.settings.password.update'), [
        'current_password' => 'password',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ]);

    $this->post(route('logout'));

    $this->post(route('login'), [
        'email' => 'ada@example.com',
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();

    $this->post(route('login'), [
        'email' => 'ada@example.com',
        'password' => 'new-password-123',
    ])->assertSessionHasNoErrors();

    $this->assertAuthenticated();
});
