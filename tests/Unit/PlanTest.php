<?php

use App\Enums\PlanName;
use App\Enums\SpaceTheme;

/*
|--------------------------------------------------------------------------
| Plan Name Enum
|--------------------------------------------------------------------------
*/

test('the plan name enum exposes the free and pro tiers', function () {
    expect(PlanName::Free->value)->toBe('free')
        ->and(PlanName::Pro->value)->toBe('pro');
});

test('the plan name enum labels each tier', function () {
    expect(PlanName::Free->label())->toBe('Free')
        ->and(PlanName::Pro->label())->toBe('Pro');
});

test('the plan name enum lists its values', function () {
    expect(PlanName::values())->toBe(['free', 'pro']);
});

test('the plan name enum can be created from a value', function () {
    expect(PlanName::from('free'))->toBe(PlanName::Free)
        ->and(PlanName::from('pro'))->toBe(PlanName::Pro);
});

test('the plan name enum can be created from a value in a try block', function () {
    expect(PlanName::tryFrom('free'))->toBe(PlanName::Free)
        ->and(PlanName::tryFrom('enterprise'))->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Space Theme Enum
|--------------------------------------------------------------------------
*/

test('the space theme enum exposes each theme', function () {
    expect(SpaceTheme::MinimalLight->value)->toBe('minimal-light')
        ->and(SpaceTheme::ModernDark->value)->toBe('modern-dark')
        ->and(SpaceTheme::SoftGradient->value)->toBe('soft-gradient');
});

test('the space theme enum labels each theme', function () {
    expect(SpaceTheme::MinimalLight->label())->toBe('Minimal Light')
        ->and(SpaceTheme::ModernDark->label())->toBe('Modern Dark')
        ->and(SpaceTheme::SoftGradient->label())->toBe('Soft Gradient');
});

test('the space theme enum lists its values', function () {
    expect(SpaceTheme::values())->toBe(['minimal-light', 'modern-dark', 'soft-gradient']);
});

test('the space theme enum can be created from a value', function () {
    expect(SpaceTheme::from('minimal-light'))->toBe(SpaceTheme::MinimalLight)
        ->and(SpaceTheme::from('modern-dark'))->toBe(SpaceTheme::ModernDark)
        ->and(SpaceTheme::from('soft-gradient'))->toBe(SpaceTheme::SoftGradient);
});

test('the space theme enum can be created from a value in a try block', function () {
    expect(SpaceTheme::tryFrom('minimal-light'))->toBe(SpaceTheme::MinimalLight)
        ->and(SpaceTheme::tryFrom('neon'))->toBeNull();
});

test('the space theme enum has three cases', function () {
    expect(SpaceTheme::cases())->toHaveCount(3);
});

test('the plan name enum has two cases', function () {
    expect(PlanName::cases())->toHaveCount(2);
});
