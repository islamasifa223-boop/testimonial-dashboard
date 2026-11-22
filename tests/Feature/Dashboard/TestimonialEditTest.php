<?php

use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;

beforeEach(function () {
    seedPlans();
});

/*
 * The owner may edit the text and the rating of a testimonial. Nothing
 * else: the customer details and the consent belong to the customer, and
 * the moderation flags have their own actions.
 *
 * The form only offers a rating when the space collects ratings, so most
 * of these tests use a space with rating_enabled switched on.
 *
 * A note on names. The layout renders the name of the signed in user, and
 * the name of the space appears on the edit page. Both factories generate
 * random names, so every fixture here pins an explicit name. Without
 * that, a random name could collide with a name a test asserts is
 * absent, and the assertion would fail for the wrong reason.
 */

/*
|--------------------------------------------------------------------------
| The edit form
|--------------------------------------------------------------------------
*/

test('the edit form can be rendered', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    $testimonial = Testimonial::factory()->for($space)->create([
        'name' => 'Ada Lovelace',
        'body' => 'A wonderfully ordinary testimonial.',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.testimonials.edit', $testimonial))
        ->assertOk()
        ->assertSee('Edit testimonial')
        ->assertSee('A wonderfully ordinary testimonial.');
});

test('the edit form shows the customer details', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    $testimonial = Testimonial::factory()->for($space)->create([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'company_name' => 'Analytical Engines',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.testimonials.edit', $testimonial))
        ->assertOk()
        ->assertSee('Ada Lovelace')
        ->assertSee('ada@example.com')
        ->assertSee('Analytical Engines');
});

test('the edit form offers a rating when the space collects ratings', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create([
        'name' => 'Owned Space',
        'rating_enabled' => true,
    ]);

    $testimonial = Testimonial::factory()->for($space)->create([
        'name' => 'Ada Lovelace',
        'rating' => 4,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.testimonials.edit', $testimonial))
        ->assertOk()
        ->assertSee('name="rating"', false)
        ->assertSee('<option value="4" selected>4 stars</option>', false);
});

test('the edit form shows the rating as text when the space does not collect ratings', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create([
        'name' => 'Owned Space',
        'rating_enabled' => false,
    ]);

    $testimonial = Testimonial::factory()->for($space)->create([
        'name' => 'Ada Lovelace',
        'rating' => 4,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.testimonials.edit', $testimonial))
        ->assertOk()
        ->assertSee('4 out of 5')
        ->assertDontSee('name="rating"', false);
});

/*
|--------------------------------------------------------------------------
| Saving an edit
|--------------------------------------------------------------------------
*/

test('the text of a testimonial can be edited', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    $testimonial = Testimonial::factory()->for($space)->create([
        'name' => 'Ada Lovelace',
        'body' => 'The original wording.',
    ]);

    $this->actingAs($user)
        ->put(route('dashboard.testimonials.update', $testimonial), [
            'body' => 'The carefully edited wording.',
        ])
        ->assertRedirect(route('dashboard.inbox'))
        ->assertSessionHas('status', 'The testimonial has been updated.');

    expect($testimonial->refresh()->body)->toBe('The carefully edited wording.');
});

test('the rating of a testimonial can be edited', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create([
        'name' => 'Owned Space',
        'rating_enabled' => true,
    ]);

    $testimonial = Testimonial::factory()->for($space)->create([
        'name' => 'Ada Lovelace',
        'rating' => 2,
    ]);

    $this->actingAs($user)
        ->put(route('dashboard.testimonials.update', $testimonial), [
            'body' => 'The wording stays the same.',
            'rating' => 5,
        ])
        ->assertRedirect(route('dashboard.inbox'));

    expect($testimonial->refresh()->rating)->toBe(5);
});

test('a rating can be removed from a testimonial', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create([
        'name' => 'Owned Space',
        'rating_enabled' => true,
    ]);

    $testimonial = Testimonial::factory()->for($space)->create([
        'name' => 'Ada Lovelace',
        'rating' => 4,
    ]);

    $this->actingAs($user)
        ->put(route('dashboard.testimonials.update', $testimonial), [
            'body' => 'The wording stays the same.',
            'rating' => '',
        ])
        ->assertRedirect(route('dashboard.inbox'));

    expect($testimonial->refresh()->rating)->toBeNull();
});

test('the wording of a testimonial is trimmed', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    $testimonial = Testimonial::factory()->for($space)->create([
        'name' => 'Ada Lovelace',
        'body' => 'The original wording.',
    ]);

    $this->actingAs($user)
        ->put(route('dashboard.testimonials.update', $testimonial), [
            'body' => "   The carefully edited wording.   ",
        ])
        ->assertRedirect(route('dashboard.inbox'));

    expect($testimonial->refresh()->body)->toBe('The carefully edited wording.');
});

