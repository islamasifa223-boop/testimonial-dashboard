<?php

use App\Models\Space;
use App\Models\User;

beforeEach(function () {
    seedPlans();
});

dataset('dashboard route names', [
    'overview' => ['dashboard.overview'],
    'spaces' => ['dashboard.spaces.index'],
    'spaces create' => ['dashboard.spaces.create'],
    'inbox' => ['dashboard.inbox'],
    'wall' => ['dashboard.wall'],
    'widgets' => ['dashboard.widgets'],
    'billing' => ['dashboard.billing'],
    'settings' => ['dashboard.settings'],
]);

test('guests are redirected to the login page', function (string $routeName) {
    $this->get(route($routeName))->assertRedirect(route('login'));
})->with('dashboard route names');

test('authenticated users can reach the dashboard', function (string $routeName) {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route($routeName))->assertOk();
})->with('dashboard route names');

/*
|--------------------------------------------------------------------------
| Routes that need a record
|--------------------------------------------------------------------------
|
| The remaining dashboard routes carry a record in their url, so a
| placeholder record is created to reach them.
|
*/

test('guests are redirected away from the space pages', function (string $routeName) {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    $this->get(route($routeName, $space))->assertRedirect(route('login'));
})->with([
    'spaces created' => ['dashboard.spaces.created'],
    'spaces show' => ['dashboard.spaces.show'],
    'spaces edit' => ['dashboard.spaces.edit'],
]);

test('authenticated users can reach the space pages', function (string $routeName) {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    $this->actingAs($user)->get(route($routeName, $space))->assertOk();
})->with([
    'spaces created' => ['dashboard.spaces.created'],
    'spaces show' => ['dashboard.spaces.show'],
    'spaces edit' => ['dashboard.spaces.edit'],
]);

/*
|--------------------------------------------------------------------------
| Routes that change state
|--------------------------------------------------------------------------
*/

test('guests are redirected away from the state changing routes', function (string $routeName) {
    $this->post(route($routeName))->assertRedirect(route('login'));
})->with([
    'billing upgrade' => ['dashboard.billing.upgrade'],
]);

test('guests are redirected away from the billing portal', function () {
    $this->get(route('dashboard.billing.portal'))->assertRedirect(route('login'));
});

test('guests are redirected away from the profile update', function () {
    $this->patch(route('dashboard.settings.profile.update'), [
        'name' => 'A name',
    ])->assertRedirect(route('login'));
});

test('guests are redirected away from the password update', function () {
    $this->put(route('dashboard.settings.password.update'), [
        'current_password' => 'password',
        'password' => 'a-new-password',
        'password_confirmation' => 'a-new-password',
    ])->assertRedirect(route('login'));
});

test('guests are redirected away from the logout route', function () {
    $this->post(route('logout'))->assertRedirect(route('login'));
});

test('guests can reach the login and registration pages', function () {
    $this->get(route('login'))->assertOk();

    $this->get(route('register'))->assertOk();
});

test('authenticated users are redirected away from the login page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('login'))->assertRedirect();
});

test('authenticated users are redirected away from the registration page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('register'))->assertRedirect();
});
