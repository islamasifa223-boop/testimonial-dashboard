<?php

use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;

beforeEach(function () {
    seedPlans();
});

/*
|--------------------------------------------------------------------------
| The dashboard Wall of Love page
|--------------------------------------------------------------------------
|
| The dashboard wall page shows the account's spaces, the number of
| testimonials each of them has on its public wall, and the hearted
| testimonials that are missing from the public wall.
|
| The page is a reading surface, so the tests drive the real route and
| render the real view. The controller, the model scopes and the Blade
| template are covered together.
|
| A note on names. The layout renders the name of the signed in user, and
| the space name appears on every wall card. Both factories generate
| random names, so every fixture here pins an explicit name. Without
| that, a random name could collide with a name a test asserts is
| absent, and the assertion would fail for the wrong reason.
|
*/

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * A space owned by the given account.
 *
 * The slug is pinned so the wall path on the page is predictable.
 */
function wallDashboardSpace(User $user, array $attributes = []): Space
{
    return Space::factory()->for($user)->create(array_merge([
        'name' => 'Owned Space',
        'slug' => 'owned-space',
    ], $attributes));
}

/**
 * A testimonial of the given space.
 *
 * The state is left at the factory default unless a test changes it, so
 * a test says exactly which condition it is breaking.
 */
function wallDashboardTestimonial(Space $space, array $attributes = []): Testimonial
{
    return Testimonial::factory()->for($space)->create(array_merge([
        'name' => 'Ada Lovelace',
    ], $attributes));
}

/*
|--------------------------------------------------------------------------
| The spaces
|--------------------------------------------------------------------------
*/

test('the wall page lists the spaces of the signed in account', function () {
    $user = User::factory()->create(['name' => 'Wall Owner']);

    wallDashboardSpace($user, ['name' => 'First Space']);
    wallDashboardSpace($user, ['name' => 'Second Space', 'slug' => 'second-space']);

    $this->actingAs($user)
        ->get(route('dashboard.wall'))
        ->assertOk()
        ->assertSee('First Space')
        ->assertSee('Second Space');
});

test('the wall page shows the public wall path of a space', function () {
    $user = User::factory()->create(['name' => 'Wall Owner']);

    wallDashboardSpace($user, ['slug' => 'acme-wall']);

    $this->actingAs($user)
        ->get(route('dashboard.wall'))
        ->assertOk()
        ->assertSee('/wall/acme-wall');
});

test('the wall page links to the public wall of a space', function () {
    $user = User::factory()->create(['name' => 'Wall Owner']);

    $space = wallDashboardSpace($user, ['slug' => 'acme-wall']);

    $this->actingAs($user)
        ->get(route('dashboard.wall'))
        ->assertOk()
        ->assertSee(route('walls.show', $space->slug), false);
});

test('the wall page does not show the spaces of another account', function () {
    $user = User::factory()->create(['name' => 'Wall Owner']);
    $foreign = User::factory()->create(['name' => 'Foreign Owner']);

    wallDashboardSpace($user, ['name' => 'Owned Space']);
    wallDashboardSpace($foreign, ['name' => 'Foreign Space', 'slug' => 'foreign-space']);

    $this->actingAs($user)
        ->get(route('dashboard.wall'))
        ->assertOk()
        ->assertSee('Owned Space')
        ->assertDontSee('Foreign Space')
        ->assertDontSee('/wall/foreign-space');
});

test('the wall page does not show a soft deleted space', function () {
    $user = User::factory()->create(['name' => 'Wall Owner']);

    $space = wallDashboardSpace($user, ['name' => 'Deleted Space', 'slug' => 'deleted-space']);

    $space->delete();

    $this->actingAs($user)
        ->get(route('dashboard.wall'))
        ->assertOk()
        ->assertDontSee('Deleted Space')
        ->assertDontSee('/wall/deleted-space');
});

/*
|--------------------------------------------------------------------------
| The empty state
|--------------------------------------------------------------------------
*/

test('the wall page shows the empty state when the account has no space', function () {
    $user = User::factory()->create(['name' => 'Wall Owner']);

    $this->actingAs($user)
        ->get(route('dashboard.wall'))
        ->assertOk()
        ->assertSee("You don't have any spaces yet");
});

test('the wall page does not show the empty state when the account has a space', function () {
    $user = User::factory()->create(['name' => 'Wall Owner']);

    wallDashboardSpace($user);

    $this->actingAs($user)
        ->get(route('dashboard.wall'))
        ->assertOk()
        ->assertDontSee("You don't have any spaces yet");
});

/*
|--------------------------------------------------------------------------
| The eligible count
|--------------------------------------------------------------------------
*/

