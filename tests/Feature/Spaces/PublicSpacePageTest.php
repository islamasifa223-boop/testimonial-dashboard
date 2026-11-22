<?php

use App\Models\Space;
use App\Models\User;

beforeEach(function () {
    seedPlans();
});

test('the public page can be reached without signing in', function () {
    $space = Space::factory()->create(['slug' => 'public-space']);

    $this->get(route('spaces.show', $space->slug))
        ->assertOk()
        ->assertSee($space->title);
});

test('the public page shows the space details', function () {
    $space = Space::factory()->create([
        'slug' => 'public-space',
        'title' => 'Share your experience',
        'subtitle' => 'It only takes a minute.',
        'request' => 'Tell us how we did.',
    ]);

    $this->get(route('spaces.show', $space->slug))
        ->assertOk()
        ->assertSee('Share your experience')
        ->assertSee('It only takes a minute.')
        ->assertSee('Tell us how we did.');
});

test('an unknown slug returns a 404', function () {
    $this->get(route('spaces.show', 'no-such-space'))->assertNotFound();
});

test('a soft deleted space returns a 404', function () {
    $space = Space::factory()->create(['slug' => 'deleted-space']);

    $space->delete();

    $this->get(route('spaces.show', 'deleted-space'))->assertNotFound();
});

test('the public page shows the testimonial submission form', function () {
    $space = Space::factory()->create(['slug' => 'public-space']);

    $this->get(route('spaces.show', $space->slug))
        ->assertOk()
        ->assertSee('name="name"', false)
        ->assertSee('name="email"', false)
        ->assertSee('name="body"', false)
        ->assertSee('action="'.route('spaces.testimonials.store', $space->slug).'"', false);
});

test('the rating field is shown when ratings are enabled', function () {
    $space = Space::factory()->create([
        'slug' => 'public-space',
        'rating_enabled' => true,
    ]);

    $this->get(route('spaces.show', $space->slug))
        ->assertOk()
        ->assertSee('name="rating"', false);
});

test('the rating field is hidden when ratings are disabled', function () {
    $space = Space::factory()->create([
        'slug' => 'public-space',
        'rating_enabled' => false,
    ]);

    $this->get(route('spaces.show', $space->slug))
        ->assertOk()
        ->assertDontSee('name="rating"', false);
});

test('the public page does not leak the owner', function () {
    $owner = User::factory()->create(['name' => 'Secret Owner']);
    $space = Space::factory()->for($owner)->create(['slug' => 'public-space']);

    $this->get(route('spaces.show', $space->slug))
        ->assertOk()
        ->assertDontSee('Secret Owner');
});

test('the public page uses the space slug', function () {
    $space = Space::factory()->create(['slug' => 'my-public-space']);

    $this->get('/s/'.$space->slug)
        ->assertOk()
        ->assertSee($space->title);
});

test('deleting the owner removes the space and its public page', function () {
    $owner = User::factory()->create();
    $space = Space::factory()->for($owner)->create(['slug' => 'public-space']);

    // The spaces table cascades on delete, so removing the owner removes
    // the space and the public page with it.
    $owner->delete();

    expect(Space::count())->toBe(0);

    $this->get(route('spaces.show', 'public-space'))->assertNotFound();
});
