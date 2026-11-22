<?php

use App\Enums\SpaceField;
use App\Models\Space;
use App\Models\User;

beforeEach(function () {
    seedPlans();
});

test('the name is required', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('dashboard.spaces.store'), validSpacePayload(['name' => '']))
        ->assertSessionHasErrors('name');

    expect(Space::count())->toBe(0);
});

test('the title is required', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('dashboard.spaces.store'), validSpacePayload(['title' => '']))
        ->assertSessionHasErrors('title');
});

test('the theme is required', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('dashboard.spaces.store'), validSpacePayload(['theme' => '']))
        ->assertSessionHasErrors('theme');
});

test('the theme must be a known theme', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('dashboard.spaces.store'), validSpacePayload(['theme' => 'not-a-theme']))
        ->assertSessionHasErrors('theme');
});

test('the subtitle may be left empty', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('dashboard.spaces.store'), validSpacePayload(['subtitle' => null]))
        ->assertSessionHasNoErrors();

    expect(Space::count())->toBe(1);
});

test('the request message may be left empty', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('dashboard.spaces.store'), validSpacePayload(['request' => null]))
        ->assertSessionHasNoErrors();
});

test('an empty field order defaults to the standard order', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('dashboard.spaces.store'), validSpacePayload(['field_order' => []]))
        ->assertSessionHasNoErrors();

    expect(Space::firstOrFail()->field_order)
        ->toBe(SpaceField::defaultOrder());
});

test('the field order must only contain known fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('dashboard.spaces.store'), validSpacePayload([
            'field_order' => ['name', 'email', 'not_a_field'],
        ]))
        ->assertSessionHasErrors('field_order.2');
});

test('the field order must not contain duplicates', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('dashboard.spaces.store'), validSpacePayload([
            'field_order' => ['name', 'name', 'email'],
        ]))
        ->assertSessionHasErrors('field_order.1');
});

test('a partial field order is completed with the remaining fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('dashboard.spaces.store'), validSpacePayload([
            'field_order' => ['photo', 'name'],
        ]))
        ->assertSessionHasNoErrors();

    expect(Space::firstOrFail()->field_order)
        ->toBe(['photo', 'name', 'email', 'company_name', 'social_url']);
});

test('the name must be a string', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('dashboard.spaces.store'), validSpacePayload(['name' => ['an', 'array']]))
        ->assertSessionHasErrors('name');
});

test('the title must not be longer than 255 characters', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('dashboard.spaces.store'), validSpacePayload([
            'title' => str_repeat('a', 256),
        ]))
        ->assertSessionHasErrors('title');
});

test('the request message must not be longer than 2000 characters', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('dashboard.spaces.store'), validSpacePayload([
            'request' => str_repeat('a', 2001),
        ]))
        ->assertSessionHasErrors('request');
});

test('the internal name key is never stored on the space', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('dashboard.spaces.store'), validSpacePayload([
        'kudosly_name_key' => 'forged',
    ]));

    $space = Space::firstOrFail();

    expect($space->name_key)->not->toBe('forged');
});