test('the wall page counts the testimonials that are on the wall', function () {
    $user = User::factory()->create(['name' => 'Wall Owner']);

    $space = wallDashboardSpace($user);

    wallDashboardTestimonial($space, ['approved' => true, 'consented' => true, 'hearted' => true]);
    wallDashboardTestimonial($space, ['approved' => true, 'consented' => true, 'hearted' => true]);

    $this->actingAs($user)
        ->get(route('dashboard.wall'))
        ->assertOk()
        ->assertSee('2 on wall');
});

test('a space with no eligible testimonial counts zero', function () {
    $user = User::factory()->create(['name' => 'Wall Owner']);

    $space = wallDashboardSpace($user);

    wallDashboardTestimonial($space, ['approved' => true, 'consented' => true, 'hearted' => false]);

    $this->actingAs($user)
        ->get(route('dashboard.wall'))
        ->assertOk()
        ->assertSee('0 on wall');
});

test('the count ignores a testimonial that breaks the wall rule', function () {
    $user = User::factory()->create(['name' => 'Wall Owner']);

    $space = wallDashboardSpace($user);

    wallDashboardTestimonial($space, ['approved' => true, 'consented' => true, 'hearted' => true]);
    wallDashboardTestimonial($space, ['approved' => false, 'consented' => true, 'hearted' => true]);
    wallDashboardTestimonial($space, ['approved' => true, 'consented' => true, 'hearted' => true, 'hidden' => true]);
    wallDashboardTestimonial($space, ['approved' => true, 'consented' => false, 'hearted' => true]);

    $this->actingAs($user)
        ->get(route('dashboard.wall'))
        ->assertOk()
        ->assertSee('1 on wall')
        ->assertDontSee('2 on wall')
        ->assertDontSee('3 on wall');
});

test('the count is per space', function () {
    $user = User::factory()->create(['name' => 'Wall Owner']);

    $first = wallDashboardSpace($user, ['slug' => 'first-space']);
    $second = wallDashboardSpace($user, ['name' => 'Second Space', 'slug' => 'second-space']);

    wallDashboardTestimonial($first, ['approved' => true, 'consented' => true, 'hearted' => true]);
    wallDashboardTestimonial($first, ['approved' => true, 'consented' => true, 'hearted' => true]);
    wallDashboardTestimonial($second, ['approved' => true, 'consented' => true, 'hearted' => true]);

    $this->actingAs($user)
        ->get(route('dashboard.wall'))
        ->assertOk()
        ->assertSee('2 on wall')
        ->assertSee('1 on wall');
});

test('the count ignores the testimonials of another account', function () {
    $user = User::factory()->create(['name' => 'Wall Owner']);
    $foreign = User::factory()->create(['name' => 'Foreign Owner']);

    $space = wallDashboardSpace($user);
    $foreignSpace = wallDashboardSpace($foreign, ['name' => 'Foreign Space', 'slug' => 'foreign-space']);

    wallDashboardTestimonial($space, ['approved' => true, 'consented' => true, 'hearted' => true]);
    wallDashboardTestimonial($foreignSpace, ['approved' => true, 'consented' => true, 'hearted' => true]);

    $this->actingAs($user)
        ->get(route('dashboard.wall'))
        ->assertOk()
        ->assertSee('1 on wall')
        ->assertDontSee('2 on wall');
});

/*
|--------------------------------------------------------------------------
| The missing list
|--------------------------------------------------------------------------
*/

test('a hearted testimonial that is missing from the wall is listed', function () {
    $user = User::factory()->create(['name' => 'Wall Owner']);

    $space = wallDashboardSpace($user);

    wallDashboardTestimonial($space, ['approved' => false, 'consented' => true, 'hearted' => true]);

    $this->actingAs($user)
        ->get(route('dashboard.wall'))
        ->assertOk()
        ->assertSee('Hearts that are not on the wall')
        ->assertSee('Ada Lovelace');
});

test('a testimonial that is missing from the wall lists the not approved reason', function () {
    $user = User::factory()->create(['name' => 'Wall Owner']);

    $space = wallDashboardSpace($user);

    wallDashboardTestimonial($space, ['approved' => false, 'consented' => true, 'hearted' => true]);

    $this->actingAs($user)
        ->get(route('dashboard.wall'))
        ->assertOk()
        ->assertSee('Not approved');
});

test('a testimonial that is missing from the wall lists the hidden reason', function () {
    $user = User::factory()->create(['name' => 'Wall Owner']);

    $space = wallDashboardSpace($user);

    wallDashboardTestimonial($space, ['approved' => true, 'consented' => true, 'hearted' => true, 'hidden' => true]);

    $this->actingAs($user)
        ->get(route('dashboard.wall'))
        ->assertOk()
        ->assertSee('Hidden');
});

