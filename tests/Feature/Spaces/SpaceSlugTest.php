<?php

use App\Models\Space;
use App\Models\User;

beforeEach(function () {
    seedPlans();
});

test('a slug is generated from the name', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('dashboard.spaces.store'), validSpacePayload([
        'name' => 'Acme Reviews',
    ]));

    expect(Space::firstOrFail()->slug)->toBe('acme-reviews');
});

test('a submitted slug is ignored', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('dashboard.spaces.store'), validSpacePayload([
        'name' => 'Acme Reviews',
        'slug' => 'forged-slug',
    ]));

    expect(Space::firstOrFail()->slug)->toBe('acme-reviews');
});

test('a name that produces no slug still gets one', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('dashboard.spaces.store'), validSpacePayload([
        'name' => '!!!',
    ]));

    expect(Space::firstOrFail()->slug)->not->toBeEmpty();
});

test('a colliding slug is made unique', function () {
    $user = User::factory()->create();

    Space::factory()->for($user)->create(['slug' => 'acme-reviews']);

    $this->actingAs($user)->post(route('dashboard.spaces.store'), validSpacePayload([
        'name' => 'Acme Reviews',
    ]));

    $slugs = Space::pluck('slug')->all();

    expect($slugs)->toHaveCount(2)
        ->and(array_unique($slugs))->toHaveCount(2)
        ->and($slugs)->toContain('acme-reviews');
});

test('the slug of a deleted space is not reused', function () {
    $user = User::factory()->create();

    $space = Space::factory()->for($user)->create(['slug' => 'taken-slug']);

    $this->actingAs($user)->delete(route('dashboard.spaces.destroy', $space));

    $this->actingAs($user)->post(route('dashboard.spaces.store'), validSpacePayload([
        'name' => 'Taken Slug',
    ]));

    expect(Space::withTrashed()->where('slug', 'taken-slug')->count())->toBe(1)
        ->and(Space::where('slug', 'taken-slug')->count())->toBe(0);
});

test('the public path is built from the slug', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create(['slug' => 'my-space']);

    expect($space->publicPath())->toBe('/s/my-space');
});

test('the wall path is built from the slug', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create(['slug' => 'my-space']);

    expect($space->wallPath())->toBe('/wall/my-space');
});

test('a slug is generated for every space in the account', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('dashboard.spaces.store'), validSpacePayload([
        'name' => 'First Space',
    ]));

    $this->actingAs($user)->post(route('dashboard.spaces.store'), validSpacePayload([
        'name' => 'Second Space',
    ]));

    expect(Space::pluck('slug')->all())->toBe(['first-space', 'second-space']);
});
