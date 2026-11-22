<?php

use App\Models\Space;
use App\Models\User;

beforeEach(function () {
    seedPlans();
});

test('the name key is stored for a space', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('dashboard.spaces.store'), validSpacePayload([
        'name' => 'Ducimus Quae',
    ]));

    $space = Space::firstOrFail();

    // The key is the case-folded form of the name, so "Ducimus Quae"
    // and "ducimus quae" share a single key.
    expect($space->name_key)->toBe('ducimus quae');
});

test('the name key is never empty', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('dashboard.spaces.store'), validSpacePayload([
        'name' => 'Ducimus Quae',
    ]));

    $space = Space::firstOrFail();

    expect($space->name_key)->not->toBe('');
});

test('the name key is valid UTF-8', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('dashboard.spaces.store'), validSpacePayload([
        'name' => 'Ducimus Quae',
    ]));

    $space = Space::firstOrFail();

    expect(mb_check_encoding($space->name_key, 'UTF-8'))->toBeTrue();
});
