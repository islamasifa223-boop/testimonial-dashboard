<?php

use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| The public Wall of Love page
|--------------------------------------------------------------------------
|
| These tests cover the public page at /wall/{slug}. They prove that the
| page shows the testimonials the owner put on the wall and nothing
| else, that it never reveals an email address or the owner, and that
| a photo is only ever linked through the wall scoped photo route.
|
*/

beforeEach(function () {
    seedPlans();
});

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * A space with a wall.
 *
 * The slug is set so the route can be built, and the title is fixed so
 * the page can be checked for the space's own details.
 */
function wallPageSpace(array $attributes = []): Space
{
    return Space::factory()->create(array_merge([
        'slug' => 'wall-space',
        'title' => 'Acme Wall of Love',
        'subtitle' => 'What our customers say.',
    ], $attributes));
}

/**
 * A testimonial that is on the wall.
 *
 * All four conditions hold: hearted, approved, not hidden and
 * consented. A test that needs one of them broken overrides the state
 * it cares about.
 */
function wallPageTestimonial(Space $space, array $attributes = []): Testimonial
{
    return Testimonial::factory()
        ->for($space)
        ->approved()
        ->consented()
        ->hearted()
        ->create(array_merge([
            'name' => 'Ada Lovelace',
            'body' => 'A wonderfully ordinary testimonial.',
        ], $attributes));
}

/**
 * The offset of a name in the rendered page.
 *
 * The offset is -1 when the name is not on the page at all, so a name
 * that never appears can never be mistaken for one that appears early.
 */
function wallPagePosition(string $content, string $name): int
{
    $position = strpos($content, $name);

    return $position === false ? -1 : $position;
}

/*
|--------------------------------------------------------------------------
| The page
|--------------------------------------------------------------------------
*/

test('the wall page can be reached without signing in', function () {
    $space = wallPageSpace();

    $this->get(route('walls.show', $space->slug))
        ->assertOk()
        ->assertSee($space->title);
});

test('the wall page shows the space subtitle', function () {
    $space = wallPageSpace();

    $this->get(route('walls.show', $space->slug))
        ->assertOk()
        ->assertSee('What our customers say.');
});

test('the wall page uses the space slug', function () {
    $space = wallPageSpace(['slug' => 'my-wall-space']);

    $this->get('/wall/'.$space->slug)
        ->assertOk()
        ->assertSee($space->title);
});

test('an unknown slug returns a 404', function () {
    $this->get(route('walls.show', 'no-such-space'))->assertNotFound();
});

