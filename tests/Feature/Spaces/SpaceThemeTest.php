<?php

use App\Enums\SpaceTheme;
use App\Models\Space;
use App\Models\User;
use App\Services\SpaceThemePreview;

beforeEach(function () {
    seedPlans();
});

test('the three themes exist', function () {
    expect(SpaceTheme::values())->toBe(['minimal-light', 'modern-dark', 'soft-gradient']);
});

test('each theme has a label', function () {
    expect(SpaceTheme::MinimalLight->label())->toBe('Minimal Light')
        ->and(SpaceTheme::ModernDark->label())->toBe('Modern Dark')
        ->and(SpaceTheme::SoftGradient->label())->toBe('Soft Gradient');
});

test('the theme is cast to the enum', function () {
    $space = Space::factory()->create(['theme' => 'modern-dark']);

    expect($space->theme)->toBe(SpaceTheme::ModernDark);
});

test('a theme can be chosen when creating a space', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('dashboard.spaces.store'), validSpacePayload([
        'theme' => 'soft-gradient',
    ]));

    expect(Space::firstOrFail()->theme)->toBe(SpaceTheme::SoftGradient);
});

test('the chosen theme is shown on the space page', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create(['theme' => 'modern-dark']);

    $this->actingAs($user)
        ->get(route('dashboard.spaces.show', $space))
        ->assertOk()
        ->assertSee('Modern Dark');
});

test('the theme picker shows every theme', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard.spaces.create'))
        ->assertOk()
        ->assertSee('Minimal Light')
        ->assertSee('Modern Dark')
        ->assertSee('Soft Gradient');
});

test('the theme picker marks the saved theme as selected', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create(['theme' => 'soft-gradient']);

    $response = $this->actingAs($user)->get(route('dashboard.spaces.edit', $space));

    $response->assertOk();

    // The radio for the saved theme must carry the checked attribute, and
    // the other radios must not. The markup is parsed rather than matched
    // as a string, so the assertion does not depend on how Blade happens
    // to order or wrap the attributes.
    //
    // libxml's HTML parser does not know the HTML5 elements used by the
    // layout, so its warnings are silenced while the page is parsed and
    // the previous error-handling state is restored afterwards.
    $previous = libxml_use_internal_errors(true);

    try {
        $document = new DOMDocument;
        $document->loadHTML((string) $response->getContent());
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }

    $radios = (new DOMXPath($document))->query('//input[@type="radio" and @name="theme"]');

    $checked = [];

    foreach ($radios as $radio) {
        if ($radio->hasAttribute('checked')) {
            $checked[] = $radio->getAttribute('value');
        }
    }

    expect($checked)->toBe(['soft-gradient']);
});

test('every theme renders a preview', function () {
    $preview = app(SpaceThemePreview::class);

    foreach (SpaceTheme::cases() as $theme) {
        $swatch = $preview->swatch($theme);
        $page = $preview->page($theme);

        expect($swatch['surface'])->not->toBeEmpty()
            ->and($swatch['text'])->not->toBeEmpty()
            ->and($swatch['accent'])->not->toBeEmpty()
            ->and($page['page'])->not->toBeEmpty()
            ->and($page['heading'])->not->toBeEmpty()
            ->and($preview->description($theme))->not->toBeEmpty();
    }
});

test('the public page is rendered in the theme of the space', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create(['theme' => 'modern-dark']);

    $this->get(route('spaces.show', $space->slug))
        ->assertOk()
        ->assertSee('bg-slate-900', false);
});

test('a space can switch to another theme', function () {
    $user = User::factory()->create();
    $space = Space::factory()->for($user)->create(['theme' => 'minimal-light']);

    $this->actingAs($user)->put(route('dashboard.spaces.update', $space), validSpacePayload([
        'theme' => 'modern-dark',
    ]));

    expect($space->refresh()->theme)->toBe(SpaceTheme::ModernDark);
});
