<?php

use App\Models\Space;
use App\Models\Testimonial;

test('the moderation flags cannot be mass assigned through fill', function () {
    $testimonial = new Testimonial;

    $testimonial->fill([
        'name' => 'Ada Lovelace',
        'body' => 'A perfectly ordinary body.',
        'rating' => 5,
        'consented' => true,
        'approved' => true,
        'hidden' => true,
        'favorite' => true,
        'hearted' => true,
    ]);

    expect($testimonial->name)->toBe('Ada Lovelace')
        ->and($testimonial->body)->toBe('A perfectly ordinary body.')
        ->and($testimonial->rating)->toBe(5)
        ->and($testimonial->consented)->toBeNull()
        ->and($testimonial->approved)->toBeNull()
        ->and($testimonial->hidden)->toBeNull()
        ->and($testimonial->favorite)->toBeNull()
        ->and($testimonial->hearted)->toBeNull();
});

test('the moderation flags cannot be mass assigned through create', function () {
    $space = Space::factory()->create();

    $testimonial = $space->testimonials()->create([
        'name' => 'Grace Hopper',
        'email' => 'grace@example.com',
        'body' => 'Another perfectly ordinary body.',
        'rating' => 4,
        'consented' => true,
        'approved' => true,
        'hidden' => true,
        'favorite' => true,
        'hearted' => true,
    ]);

    $stored = Testimonial::query()->findOrFail($testimonial->id);

    expect($stored->space_id)->toBe($space->id)
        ->and($stored->name)->toBe('Grace Hopper')
        ->and($stored->body)->toBe('Another perfectly ordinary body.')
        ->and($stored->rating)->toBe(4)
        ->and($stored->consented)->toBeFalse()
        ->and($stored->approved)->toBeFalse()
        ->and($stored->hidden)->toBeFalse()
        ->and($stored->favorite)->toBeFalse()
        ->and($stored->hearted)->toBeFalse();
});

test('the moderation flags cannot be mass assigned through update', function () {
    $testimonial = Testimonial::factory()->create();

    $testimonial->update([
        'body' => 'An edited body.',
        'approved' => true,
        'consented' => true,
        'hearted' => true,
    ]);

    $stored = Testimonial::query()->findOrFail($testimonial->id);

    expect($stored->body)->toBe('An edited body.')
        ->and($stored->approved)->toBeFalse()
        ->and($stored->consented)->toBeFalse()
        ->and($stored->hearted)->toBeFalse();
});

test('fill cannot change the space id of an existing testimonial', function () {
    $space = Space::factory()->create();
    $other = Space::factory()->create();

    $testimonial = Testimonial::factory()->for($space)->create();

    $testimonial->fill([
        'space_id' => $other->id,
        'name' => 'Alan Turing',
    ]);

    expect($testimonial->space_id)->toBe($space->id)
        ->and($testimonial->name)->toBe('Alan Turing');
});

test('fill cannot change the space id of a stored testimonial', function () {
    $space = Space::factory()->create();
    $other = Space::factory()->create();

    $testimonial = Testimonial::factory()->for($space)->create();

    $testimonial->fill(['space_id' => $other->id]);
    $testimonial->save();

    $stored = Testimonial::query()->findOrFail($testimonial->id);

    expect($stored->space_id)->toBe($space->id);
});

test('creating through the relation assigns the correct space id', function () {
    $space = Space::factory()->create();

    $testimonial = $space->testimonials()->create([
        'name' => 'Katherine Johnson',
        'email' => 'katherine@example.com',
        'body' => 'A body submitted to a specific space.',
    ]);

    $stored = Testimonial::query()->findOrFail($testimonial->id);

    expect($stored->space_id)->toBe($space->id)
        ->and($stored->space->id)->toBe($space->id);
});

test('trusted server side code can set the moderation flags', function () {
    $testimonial = Testimonial::factory()->create();

    $testimonial->approved = true;
    $testimonial->consented = true;
    $testimonial->favorite = true;
    $testimonial->hearted = true;
    $testimonial->hidden = true;
    $testimonial->save();

    $stored = Testimonial::query()->findOrFail($testimonial->id);

    expect($stored->approved)->toBeTrue()
        ->and($stored->consented)->toBeTrue()
        ->and($stored->favorite)->toBeTrue()
        ->and($stored->hearted)->toBeTrue()
        ->and($stored->hidden)->toBeTrue();
});

test('force fill can set the moderation flags', function () {
    $testimonial = Testimonial::factory()->create();

    $testimonial->forceFill([
        'approved' => true,
        'hidden' => false,
    ])->save();

    $stored = Testimonial::query()->findOrFail($testimonial->id);

    expect($stored->approved)->toBeTrue()
        ->and($stored->hidden)->toBeFalse();
});

test('the factory states set the moderation flags', function () {
    expect(Testimonial::factory()->approved()->create()->approved)->toBeTrue()
        ->and(Testimonial::factory()->consented()->create()->consented)->toBeTrue()
        ->and(Testimonial::factory()->hidden()->create()->hidden)->toBeTrue()
        ->and(Testimonial::factory()->favorited()->create()->favorite)->toBeTrue()
        ->and(Testimonial::factory()->hearted()->create()->hearted)->toBeTrue();
});

test('the factory states persist the moderation flags to the database', function () {
    $testimonial = Testimonial::factory()->approved()->consented()->create();

    $stored = Testimonial::query()->findOrFail($testimonial->id);

    expect($stored->approved)->toBeTrue()
        ->and($stored->consented)->toBeTrue();
});

test('the publicly visible factory state is approved and consented', function () {
    $testimonial = Testimonial::factory()->publiclyVisible()->create();

    $stored = Testimonial::query()->findOrFail($testimonial->id);

    expect($stored->isPubliclyVisible())->toBeTrue();
});

test('the moderation flags default to false', function () {
    $testimonial = Testimonial::factory()->create();

    expect($testimonial->consented)->toBeFalse()
        ->and($testimonial->approved)->toBeFalse()
        ->and($testimonial->hidden)->toBeFalse()
        ->and($testimonial->favorite)->toBeFalse()
        ->and($testimonial->hearted)->toBeFalse();
});

test('an unsaved testimonial has no moderation flag values yet', function () {
    $testimonial = new Testimonial;

    expect($testimonial->consented)->toBeNull()
        ->and($testimonial->approved)->toBeNull()
        ->and($testimonial->hidden)->toBeNull()
        ->and($testimonial->favorite)->toBeNull()
        ->and($testimonial->hearted)->toBeNull();
});