test('a soft deleted space returns a 404', function () {
    $space = wallPageSpace(['slug' => 'deleted-space']);

    $space->delete();

    $this->get(route('walls.show', 'deleted-space'))->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| The empty wall
|--------------------------------------------------------------------------
*/

test('a space without any testimonial still renders the page', function () {
    $space = wallPageSpace();

    $this->get(route('walls.show', $space->slug))
        ->assertOk()
        ->assertSee('No testimonials yet');
});

test('the empty wall shows no testimonial cards', function () {
    $space = wallPageSpace();

    $this->get(route('walls.show', $space->slug))
        ->assertOk()
        ->assertDontSee('A wonderfully ordinary testimonial.');
});

/*
|--------------------------------------------------------------------------
| The testimonials on the wall
|--------------------------------------------------------------------------
*/

test('a testimonial on the wall is shown', function () {
    $space = wallPageSpace();

    wallPageTestimonial($space);

    $this->get(route('walls.show', $space->slug))
        ->assertOk()
        ->assertSee('Ada Lovelace')
        ->assertSee('A wonderfully ordinary testimonial.');
});

test('the company name is shown when the customer gave one', function () {
    $space = wallPageSpace();

    wallPageTestimonial($space, ['company_name' => 'Analytical Engines Ltd.']);

    $this->get(route('walls.show', $space->slug))
        ->assertOk()
        ->assertSee('Analytical Engines Ltd.');
});

test('a testimonial without a company name shows none', function () {
    $space = wallPageSpace();

    wallPageTestimonial($space, ['company_name' => null]);

    $this->get(route('walls.show', $space->slug))
        ->assertOk()
        ->assertDontSee('Analytical Engines Ltd.');
});

test('the rating is shown as stars', function () {
    $space = wallPageSpace();

    wallPageTestimonial($space, ['rating' => 4]);

    $this->get(route('walls.show', $space->slug))
        ->assertOk()
        ->assertSee('4 out of 5 stars');
});

test('a testimonial without a rating shows no stars', function () {
    $space = wallPageSpace();

    wallPageTestimonial($space, ['rating' => null]);

    $this->get(route('walls.show', $space->slug))
        ->assertOk()
        ->assertDontSee('out of 5 stars');
});

test('a testimonial of another space is not shown', function () {
    $space = wallPageSpace();

    $other = wallPageSpace(['slug' => 'other-space']);

    wallPageTestimonial($other, [
        'name' => 'Grace Hopper',
        'body' => 'A testimonial from somewhere else.',
    ]);

    $this->get(route('walls.show', $space->slug))
        ->assertOk()
        ->assertDontSee('Grace Hopper')
        ->assertDontSee('A testimonial from somewhere else.');
});

/*
|--------------------------------------------------------------------------
| Only the testimonials that belong on the wall
|--------------------------------------------------------------------------
*/

test('a testimonial without a heart is not shown', function () {
    $space = wallPageSpace();

    wallPageTestimonial($space, ['hearted' => false]);

    $this->get(route('walls.show', $space->slug))
        ->assertOk()
        ->assertDontSee('Ada Lovelace');
});

test('a testimonial that is not approved is not shown', function () {
    $space = wallPageSpace();

    wallPageTestimonial($space, ['approved' => false]);

    $this->get(route('walls.show', $space->slug))
        ->assertOk()
        ->assertDontSee('Ada Lovelace');
});

test('a hidden testimonial is not shown', function () {
    $space = wallPageSpace();

    wallPageTestimonial($space, ['hidden' => true]);

    $this->get(route('walls.show', $space->slug))
        ->assertOk()
        ->assertDontSee('Ada Lovelace');
});

test('a testimonial without consent is not shown', function () {
    $space = wallPageSpace();

    wallPageTestimonial($space, ['consented' => false]);

    $this->get(route('walls.show', $space->slug))
        ->assertOk()
        ->assertDontSee('Ada Lovelace');
});

test('a hidden testimonial is not shown even when it carries a heart', function () {
    $space = wallPageSpace();

    wallPageTestimonial($space, ['hidden' => true, 'hearted' => true]);

    $this->get(route('walls.show', $space->slug))
        ->assertOk()
        ->assertDontSee('Ada Lovelace');
});

test('the page only shows the testimonials that belong on the wall', function () {
    $space = wallPageSpace();

    wallPageTestimonial($space, ['name' => 'Ada Lovelace']);

    wallPageTestimonial($space, ['name' => 'Grace Hopper', 'hearted' => false]);

    $this->get(route('walls.show', $space->slug))
        ->assertOk()
        ->assertSee('Ada Lovelace')
        ->assertDontSee('Grace Hopper');
});

/*
|--------------------------------------------------------------------------
| The private data
|--------------------------------------------------------------------------
*/

test('the wall page does not show the customer email', function () {
    $space = wallPageSpace();

    wallPageTestimonial($space, ['email' => 'ada@example.com']);

    $this->get(route('walls.show', $space->slug))
        ->assertOk()
        ->assertDontSee('ada@example.com');
});

test('the wall page does not show the owner', function () {
    $owner = User::factory()->create(['name' => 'Secret Owner']);

    $space = Space::factory()->for($owner)->create([
        'slug' => 'wall-space',
        'title' => 'Acme Wall of Love',
    ]);

    wallPageTestimonial($space);

    $this->get(route('walls.show', $space->slug))
        ->assertOk()
        ->assertDontSee('Secret Owner');
});

test('the wall page does not show the owner email address', function () {
    $owner = User::factory()->create(['email' => 'owner@example.com']);

    $space = Space::factory()->for($owner)->create([
        'slug' => 'wall-space',
        'title' => 'Acme Wall of Love',
    ]);

    wallPageTestimonial($space);

    $this->get(route('walls.show', $space->slug))
        ->assertOk()
        ->assertDontSee('owner@example.com');
});

test('the wall page does not show the social profile url', function () {
    $space = wallPageSpace();

    wallPageTestimonial($space, ['social_url' => 'https://example.com/ada']);

    $this->get(route('walls.show', $space->slug))
        ->assertOk()
        ->assertDontSee('https://example.com/ada');
});

/*
|--------------------------------------------------------------------------
| The photo
|--------------------------------------------------------------------------
*/

test('a photo is linked through the wall photo route', function () {
    $space = wallPageSpace();

    $testimonial = wallPageTestimonial($space, ['photo_path' => 'testimonials/1/photo.png']);

    $this->get(route('walls.show', $space->slug))
        ->assertOk()
        ->assertSee(route('walls.photos.show', [$space->slug, $testimonial->id]), false);
});

test('the raw storage path is never shown', function () {
    $space = wallPageSpace();

    wallPageTestimonial($space, ['photo_path' => 'testimonials/1/secret-photo-name.png']);

    $this->get(route('walls.show', $space->slug))
        ->assertOk()
        ->assertDontSee('secret-photo-name.png');
});

test('a testimonial without a photo links to no photo', function () {
    $space = wallPageSpace();

    $testimonial = wallPageTestimonial($space, ['photo_path' => null]);

    $this->get(route('walls.show', $space->slug))
        ->assertOk()
        ->assertDontSee(route('walls.photos.show', [$space->slug, $testimonial->id]), false);
});

/*
|--------------------------------------------------------------------------
| The order
|--------------------------------------------------------------------------
*/

test('the newest testimonial is shown first', function () {
    $space = wallPageSpace();

    wallPageTestimonial($space, [
        'name' => 'Ada Lovelace',
        'created_at' => now()->subDays(3),
    ]);

    wallPageTestimonial($space, [
        'name' => 'Grace Hopper',
        'created_at' => now()->subDay(),
    ]);

    $content = $this->get(route('walls.show', $space->slug))
        ->assertOk()
        ->getContent();

    $ada = wallPagePosition($content, 'Ada Lovelace');
    $grace = wallPagePosition($content, 'Grace Hopper');

    expect($ada)->toBeGreaterThan(-1)
        ->and($grace)->toBeGreaterThan(-1)
        ->and($grace)->toBeLessThan($ada);
});

test('testimonials that share a timestamp keep a stable order', function () {
    $space = wallPageSpace();

    $created = now();

    $first = wallPageTestimonial($space, [
        'name' => 'Ada Lovelace',
        'created_at' => $created,
    ]);

    $second = wallPageTestimonial($space, [
        'name' => 'Grace Hopper',
        'created_at' => $created,
    ]);

    $content = $this->get(route('walls.show', $space->slug))
        ->assertOk()
        ->getContent();

    // The primary key breaks the tie, so the higher id is shown first.
    expect($second->id)->toBeGreaterThan($first->id)
        ->and(wallPagePosition($content, 'Grace Hopper'))
        ->toBeLessThan(wallPagePosition($content, 'Ada Lovelace'));
});
