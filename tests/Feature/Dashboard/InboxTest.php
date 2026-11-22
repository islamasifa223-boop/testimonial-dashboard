<?php

use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;

beforeEach(function () {
    seedPlans();
});

/*
 * The dashboard inbox lists the testimonials of the spaces the signed in
 * account owns, and lets them be narrowed down by search term, space,
 * status and rating.
 *
 * These tests drive the real routes and render the real views, so the
 * controller, the model scopes and the Blade template are covered
 * together.
 *
 * A note on names. The layout renders the name of the signed in user, and
 * the name of the space appears as a badge on every testimonial row. Both
 * factories generate random names, so every fixture here pins an explicit
 * name. Without that, a random name could collide with a name a test
 * asserts is absent, and the assertion would fail for the wrong reason.
 */

test('the inbox shows only the testimonials of the spaces the user owns', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $foreign = User::factory()->create(['name' => 'Foreign Owner']);

    $ownedSpace = Space::factory()->for($user)->create(['name' => 'Owned Space']);
    $foreignSpace = Space::factory()->for($foreign)->create(['name' => 'Foreign Space']);

    Testimonial::factory()->for($ownedSpace)->create(['name' => 'Ada Lovelace']);
    Testimonial::factory()->for($foreignSpace)->create(['name' => 'Grace Hopper']);

    $this->actingAs($user)
        ->get(route('dashboard.inbox'))
        ->assertOk()
        ->assertSee('Ada Lovelace')
        ->assertDontSee('Grace Hopper');
});

test('the inbox is empty when the user owns no space', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);

    Testimonial::factory()->create(['name' => 'Ada Lovelace']);

    $this->actingAs($user)
        ->get(route('dashboard.inbox'))
        ->assertOk()
        ->assertSee('No testimonials yet')
        ->assertDontSee('Ada Lovelace');
});

test('the inbox shows a pending testimonial with the pending badge', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    Testimonial::factory()->for($space)->create([
        'name' => 'Ada Lovelace',
        'approved' => false,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.inbox'))
        ->assertOk()
        ->assertSee('Ada Lovelace')
        ->assertSee('Pending');
});

test('the inbox shows an approved testimonial with the approved badge', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    Testimonial::factory()->for($space)->approved()->create([
        'name' => 'Ada Lovelace',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.inbox'))
        ->assertOk()
        ->assertSee('Ada Lovelace')
        ->assertSee('Approved');
});

test('the inbox excludes the testimonials of a soft deleted space', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    Testimonial::factory()->for($space)->create(['name' => 'Ada Lovelace']);

    $space->delete();

    $this->actingAs($user)
        ->get(route('dashboard.inbox'))
        ->assertOk()
        ->assertSee('No testimonials yet')
        ->assertDontSee('Ada Lovelace');
});

test('the search finds a testimonial by the name of the customer', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    Testimonial::factory()->for($space)->create([
        'name' => 'Ada Lovelace',
        'company_name' => 'Analytical Engines',
    ]);
    Testimonial::factory()->for($space)->create([
        'name' => 'Grace Hopper',
        'company_name' => 'Compilers',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.inbox', ['q' => 'Ada']))
        ->assertOk()
        ->assertSee('Ada Lovelace')
        ->assertDontSee('Grace Hopper');
});

test('the search finds a testimonial by the company of the customer', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    Testimonial::factory()->for($space)->create([
        'name' => 'Ada Lovelace',
        'company_name' => 'Analytical Engines',
    ]);
    Testimonial::factory()->for($space)->create([
        'name' => 'Grace Hopper',
        'company_name' => 'Compilers',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.inbox', ['q' => 'Compilers']))
        ->assertOk()
        ->assertSee('Grace Hopper')
        ->assertDontSee('Ada Lovelace');
});

test('the search finds a testimonial by the words of the customer', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    Testimonial::factory()->for($space)->create([
        'name' => 'Ada Lovelace',
        'body' => 'The onboarding was a joy.',
    ]);
    Testimonial::factory()->for($space)->create([
        'name' => 'Grace Hopper',
        'body' => 'Everything worked as promised.',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.inbox', ['q' => 'onboarding']))
        ->assertOk()
        ->assertSee('Ada Lovelace')
        ->assertDontSee('Grace Hopper');
});

test('a search term that matches nothing empties the inbox', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    Testimonial::factory()->for($space)->create(['name' => 'Ada Lovelace']);

    $this->actingAs($user)
        ->get(route('dashboard.inbox', ['q' => 'nothingmatchesthis']))
        ->assertOk()
        ->assertSee('No testimonials match your filters')
        ->assertDontSee('Ada Lovelace');
});

test('a search term of only spaces shows every testimonial', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    Testimonial::factory()->for($space)->create(['name' => 'Ada Lovelace']);
    Testimonial::factory()->for($space)->create(['name' => 'Grace Hopper']);

    $this->actingAs($user)
        ->get(route('dashboard.inbox', ['q' => '   ']))
        ->assertOk()
        ->assertSee('Ada Lovelace')
        ->assertSee('Grace Hopper');
});

test('the space filter shows only the testimonials of the chosen space', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);

    $firstSpace = Space::factory()->for($user)->create(['name' => 'First Space']);
    $secondSpace = Space::factory()->for($user)->create(['name' => 'Second Space']);

    Testimonial::factory()->for($firstSpace)->create(['name' => 'Ada Lovelace']);
    Testimonial::factory()->for($secondSpace)->create(['name' => 'Grace Hopper']);

    $this->actingAs($user)
        ->get(route('dashboard.inbox', ['space' => $firstSpace->id]))
        ->assertOk()
        ->assertSee('Ada Lovelace')
        ->assertDontSee('Grace Hopper');
});

