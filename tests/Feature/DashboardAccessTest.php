<?php

use App\Models\User;

beforeEach(function () {
    seedPlans();
});

dataset('dashboard route names', [
    'overview' => ['dashboard.overview'],
    'spaces' => ['dashboard.spaces.index'],
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
