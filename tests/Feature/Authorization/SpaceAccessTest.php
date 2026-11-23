<?php

use App\Models\Space;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Another account
|--------------------------------------------------------------------------
|
| A space belongs to the account that created it. A space of another
| account is reported as missing rather than as forbidden, so that the
| response does not reveal that the id belongs to a real space.
|
*/

beforeEach(function () {
    seedPlans();
});

test('another account cannot open the space page', function () {
    $owner = User::factory()->create(['name' => 'Space Owner']);
    $other = User::factory()->create(['name' => 'Foreign Owner']);

    $space = Space::factory()->for($owner)->create(['name' => 'Owned Space']);

    $this->actingAs($other)
        ->get(route('dashboard.spaces.show', $space))
        ->assertNotFound();
});

test('another account cannot open the space created page', function () {
    $owner = User::factory()->create(['name' => 'Space Owner']);
    $other = User::factory()->create(['name' => 'Foreign Owner']);

    $space = Space::factory()->for($owner)->create(['name' => 'Owned Space']);

    // The created page carries the public share URL of the space, so it
    // must not be reachable by an account that does not own the space.
    $this->actingAs($other)
        ->get(route('dashboard.spaces.created', $space))
        ->assertNotFound();
});

test('another account cannot open the edit form of the space', function () {
    $owner = User::factory()->create(['name' => 'Space Owner']);
    $other = User::factory()->create(['name' => 'Foreign Owner']);

    $space = Space::factory()->for($owner)->create(['name' => 'Owned Space']);

    $this->actingAs($other)
        ->get(route('dashboard.spaces.edit', $space))
        ->assertNotFound();
});

test('another account cannot update the space', function () {
    $owner = User::factory()->create(['name' => 'Space Owner']);
    $other = User::factory()->create(['name' => 'Foreign Owner']);

    $space = Space::factory()->for($owner)->create(['name' => 'Owned Space']);

    $this->actingAs($other)
        ->put(route('dashboard.spaces.update', $space), [
            'name' => 'A hijacked name',
        ])
        ->assertNotFound();

    expect($space->refresh()->name)->toBe('Owned Space');
});

test('another account cannot update the space even with an invalid payload', function () {
    $owner = User::factory()->create(['name' => 'Space Owner']);
    $other = User::factory()->create(['name' => 'Foreign Owner']);

    $space = Space::factory()->for($owner)->create(['name' => 'Owned Space']);

    // The ownership check runs before the payload is validated, so a
    // payload that would fail validation cannot be used to tell a real
    // space from a space that does not exist.
    $this->actingAs($other)
        ->put(route('dashboard.spaces.update', $space), [
            'name' => '',
        ])
        ->assertNotFound();

    $this->actingAs($other)
        ->put(route('dashboard.spaces.update', 999999), [
            'name' => '',
        ])
        ->assertNotFound();
});

test('another account cannot delete the space', function () {
    $owner = User::factory()->create(['name' => 'Space Owner']);
    $other = User::factory()->create(['name' => 'Foreign Owner']);

    $space = Space::factory()->for($owner)->create(['name' => 'Owned Space']);

    $this->actingAs($other)
        ->delete(route('dashboard.spaces.destroy', $space))
        ->assertNotFound();

    expect($space->fresh()->trashed())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| The owner's own space
|--------------------------------------------------------------------------
*/

test('the owner can open the space page', function () {
    $owner = User::factory()->create();

    $space = Space::factory()->for($owner)->create();

    $this->actingAs($owner)
        ->get(route('dashboard.spaces.show', $space))
        ->assertOk();
});

test('the owner can open the space created page', function () {
    $owner = User::factory()->create();

    $space = Space::factory()->for($owner)->create();

    $this->actingAs($owner)
        ->get(route('dashboard.spaces.created', $space))
        ->assertOk();
});

/*
|--------------------------------------------------------------------------
| A missing space
|--------------------------------------------------------------------------
*/

test('opening a space that does not exist is not found', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard.spaces.show', 999999))
        ->assertNotFound();
});

test('opening the created page of a space that does not exist is not found', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard.spaces.created', 999999))
        ->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| The space list
|--------------------------------------------------------------------------
*/

test('the space list does not show the spaces of another account', function () {
    $owner = User::factory()->create(['name' => 'Space Owner']);
    $other = User::factory()->create(['name' => 'Foreign Owner']);

    Space::factory()->for($owner)->create(['name' => 'Owned Space']);
    Space::factory()->for($other)->create(['name' => 'Foreign Space']);

    $this->actingAs($other)
        ->get(route('dashboard.spaces.index'))
        ->assertOk()
        ->assertSee('Foreign Space')
        ->assertDontSee('Owned Space');
});