test('an edit leaves the customer details alone', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    $testimonial = Testimonial::factory()->for($space)->create([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'company_name' => 'Analytical Engines',
    ]);

    $this->actingAs($user)
        ->put(route('dashboard.testimonials.update', $testimonial), [
            'body' => 'The carefully edited wording.',
            'name' => 'Grace Hopper',
            'email' => 'grace@example.com',
            'company_name' => 'Compilers',
        ])
        ->assertRedirect(route('dashboard.inbox'));

    $testimonial->refresh();

    expect($testimonial->body)->toBe('The carefully edited wording.')
        ->and($testimonial->name)->toBe('Ada Lovelace')
        ->and($testimonial->email)->toBe('ada@example.com')
        ->and($testimonial->company_name)->toBe('Analytical Engines');
});

test('an edit leaves the moderation flags alone', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    $testimonial = Testimonial::factory()->for($space)->create([
        'name' => 'Ada Lovelace',
        'approved' => false,
        'consented' => false,
        'hearted' => false,
        'favorite' => false,
        'hidden' => false,
    ]);

    $this->actingAs($user)
        ->put(route('dashboard.testimonials.update', $testimonial), [
            'body' => 'The carefully edited wording.',
            'approved' => true,
            'consented' => true,
            'hearted' => true,
            'favorite' => true,
            'hidden' => true,
        ])
        ->assertRedirect(route('dashboard.inbox'));

    $testimonial->refresh();

    expect($testimonial->approved)->toBeFalse()
        ->and($testimonial->consented)->toBeFalse()
        ->and($testimonial->hearted)->toBeFalse()
        ->and($testimonial->favorite)->toBeFalse()
        ->and($testimonial->getAttribute('hidden'))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

test('an edit without a body is rejected', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    $testimonial = Testimonial::factory()->for($space)->create([
        'name' => 'Ada Lovelace',
        'body' => 'The original wording.',
    ]);

    $this->actingAs($user)
        ->put(route('dashboard.testimonials.update', $testimonial), [
            'body' => '',
        ])
        ->assertSessionHasErrors('body');

    expect($testimonial->refresh()->body)->toBe('The original wording.');
});

test('an edit with only spaces in the body is rejected', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    $testimonial = Testimonial::factory()->for($space)->create([
        'name' => 'Ada Lovelace',
        'body' => 'The original wording.',
    ]);

    $this->actingAs($user)
        ->put(route('dashboard.testimonials.update', $testimonial), [
            'body' => '    ',
        ])
        ->assertSessionHasErrors('body');

    expect($testimonial->refresh()->body)->toBe('The original wording.');
});

test('an edit with a body over the limit is rejected', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    $testimonial = Testimonial::factory()->for($space)->create([
        'name' => 'Ada Lovelace',
        'body' => 'The original wording.',
    ]);

    $this->actingAs($user)
        ->put(route('dashboard.testimonials.update', $testimonial), [
            'body' => str_repeat('a', 2001),
        ])
        ->assertSessionHasErrors('body');

    expect($testimonial->refresh()->body)->toBe('The original wording.');
});

test('an edit with a rating above the scale is rejected', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create([
        'name' => 'Owned Space',
        'rating_enabled' => true,
    ]);

    $testimonial = Testimonial::factory()->for($space)->create([
        'name' => 'Ada Lovelace',
        'rating' => 4,
    ]);

    $this->actingAs($user)
        ->put(route('dashboard.testimonials.update', $testimonial), [
            'body' => 'The carefully edited wording.',
            'rating' => 6,
        ])
        ->assertSessionHasErrors('rating');

    expect($testimonial->refresh()->rating)->toBe(4);
});

test('an edit with a rating below the scale is rejected', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create([
        'name' => 'Owned Space',
        'rating_enabled' => true,
    ]);

    $testimonial = Testimonial::factory()->for($space)->create([
        'name' => 'Ada Lovelace',
        'rating' => 4,
    ]);

    $this->actingAs($user)
        ->put(route('dashboard.testimonials.update', $testimonial), [
            'body' => 'The carefully edited wording.',
            'rating' => 0,
        ])
        ->assertSessionHasErrors('rating');

    expect($testimonial->refresh()->rating)->toBe(4);
});