test('the space filter shows every testimonial when no space is chosen', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);

    $firstSpace = Space::factory()->for($user)->create(['name' => 'First Space']);
    $secondSpace = Space::factory()->for($user)->create(['name' => 'Second Space']);

    Testimonial::factory()->for($firstSpace)->create(['name' => 'Ada Lovelace']);
    Testimonial::factory()->for($secondSpace)->create(['name' => 'Grace Hopper']);

    $this->actingAs($user)
        ->get(route('dashboard.inbox'))
        ->assertOk()
        ->assertSee('Ada Lovelace')
        ->assertSee('Grace Hopper');
});

test('a space the user does not own never appears in the space filter', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $foreign = User::factory()->create(['name' => 'Foreign Owner']);

    $ownedSpace = Space::factory()->for($user)->create(['name' => 'Owned Space']);
    $foreignSpace = Space::factory()->for($foreign)->create(['name' => 'Foreign Space']);

    Testimonial::factory()->for($ownedSpace)->create(['name' => 'Ada Lovelace']);
    Testimonial::factory()->for($foreignSpace)->create(['name' => 'Grace Hopper']);

    $this->actingAs($user)
        ->get(route('dashboard.inbox'))
        ->assertOk()
        ->assertSee('Owned Space')
        ->assertDontSee('Foreign Space');
});

test('the space filter of another account empties the inbox', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $foreign = User::factory()->create(['name' => 'Foreign Owner']);

    $ownedSpace = Space::factory()->for($user)->create(['name' => 'Owned Space']);
    $foreignSpace = Space::factory()->for($foreign)->create(['name' => 'Foreign Space']);

    Testimonial::factory()->for($ownedSpace)->create(['name' => 'Ada Lovelace']);
    Testimonial::factory()->for($foreignSpace)->create(['name' => 'Grace Hopper']);

    $this->actingAs($user)
        ->get(route('dashboard.inbox', ['space' => $foreignSpace->id]))
        ->assertOk()
        ->assertSee('No testimonials match your filters')
        ->assertDontSee('Ada Lovelace')
        ->assertDontSee('Grace Hopper');
});

test('a space that does not exist empties the inbox', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    Testimonial::factory()->for($space)->create(['name' => 'Ada Lovelace']);

    $this->actingAs($user)
        ->get(route('dashboard.inbox', ['space' => 999999]))
        ->assertOk()
        ->assertSee('No testimonials match your filters')
        ->assertDontSee('Ada Lovelace');
});

