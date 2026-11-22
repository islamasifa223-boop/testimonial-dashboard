<?php

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    seedPlans();
});

test('the login screen can be rendered', function () {
    $this->get(route('login'))->assertOk();
});

test('a user can sign in with valid credentials', function () {
    $user = User::factory()->create(['email' => 'ada@example.com']);

    $response = $this->post(route('login'), [
        'email' => 'ada@example.com',
        'password' => 'password',
    ]);

    $response->assertRedirect(route('dashboard.overview'));

    $this->assertAuthenticatedAs($user);
});

test('the session is regenerated after signing in', function () {
    User::factory()->create(['email' => 'ada@example.com']);

    $originalSessionId = session()->getId();

    $this->post(route('login'), [
        'email' => 'ada@example.com',
        'password' => 'password',
    ]);

    expect(session()->getId())->not->toBe($originalSessionId);

    $this->assertAuthenticated();
});

test('a user can sign in with the remember me option', function () {
    $user = User::factory()->create(['email' => 'ada@example.com']);

    $response = $this->post(route('login'), [
        'email' => 'ada@example.com',
        'password' => 'password',
        'remember' => '1',
    ]);

    $response->assertRedirect(route('dashboard.overview'));

    expect($user->fresh()->remember_token)->not->toBeNull();
});

test('signing in fails with an incorrect password', function () {
    User::factory()->create(['email' => 'ada@example.com']);

    $response = $this->post(route('login'), [
        'email' => 'ada@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('signing in fails with an unknown email', function () {
    $response = $this->post(route('login'), [
        'email' => 'nobody@example.com',
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('signing in requires an email and a password', function () {
    $response = $this->post(route('login'), []);

    $response->assertSessionHasErrors(['email', 'password']);

    $this->assertGuest();
});

test('repeated failed attempts lock the account out', function () {
    Event::fake([Lockout::class]);

    $user = User::factory()->create(['email' => 'ada@example.com']);

    foreach (range(1, 5) as $attempt) {
        $this->post(route('login'), [
            'email' => 'ada@example.com',
            'password' => 'wrong-password',
        ]);
    }

    $response = $this->post(route('login'), [
        'email' => 'ada@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors('email');

    Event::assertDispatched(Lockout::class);

    expect($user)->toBeInstanceOf(User::class);
});

test('a user can sign out', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('login'));

    $this->assertGuest();
});

test('a signed in user is redirected away from the login screen', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('login'))->assertRedirect();
});

test('the stored password is hashed and never stored in plain text', function () {
    $user = User::factory()->create(['email' => 'ada@example.com']);

    expect($user->password)->not->toBe('password')
        ->and(Hash::check('password', $user->password))->toBeTrue();
});