/*
|--------------------------------------------------------------------------
| Guests
|--------------------------------------------------------------------------
*/

test('a guest cannot open the edit form', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    $testimonial = Testimonial::factory()->for($space)->create([
        'name' => 'Ada Lovelace',
        'body' => 'The original wording.',
    ]);

    $this->get(route('dashboard.testimonials.edit', $testimonial))
        ->assertRedirect(route('login'));
});

test('a guest cannot save an edit', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    $testimonial = Testimonial::factory()->for($space)->create([
        'name' => 'Ada Lovelace',
        'body' => 'The original wording.',
    ]);

    $this->put(route('dashboard.testimonials.update', $testimonial), [
        'body' => 'A hijacked wording.',
    ])->assertRedirect(route('login'));

    expect($testimonial->refresh()->body)->toBe('The original wording.');
});

/*
|--------------------------------------------------------------------------
| Another account
|--------------------------------------------------------------------------
*/

test('another account cannot open the edit form', function () {
    $owner = User::factory()->create(['name' => 'Inbox Owner']);
    $other = User::factory()->create(['name' => 'Foreign Owner']);

    $space = Space::factory()->for($owner)->create(['name' => 'Owned Space']);

    $testimonial = Testimonial::factory()->for($space)->create([
        'name' => 'Ada Lovelace',
        'body' => 'The original wording.',
    ]);

    $this->actingAs($other)
        ->get(route('dashboard.testimonials.edit', $testimonial))
        ->assertForbidden();
});

test('another account cannot save an edit', function () {
    $owner = User::factory()->create(['name' => 'Inbox Owner']);
    $other = User::factory()->create(['name' => 'Foreign Owner']);

    $space = Space::factory()->for($owner)->create(['name' => 'Owned Space']);

    $testimonial = Testimonial::factory()->for($space)->create([
        'name' => 'Ada Lovelace',
        'body' => 'The original wording.',
    ]);

    $this->actingAs($other)
        ->put(route('dashboard.testimonials.update', $testimonial), [
            'body' => 'A hijacked wording.',
        ])
        ->assertForbidden();

    expect($testimonial->refresh()->body)->toBe('The original wording.');
});

/*
|--------------------------------------------------------------------------
| A deleted space
|--------------------------------------------------------------------------
*/

test('a testimonial of a soft deleted space cannot be edited', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    $testimonial = Testimonial::factory()->for($space)->create([
        'name' => 'Ada Lovelace',
        'body' => 'The original wording.',
    ]);

    $space->delete();

    $this->actingAs($user)
        ->get(route('dashboard.testimonials.edit', $testimonial))
        ->assertForbidden();
});

test('a testimonial of a soft deleted space cannot be saved', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    $testimonial = Testimonial::factory()->for($space)->create([
        'name' => 'Ada Lovelace',
        'body' => 'The original wording.',
    ]);

    $space->delete();

    $this->actingAs($user)
        ->put(route('dashboard.testimonials.update', $testimonial), [
            'body' => 'A wording that should not be saved.',
        ])
        ->assertForbidden();

    expect($testimonial->refresh()->body)->toBe('The original wording.');
});

/*
|--------------------------------------------------------------------------
| Missing testimonials
|--------------------------------------------------------------------------
*/

test('opening the edit form of a testimonial that does not exist is not found', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);

    $this->actingAs($user)
        ->get(route('dashboard.testimonials.edit', 999999))
        ->assertNotFound();
});

test('saving an edit of a testimonial that does not exist is not found', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);

    $this->actingAs($user)
        ->put(route('dashboard.testimonials.update', 999999), [
            'body' => 'A wording for a testimonial that is not there.',
        ])
        ->assertNotFound();
});

test('opening the edit form with a non numeric id is not found', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);

    $this->actingAs($user)
        ->get(route('dashboard.testimonials.edit', 'not-a-number'))
        ->assertNotFound();
});

test('saving an edit with a non numeric id is not found', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);

    $this->actingAs($user)
        ->put(route('dashboard.testimonials.update', 'not-a-number'), [
            'body' => 'A wording for a testimonial that is not there.',
        ])
        ->assertNotFound();
});