test('a testimonial that is missing from the wall lists the no consent reason', function () {
    $user = User::factory()->create(['name' => 'Wall Owner']);

    $space = wallDashboardSpace($user);

    wallDashboardTestimonial($space, ['approved' => true, 'consented' => false, 'hearted' => true]);

    $this->actingAs($user)
        ->get(route('dashboard.wall'))
        ->assertOk()
        ->assertSee('No consent');
});

test('a testimonial that breaks several conditions lists every reason', function () {
    $user = User::factory()->create(['name' => 'Wall Owner']);

    $space = wallDashboardSpace($user);

    wallDashboardTestimonial($space, ['approved' => false, 'consented' => false, 'hearted' => true, 'hidden' => true]);

    $this->actingAs($user)
        ->get(route('dashboard.wall'))
        ->assertOk()
        ->assertSee('Not approved')
        ->assertSee('Hidden')
        ->assertSee('No consent');
});

test('a testimonial that is on the wall is not listed as missing', function () {
    $user = User::factory()->create(['name' => 'Wall Owner']);

    $space = wallDashboardSpace($user);

    wallDashboardTestimonial($space, ['approved' => true, 'consented' => true, 'hearted' => true]);

    $this->actingAs($user)
        ->get(route('dashboard.wall'))
        ->assertOk()
        ->assertDontSee('Hearts that are not on the wall');
});

test('a testimonial without a heart is not listed as missing', function () {
    $user = User::factory()->create(['name' => 'Wall Owner']);

    $space = wallDashboardSpace($user);

    wallDashboardTestimonial($space, ['approved' => false, 'consented' => true, 'hearted' => false]);

    $this->actingAs($user)
        ->get(route('dashboard.wall'))
        ->assertOk()
        ->assertDontSee('Hearts that are not on the wall')
        ->assertDontSee('Not approved');
});

test('the missing list is not shown when every hearted testimonial is on the wall', function () {
    $user = User::factory()->create(['name' => 'Wall Owner']);

    $space = wallDashboardSpace($user);

    wallDashboardTestimonial($space, ['approved' => true, 'consented' => true, 'hearted' => true]);

    $this->actingAs($user)
        ->get(route('dashboard.wall'))
        ->assertOk()
        ->assertDontSee('Hearts that are not on the wall');
});

test('the missing list does not show the hearted testimonials of another account', function () {
    $user = User::factory()->create(['name' => 'Wall Owner']);
    $foreign = User::factory()->create(['name' => 'Foreign Owner']);

    $foreignSpace = wallDashboardSpace($foreign, ['name' => 'Foreign Space', 'slug' => 'foreign-space']);

    wallDashboardTestimonial($foreignSpace, [
        'name' => 'Grace Hopper',
        'approved' => false,
        'consented' => true,
        'hearted' => true,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.wall'))
        ->assertOk()
        ->assertDontSee('Grace Hopper')
        ->assertDontSee('Hearts that are not on the wall');
});

test('the missing list does not show the hearted testimonials of a soft deleted space', function () {
    $user = User::factory()->create(['name' => 'Wall Owner']);

    $space = wallDashboardSpace($user, ['name' => 'Deleted Space', 'slug' => 'deleted-space']);

    wallDashboardTestimonial($space, [
        'name' => 'Ada Lovelace',
        'approved' => false,
        'consented' => true,
        'hearted' => true,
    ]);

    $space->delete();

    $this->actingAs($user)
        ->get(route('dashboard.wall'))
        ->assertOk()
        ->assertDontSee('Ada Lovelace')
        ->assertDontSee('Hearts that are not on the wall');
});

/*
|--------------------------------------------------------------------------
| The private data
|--------------------------------------------------------------------------
*/

test('the wall page does not show the customer email of a missing testimonial', function () {
    $user = User::factory()->create(['name' => 'Wall Owner']);

    $space = wallDashboardSpace($user);

    wallDashboardTestimonial($space, [
        'email' => 'ada@example.com',
        'approved' => false,
        'consented' => true,
        'hearted' => true,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.wall'))
        ->assertOk()
        ->assertDontSee('ada@example.com');
});

test('the wall page does not show the body of a missing testimonial', function () {
    $user = User::factory()->create(['name' => 'Wall Owner']);

    $space = wallDashboardSpace($user);

    wallDashboardTestimonial($space, [
        'body' => 'A wonderfully ordinary testimonial.',
        'approved' => false,
        'consented' => true,
        'hearted' => true,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard.wall'))
        ->assertOk()
        ->assertDontSee('A wonderfully ordinary testimonial.');
});
