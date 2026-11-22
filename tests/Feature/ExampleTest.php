<?php

use App\Models\User;

/*
|--------------------------------------------------------------------------
| Root Redirect
|--------------------------------------------------------------------------
|
| The root path sends visitors to the dashboard when they are signed in and
| to the login screen when they are not.
|
*/

test('guests are redirected to the login page from the root path', function () {
    $this->get('/')->assertRedirect(route('login'));
});

test('authenticated users are redirected to the dashboard from the root path', function () {
    seedPlans();

    $user = User::factory()->create();

    $this->actingAs($user)->get('/')->assertRedirect(route('dashboard.overview'));
});
