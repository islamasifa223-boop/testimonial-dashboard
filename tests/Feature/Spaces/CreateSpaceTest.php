<?php

use App\Enums\SpaceTheme;
use App\Models\Space;
use App\Models\User;
use App\Services\SpaceFieldConfiguration;

beforeEach(function () {
    seedPlans();
});

test('the create form can be rendered', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard.spaces.create'))
        ->assertOk()
        ->assertSee('Create a space');
});

test('guests cannot reach the create form', function () {
    $this->get(route('dashboard.spaces.create'))->assertRedirect(route('login'));
});

test('a space can be created', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('dashboard.spaces.store'), validSpacePayload());

    $response->assertRedirect(route('dashboard.spaces.created', Space::where('user_id', $user->id)->firstOrFail()));

    $space = Space::where('user_id', $user->id)->firstOrFail();

    expect($space->user_id)->toBe($user->id)
        ->and($space->name)->toBe('Acme Reviews')
        ->and($space->title)->toBe('Share your experience with Acme')
        ->and($space->subtitle)->toBe('It only takes a minute.')
        ->and($space->request)->toBe('Tell us how we did.')
        ->and($space->theme)->toBe(SpaceTheme::ModernDark)
        ->and($space->rating_enabled)->toBeTrue();
});

test('a slug is generated for the space', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('dashboard.spaces.store'), validSpacePayload());

    $space = Space::firstOrFail();

    expect($space->slug)->not->toBeEmpty()
        ->and($space->slug)->toBe('acme-reviews');
});

test('a submitted slug is ignored', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('dashboard.spaces.store'), validSpacePayload([
        'slug' => 'forged-slug',
    ]));

    expect(Space::firstOrFail()->slug)->toBe('acme-reviews');
});

test('the space belongs to the signed in account', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('dashboard.spaces.store'), validSpacePayload());

    expect(Space::firstOrFail()->user_id)->toBe($user->id);
});

test('the field configuration is stored on the space', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('dashboard.spaces.store'), validSpacePayload());

    $space = Space::firstOrFail();

    expect($space->company_name_enabled)->toBeTrue()
        ->and($space->company_name_required)->toBeFalse()
        ->and($space->social_url_enabled)->toBeFalse()
        ->and($space->photo_enabled)->toBeTrue()
        ->and($space->photo_required)->toBeFalse();
});

test('a field that is switched off is never stored as required', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('dashboard.spaces.store'), validSpacePayload([
        'social_url_enabled' => 0,
        'social_url_required' => 1,
    ]));

    expect(Space::firstOrFail()->social_url_required)->toBeFalse();
});

test('the field order is stored on the space', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('dashboard.spaces.store'), validSpacePayload([
        'field_order' => ['photo', 'name', 'email', 'company_name', 'social_url'],
    ]));

    expect(Space::firstOrFail()->field_order)
        ->toBe(['photo', 'name', 'email', 'company_name', 'social_url']);
});

test('the space is redirected to the success page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('dashboard.spaces.store'), validSpacePayload())
        ->assertRedirect(route('dashboard.spaces.created', Space::where('user_id', $user->id)->firstOrFail()));
});

test('the success page shows the public link', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('dashboard.spaces.store'), validSpacePayload());

    $this->actingAs($user)
        ->get(route('dashboard.spaces.created', Space::firstOrFail()))
        ->assertOk()
        ->assertSee('/s/acme-reviews');
});

test('a space cannot be created once the plan limit is reached', function () {
    $user = User::factory()->create();

    Space::factory()->count(3)->for($user)->create();

    $this->actingAs($user)
        ->post(route('dashboard.spaces.store'), validSpacePayload())
        ->assertRedirect(route('dashboard.spaces.index'))
        ->assertSessionHas('error');

    expect(Space::count())->toBe(3);
});

test('the create form is closed once the plan limit is reached', function () {
    $user = User::factory()->create();

    Space::factory()->count(3)->for($user)->create();

    $this->actingAs($user)
        ->get(route('dashboard.spaces.create'))
        ->assertRedirect(route('dashboard.spaces.index'))
        ->assertSessionHas('error');
});

test('the space count of another account does not count towards the limit', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    Space::factory()->count(3)->for($other)->create();

    $this->actingAs($user)
        ->post(route('dashboard.spaces.store'), validSpacePayload())
        ->assertRedirect(route('dashboard.spaces.created', Space::where('user_id', $user->id)->firstOrFail()));

    expect(Space::count())->toBe(4);
});

test('the field configuration service reads the submitted values', function () {
    $configuration = app(SpaceFieldConfiguration::class)->fromInput([
        'company_name_enabled' => '1',
        'company_name_required' => '1',
        'social_url_enabled' => '0',
        'social_url_required' => '1',
        'photo_enabled' => '1',
        'photo_required' => '0',
    ]);

    expect($configuration['company_name'])->toBe(['enabled' => true, 'required' => true])
        ->and($configuration['social_url'])->toBe(['enabled' => false, 'required' => true])
        ->and($configuration['photo'])->toBe(['enabled' => true, 'required' => false]);
});

test('the field configuration service clears the required flag for a disabled field', function () {
    $columns = app(SpaceFieldConfiguration::class)->toColumns([
        'social_url' => ['enabled' => false, 'required' => true],
    ]);

    expect($columns['social_url_enabled'])->toBeFalse()
        ->and($columns['social_url_required'])->toBeFalse();
});
