<?php

use App\Enums\TestimonialVisibility;
use App\Models\Testimonial;
use App\Models\Space;

test('a testimonial is publicly visible when it is approved, not hidden and consented', function () {
    expect(TestimonialVisibility::isPubliclyVisible(true, false, true))->toBeTrue();
});

test('a testimonial is not publicly visible without approval', function () {
    expect(TestimonialVisibility::isPubliclyVisible(false, false, true))->toBeFalse();
});

test('a testimonial is not publicly visible when it is hidden', function () {
    expect(TestimonialVisibility::isPubliclyVisible(true, true, true))->toBeFalse();
});

test('a testimonial is not publicly visible without consent', function () {
    expect(TestimonialVisibility::isPubliclyVisible(true, false, false))->toBeFalse();
});

test('a testimonial is not publicly visible when every flag is against it', function () {
    expect(TestimonialVisibility::isPubliclyVisible(false, true, false))->toBeFalse();
});

test('the model reports its own public visibility', function () {
    $testimonial = Testimonial::factory()->publiclyVisible()->create();

    expect($testimonial->isPubliclyVisible())->toBeTrue();
});

test('a hidden testimonial is not publicly visible', function () {
    $testimonial = Testimonial::factory()->publiclyVisible()->hidden()->create();

    expect($testimonial->isPubliclyVisible())->toBeFalse();
});

test('an unconsented testimonial is not publicly visible', function () {
    $testimonial = Testimonial::factory()->approved()->create();

    expect($testimonial->isPubliclyVisible())->toBeFalse();
});

test('an unsaved testimonial is not publicly visible', function () {
    $testimonial = new Testimonial;

    expect($testimonial->isPubliclyVisible())->toBeFalse();
});

test('the public attributes never include the customer email', function () {
    $testimonial = Testimonial::factory()->create();

    expect($testimonial->publicAttributes())->not->toContain('email')
        ->and($testimonial->publicAttributes())->toContain('name')
        ->and($testimonial->publicAttributes())->toContain('body');
});

/*
|--------------------------------------------------------------------------
| The Wall of Love rule
|--------------------------------------------------------------------------
|
| A testimonial only appears on the public Wall when it carries a heart
| and satisfies the public visibility rule as well. The heart is the
| owner's selection on top of visibility, so the two are independent.
|
| Each test below breaks exactly one condition, so a failure points at
| the condition that was not applied.
|
*/

test('a testimonial is on the wall when it is hearted, approved, not hidden and consented', function () {
    expect(TestimonialVisibility::isOnWall(true, false, true, true))->toBeTrue();
});

test('a testimonial is not on the wall without a heart', function () {
    expect(TestimonialVisibility::isOnWall(true, false, true, false))->toBeFalse();
});

test('a testimonial is not on the wall without approval', function () {
    expect(TestimonialVisibility::isOnWall(false, false, true, true))->toBeFalse();
});

test('a testimonial is not on the wall when it is hidden', function () {
    expect(TestimonialVisibility::isOnWall(true, true, true, true))->toBeFalse();
});

test('a testimonial is not on the wall without consent', function () {
    expect(TestimonialVisibility::isOnWall(true, false, false, true))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| The model
|--------------------------------------------------------------------------
|
| The model reads the hidden column through getAttribute() rather than
| $this->hidden, because Eloquent declares a protected $hidden property
| for serialisation. Reading the property directly would return that
| array instead of the column, and the rule would then be applied to
| the wrong value.
|
*/

test('the model reports that it is on the wall', function () {
    $testimonial = Testimonial::factory()->approved()->consented()->hearted()->create();

    expect($testimonial->isOnWall())->toBeTrue();
});

test('a hidden testimonial is not on the wall', function () {
    $testimonial = Testimonial::factory()->approved()->consented()->hearted()->hidden()->create();

    expect($testimonial->isOnWall())->toBeFalse();
});

test('a testimonial without a heart is not on the wall', function () {
    $testimonial = Testimonial::factory()->approved()->consented()->create();

    expect($testimonial->isOnWall())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| The query
|--------------------------------------------------------------------------
|
| The wall is read through a scope rather than through the enum, so the
| scope is pinned on its own here. The names are pinned explicitly
| because both factories generate random names, and a random name that
| happened to match one of the witnesses would make the comparison
| fail for the wrong reason.
|
*/

test('the visible on wall scope returns only the fully eligible testimonials', function () {
    $space = Space::factory()->create();

    $onWall = Testimonial::factory()->for($space)->approved()->consented()->hearted()->create([
        'name' => 'Ada Lovelace',
    ]);

    Testimonial::factory()->for($space)->consented()->hearted()->create([
        'name' => 'Not Approved',
    ]);
    Testimonial::factory()->for($space)->approved()->consented()->hidden()->hearted()->create([
        'name' => 'Hidden One',
    ]);
    Testimonial::factory()->for($space)->approved()->hearted()->create([
        'name' => 'Not Consented',
    ]);
    Testimonial::factory()->for($space)->approved()->consented()->create([
        'name' => 'Not Hearted',
    ]);

    $found = Testimonial::query()->visibleOnWall()->pluck('name')->all();

    expect($found)->toBe(['Ada Lovelace']);
});

test('the visible on wall scope returns nothing when no testimonial qualifies', function () {
    $space = Space::factory()->create();

    Testimonial::factory()->for($space)->approved()->consented()->create([
        'name' => 'Not Hearted',
    ]);

    expect(Testimonial::query()->visibleOnWall()->count())->toBe(0);
});
