<?php

use App\Enums\SpaceField;
use App\Models\Space;
use App\Models\User;

beforeEach(function () {
    seedPlans();
});

test('name and email are always collected', function () {
    $space = new Space;

    expect($space->isFieldEnabled(SpaceField::Name))->toBeTrue()
        ->and($space->isFieldEnabled(SpaceField::Email))->toBeTrue()
        ->and($space->isFieldRequired(SpaceField::Name))->toBeTrue()
        ->and($space->isFieldRequired(SpaceField::Email))->toBeTrue();
});

test('name and email cannot be switched off', function () {
    expect(SpaceField::Name->isAlwaysEnabled())->toBeTrue()
        ->and(SpaceField::Email->isAlwaysEnabled())->toBeTrue()
        ->and(SpaceField::Name->isAlwaysRequired())->toBeTrue()
        ->and(SpaceField::Email->isAlwaysRequired())->toBeTrue();
});

test('the configurable fields are the three optional ones', function () {
    expect(SpaceField::configurableValues())
        ->toBe(['company_name', 'social_url', 'photo']);
});

test('the default field order lists every field', function () {
    expect(SpaceField::defaultOrder())
        ->toBe(['name', 'email', 'company_name', 'social_url', 'photo']);
});

test('the field order falls back to the default when none is stored', function () {
    expect((new Space)->fieldOrder())->toBe(SpaceField::defaultOrder());
});

test('the stored field order is returned', function () {
    $space = new Space(['field_order' => ['photo', 'name', 'email']]);

    expect($space->fieldOrder())->toBe(['photo', 'name', 'email']);
});

test('a configurable field follows its stored columns', function () {
    $space = new Space([
        'company_name_enabled' => true,
        'company_name_required' => true,
        'social_url_enabled' => false,
        'photo_enabled' => true,
    ]);

    expect($space->isFieldEnabled(SpaceField::CompanyName))->toBeTrue()
        ->and($space->isFieldRequired(SpaceField::CompanyName))->toBeTrue()
        ->and($space->isFieldEnabled(SpaceField::SocialUrl))->toBeFalse()
        ->and($space->isFieldRequired(SpaceField::SocialUrl))->toBeFalse()
        ->and($space->isFieldEnabled(SpaceField::Photo))->toBeTrue()
        ->and($space->isFieldRequired(SpaceField::Photo))->toBeFalse();
});

test('the field methods always return a boolean', function () {
    $space = new Space;

    foreach (SpaceField::cases() as $field) {
        expect($space->isFieldEnabled($field))->toBeBool()
            ->and($space->isFieldRequired($field))->toBeBool();
    }
});

test('a field that is switched off is never required', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('dashboard.spaces.store'), validSpacePayload([
        'company_name_enabled' => 0,
        'company_name_required' => 1,
    ]));

    $space = Space::firstOrFail();

    expect($space->company_name_enabled)->toBeFalse()
        ->and($space->company_name_required)->toBeFalse();
});

test('the field order is preserved exactly as submitted', function () {
    $user = User::factory()->create();

    $order = ['photo', 'social_url', 'name', 'email', 'company_name'];

    $this->actingAs($user)->post(route('dashboard.spaces.store'), validSpacePayload([
        'field_order' => $order,
    ]));

    expect(Space::firstOrFail()->field_order)->toBe($order);
});

test('the field order is stored as an array', function () {
    $space = Space::factory()->create();

    expect($space->field_order)->toBeArray()
        ->and($space->field_order)->toHaveCount(5);
});

test('the space page lists every field in order', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create([
        'field_order' => ['photo', 'name', 'email', 'company_name', 'social_url'],
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.spaces.show', $space))
        ->assertOk()
        ->assertSeeInOrder(['Profile photo', 'Name', 'Email', 'Company name', 'Social profile URL']);
});