test('the pending status shows only the testimonials awaiting approval', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    Testimonial::factory()->for($space)->create([
        'name' => 'Ada Lovelace',
        'approved' => false,
    ]);
    Testimonial::factory()->for($space)->approved()->create([
        'name' => 'Grace Hopper',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.inbox', ['status' => 'pending']))
        ->assertOk()
        ->assertSee('Ada Lovelace')
        ->assertDontSee('Grace Hopper');
});

test('the approved status shows only the approved testimonials', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    Testimonial::factory()->for($space)->approved()->create([
        'name' => 'Ada Lovelace',
    ]);
    Testimonial::factory()->for($space)->create([
        'name' => 'Grace Hopper',
        'approved' => false,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.inbox', ['status' => 'approved']))
        ->assertOk()
        ->assertSee('Ada Lovelace')
        ->assertDontSee('Grace Hopper');
});

test('the hidden status shows only the hidden testimonials', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    Testimonial::factory()->for($space)->hidden()->create([
        'name' => 'Ada Lovelace',
    ]);
    Testimonial::factory()->for($space)->create([
        'name' => 'Grace Hopper',
        'hidden' => false,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.inbox', ['status' => 'hidden']))
        ->assertOk()
        ->assertSee('Ada Lovelace')
        ->assertDontSee('Grace Hopper');
});

test('the favourite status shows only the favourite testimonials', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    Testimonial::factory()->for($space)->favorited()->create([
        'name' => 'Ada Lovelace',
    ]);
    Testimonial::factory()->for($space)->create([
        'name' => 'Grace Hopper',
        'favorite' => false,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.inbox', ['status' => 'favorite']))
        ->assertOk()
        ->assertSee('Ada Lovelace')
        ->assertDontSee('Grace Hopper');
});

test('the on wall status shows only the fully eligible testimonials', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    // Every condition of the public visibility rule holds.
    Testimonial::factory()->for($space)->approved()->consented()->hearted()->create([
        'name' => 'Ada Lovelace',
    ]);

    // Each of the following breaks exactly one of those conditions.
    Testimonial::factory()->for($space)->consented()->hearted()->create([
        'name' => 'Not Approved',
        'approved' => false,
    ]);
    Testimonial::factory()->for($space)->approved()->consented()->hidden()->hearted()->create([
        'name' => 'Hidden One',
    ]);
    Testimonial::factory()->for($space)->approved()->hearted()->create([
        'name' => 'Not Consented',
        'consented' => false,
    ]);
    Testimonial::factory()->for($space)->approved()->consented()->create([
        'name' => 'Not Hearted',
        'hearted' => false,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.inbox', ['status' => 'on_wall']))
        ->assertOk()
        ->assertSee('Ada Lovelace')
        ->assertDontSee('Not Approved')
        ->assertDontSee('Hidden One')
        ->assertDontSee('Not Consented')
        ->assertDontSee('Not Hearted');
});

test('an empty status shows every testimonial', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    Testimonial::factory()->for($space)->approved()->create(['name' => 'Ada Lovelace']);
    Testimonial::factory()->for($space)->create(['name' => 'Grace Hopper']);

    $this->actingAs($user)
        ->get(route('dashboard.inbox', ['status' => '']))
        ->assertOk()
        ->assertSee('Ada Lovelace')
        ->assertSee('Grace Hopper');
});

test('the rating filter shows only the testimonials with that rating', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    Testimonial::factory()->for($space)->create([
        'name' => 'Ada Lovelace',
        'rating' => 5,
    ]);
    Testimonial::factory()->for($space)->create([
        'name' => 'Grace Hopper',
        'rating' => 2,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.inbox', ['rating' => 5]))
        ->assertOk()
        ->assertSee('Ada Lovelace')
        ->assertDontSee('Grace Hopper');
});

test('a rating of zero shows only the testimonials without a rating', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    Testimonial::factory()->for($space)->create([
        'name' => 'Ada Lovelace',
        'rating' => null,
    ]);
    Testimonial::factory()->for($space)->create([
        'name' => 'Grace Hopper',
        'rating' => 4,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.inbox', ['rating' => 0]))
        ->assertOk()
        ->assertSee('Ada Lovelace')
        ->assertDontSee('Grace Hopper');
});

test('a rating outside the scale does not filter the inbox', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    Testimonial::factory()->for($space)->create([
        'name' => 'Ada Lovelace',
        'rating' => 5,
    ]);
    Testimonial::factory()->for($space)->create([
        'name' => 'Grace Hopper',
        'rating' => 2,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.inbox', ['rating' => 9]))
        ->assertOk()
        ->assertSee('Ada Lovelace')
        ->assertSee('Grace Hopper');
});

test('the favourites are listed before the other testimonials', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    Testimonial::factory()->for($space)->create([
        'name' => 'Ada Lovelace',
        'favorite' => false,
    ]);
    Testimonial::factory()->for($space)->favorited()->create([
        'name' => 'Grace Hopper',
    ]);

    $response = $this->actingAs($user)->get(route('dashboard.inbox'));

    $response->assertOk();

    $content = $response->getContent();

    $ada = positionOf($content, 'Ada Lovelace');
    $grace = positionOf($content, 'Grace Hopper');

    expect($ada)->toBeGreaterThan(-1)
        ->and($grace)->toBeGreaterThan(-1)
        ->and($grace)->toBeLessThan($ada);
});

test('the newest testimonial is listed first', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    Testimonial::factory()->for($space)->create([
        'name' => 'Ada Lovelace',
        'created_at' => now()->subDays(3),
    ]);
    Testimonial::factory()->for($space)->create([
        'name' => 'Grace Hopper',
        'created_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($user)->get(route('dashboard.inbox'));

    $response->assertOk();

    $content = $response->getContent();

    $ada = positionOf($content, 'Ada Lovelace');
    $grace = positionOf($content, 'Grace Hopper');

    expect($ada)->toBeGreaterThan(-1)
        ->and($grace)->toBeGreaterThan(-1)
        ->and($grace)->toBeLessThan($ada);
});

test('the inbox paginates the testimonials', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    Testimonial::factory()->count(30)->for($space)->create();

    $response = $this->actingAs($user)->get(route('dashboard.inbox'));

    $response->assertOk();

    expect($response->viewData('testimonials')->total())->toBe(30)
        ->and($response->viewData('testimonials')->perPage())->toBe(25)
        ->and($response->viewData('testimonials')->count())->toBe(25);
});

test('the second page of the inbox holds the rest', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    Testimonial::factory()->count(30)->for($space)->create();

    $response = $this->actingAs($user)->get(route('dashboard.inbox', ['page' => 2]));

    $response->assertOk();

    expect($response->viewData('testimonials')->currentPage())->toBe(2)
        ->and($response->viewData('testimonials')->total())->toBe(30)
        ->and($response->viewData('testimonials')->count())->toBe(5);
});

test('the filter form keeps the search term', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);

    $this->actingAs($user)
        ->get(route('dashboard.inbox', ['q' => 'Ada']))
        ->assertOk()
        ->assertSee('value="Ada"', false);
});

test('the filter form marks the chosen status as selected', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);

    $this->actingAs($user)
        ->get(route('dashboard.inbox', ['status' => 'approved']))
        ->assertOk()
        ->assertSee('<option value="approved" selected>Approved</option>', false)
        ->assertDontSee('<option value="pending" selected>Pending</option>', false);
});

test('the filter form marks the chosen rating as selected', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);

    $this->actingAs($user)
        ->get(route('dashboard.inbox', ['rating' => 4]))
        ->assertOk()
        ->assertSee('<option value="4" selected>4 stars</option>', false)
        ->assertDontSee('<option value="5" selected>5 stars</option>', false);
});

