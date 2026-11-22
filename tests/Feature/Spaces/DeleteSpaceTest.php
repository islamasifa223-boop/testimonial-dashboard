<?php

use App\Models\Space;
use App\Models\User;

beforeEach(function () {
    seedPlans();
});

test('a space can be deleted by its owner', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    $this->actingAs($user)
        ->delete(route('dashboard.spaces.destroy', $space))
        ->assertRedirect(route('dashboard.spaces.index'));

    expect(Space::count())->toBe(0);
});

test('the deleted space is soft deleted', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    $this->actingAs($user)->delete(route('dashboard.spaces.destroy', $space));

    expect(Space::withTrashed()->count())->toBe(1)
        ->and(Space::withTrashed()->firstOrFail()->deleted_at)->not->toBeNull();
});

test('the deleted space no longer appears on the index', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    $this->actingAs($user)->delete(route('dashboard.spaces.destroy', $space));

    $this->actingAs($user)
        ->get(route('dashboard.spaces.index'))
        ->assertOk()
        ->assertDontSee($space->name);
});

test('another account cannot delete a space', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $space = Space::factory()->for($owner)->create();

    $this->actingAs($other)
        ->delete(route('dashboard.spaces.destroy', $space))
        ->assertNotFound();

    expect(Space::count())->toBe(1);
});

test('the slug of a deleted space stays reserved', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create(['slug' => 'reserved-slug']);

    $this->actingAs($user)->delete(route('dashboard.spaces.destroy', $space));

    // The row still exists, so the slug cannot be handed out again.
    expect(Space::withTrashed()->where('slug', 'reserved-slug')->count())->toBe(1);
});

test('a deleted space cannot be shown', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    $this->actingAs($user)->delete(route('dashboard.spaces.destroy', $space));

    $this->actingAs($user)
        ->get(route('dashboard.spaces.show', $space->id))
        ->assertNotFound();
});

test('deleting a space frees a slot on the plan', function () {
    $user = User::factory()->create();

    Space::factory()->count(3)->for($user)->create();

    $space = Space::first();

    $this->actingAs($user)->delete(route('dashboard.spaces.destroy', $space));

    $this->actingAs($user)
        ->post(route('dashboard.spaces.store'), validSpacePayload())
        ->assertRedirect(route('dashboard.spaces.created', Space::query()->latest('id')->firstOrFail()));
});
