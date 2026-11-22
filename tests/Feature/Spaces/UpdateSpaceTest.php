<?php

use App\Models\Space;
use App\Models\User;

beforeEach(function () {
    seedPlans();
});

test('the edit form can be rendered', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('dashboard.spaces.edit', $space))
        ->assertOk()
        ->assertSee($space->name);
});

test('a space can be updated', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    $this->actingAs($user)
        ->put(route('dashboard.spaces.update', $space), validSpacePayload([
            'name' => 'Renamed Space',
            'title' => 'A new title',
        ]))
        ->assertRedirect(route('dashboard.spaces.show', $space));

    $space->refresh();

    expect($space->name)->toBe('Renamed Space')
        ->and($space->title)->toBe('A new title');
});

test('the slug is not changed by an update', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    $original = $space->slug;

    $this->actingAs($user)->put(route('dashboard.spaces.update', $space), validSpacePayload([
        'name' => 'Renamed Space',
        'slug' => 'forged-slug',
    ]));

    expect($space->refresh()->slug)->toBe($original);
});

test('the field configuration is updated', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    $this->actingAs($user)->put(route('dashboard.spaces.update', $space), validSpacePayload([
        'social_url_enabled' => 1,
        'social_url_required' => 1,
        'photo_enabled' => 0,
    ]));

    $space->refresh();

    expect($space->social_url_enabled)->toBeTrue()
        ->and($space->social_url_required)->toBeTrue()
        ->and($space->photo_enabled)->toBeFalse()
        ->and($space->photo_required)->toBeFalse();
});

test('the field order is updated', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    $this->actingAs($user)->put(route('dashboard.spaces.update', $space), validSpacePayload([
        'field_order' => ['photo', 'name', 'email', 'company_name', 'social_url'],
    ]));

    expect($space->refresh()->field_order)
        ->toBe(['photo', 'name', 'email', 'company_name', 'social_url']);
});

test('another account cannot update a space', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $space = Space::factory()->for($owner)->create();

    $this->actingAs($other)
        ->put(route('dashboard.spaces.update', $space), validSpacePayload([
            'name' => 'Hijacked',
        ]))
        ->assertNotFound();

    expect($space->refresh()->name)->not->toBe('Hijacked');
});

test('another account cannot open the edit form', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $space = Space::factory()->for($owner)->create();

    $this->actingAs($other)
        ->get(route('dashboard.spaces.edit', $space))
        ->assertNotFound();
});

test('the space can still be renamed to its own name', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create(['name' => 'Acme Reviews']);

    $this->actingAs($user)
        ->put(route('dashboard.spaces.update', $space), validSpacePayload([
            'name' => 'Acme Reviews',
            'title' => 'A changed title',
        ]))
        ->assertSessionHasNoErrors();

    expect($space->refresh()->title)->toBe('A changed title');
});

test('the update redirects to the space page', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create();

    $this->actingAs($user)
        ->put(route('dashboard.spaces.update', $space), validSpacePayload())
        ->assertRedirect(route('dashboard.spaces.show', $space))
        ->assertSessionHas('status');
});