test('a rating that is not on the scale selects no rating option', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);

    $this->actingAs($user)
        ->get(route('dashboard.inbox', ['rating' => 9]))
        ->assertOk()
        ->assertDontSee('<option value="5" selected>5 stars</option>', false)
        ->assertDontSee('<option value="4" selected>4 stars</option>', false)
        ->assertDontSee('<option value="3" selected>3 stars</option>', false)
        ->assertDontSee('<option value="2" selected>2 stars</option>', false)
        ->assertDontSee('<option value="1" selected>1 star</option>', false)
        ->assertDontSee('<option value="0" selected>No rating</option>', false);
});

test('an unknown status selects no status option', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);

    $this->actingAs($user)
        ->get(route('dashboard.inbox', ['status' => 'nonsense']))
        ->assertOk()
        ->assertDontSee('<option value="pending" selected>Pending</option>', false)
        ->assertDontSee('<option value="approved" selected>Approved</option>', false)
        ->assertDontSee('<option value="hidden" selected>Hidden</option>', false)
        ->assertDontSee('<option value="on_wall" selected>On the Wall</option>', false)
        ->assertDontSee('<option value="favorite" selected>Favourites</option>', false);
});

/**
 * The position of a name in the rendered page.
 *
 * The offset is -1 when the name is not on the page at all, so a name that
 * never appears can never be mistaken for one that appears early.
 */
function positionOf(string $content, string $name): int
{
    $position = strpos($content, $name);

    return $position === false ? -1 : $position;
}
