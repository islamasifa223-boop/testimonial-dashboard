<?php

use App\Models\Space;
use App\Models\User;
use App\Policies\SpacePolicy;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    seedPlans();
});

test('an owner is allowed to view their own space', function () {
    $owner = User::factory()->create();
    $space = Space::factory()->for($owner)->create();

    $policy = app(SpacePolicy::class);

    expect($policy->view($owner, $space))->toBeTrue();
});

test('an owner is allowed to update their own space', function () {
    $owner = User::factory()->create();
    $space = Space::factory()->for($owner)->create();

    $policy = app(SpacePolicy::class);

    expect($policy->update($owner, $space))->toBeTrue();
});

test('an owner is allowed to delete their own space', function () {
    $owner = User::factory()->create();
    $space = Space::factory()->for($owner)->create();

    $policy = app(SpacePolicy::class);

    expect($policy->delete($owner, $space))->toBeTrue();
});

test('another user is not allowed to view the space', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $space = Space::factory()->for($owner)->create();

    $policy = app(SpacePolicy::class);

    expect($policy->view($other, $space))->toBeFalse();
});

test('another user is not allowed to update the space', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $space = Space::factory()->for($owner)->create();

    $policy = app(SpacePolicy::class);

    expect($policy->update($other, $space))->toBeFalse();
});

test('another user is not allowed to delete the space', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $space = Space::factory()->for($owner)->create();

    $policy = app(SpacePolicy::class);

    expect($policy->delete($other, $space))->toBeFalse();
});

test('a space belongs to the account that created it', function () {
    $owner = User::factory()->create();
    $space = Space::factory()->for($owner)->create();

    expect($space->user_id)->toBe($owner->id)
        ->and($space->user->id)->toBe($owner->id);
});

test('the space policy is registered for the space model', function () {
    $owner = User::factory()->create();
    $space = Space::factory()->for($owner)->create();

    expect(Gate::forUser($owner)->allows('view', $space))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('update', $space))->toBeTrue()
        ->and(Gate::forUser($owner)->allows('delete', $space))->toBeTrue();
});

test('the space policy denies another account', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $space = Space::factory()->for($owner)->create();

    expect(Gate::forUser($other)->denies('view', $space))->toBeTrue()
        ->and(Gate::forUser($other)->denies('update', $space))->toBeTrue()
        ->and(Gate::forUser($other)->denies('delete', $space))->toBeTrue();
});
