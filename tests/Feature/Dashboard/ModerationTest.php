<?php

use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;

beforeEach(function () {
    seedPlans();
});

/*
 * The inbox carries four moderation actions: approve, hide, favourite and
 * heart. Each one flips a single flag on a single testimonial and sends
 * the owner back to the inbox with a short message.
 *
 * Every action is guarded by the testimonial policy, which resolves
 * ownership through the space. A testimonial whose space belongs to
 * another account, or whose space has been soft deleted, is refused.
 *
 * A note on names. The layout renders the name of the signed in user, and
 * the name of the space appears as a badge on every testimonial row. Both
 * factories generate random names, so every fixture here pins an explicit
 * name. Without that, a random name could collide with a name a test
 * asserts is absent, and the assertion would fail for the wrong reason.
 */

/*
|--------------------------------------------------------------------------
| Approving
|--------------------------------------------------------------------------
*/

test('a testimonial can be approved', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    $testimonial = Testimonial::factory()->for($space)->create([
        'name' => 'Ada Lovelace',
        'approved' => false,
    ]);

    $this->actingAs($user)
        ->post(route('dashboard.testimonials.approve', $testimonial))
        ->assertRedirect(route('dashboard.inbox'))
        ->assertSessionHas('status', 'The testimonial has been approved.');

    expect($testimonial->refresh()->approved)->toBeTrue();
});

test('an approved testimonial can have its approval withdrawn', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    $testimonial = Testimonial::factory()->for($space)->approved()->create([
        'name' => 'Ada Lovelace',
    ]);

    $this->actingAs($user)
        ->post(route('dashboard.testimonials.approve', $testimonial))
        ->assertRedirect(route('dashboard.inbox'))
        ->assertSessionHas('status', 'The approval has been withdrawn.');

    expect($testimonial->refresh()->approved)->toBeFalse();
});

test('approving a testimonial leaves the other flags alone', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    $testimonial = Testimonial::factory()->for($space)->create([
        'name' => 'Ada Lovelace',
        'approved' => false,
        'hidden' => true,
        'favorite' => true,
        'hearted' => true,
        'consented' => true,
    ]);

    $this->actingAs($user)
        ->post(route('dashboard.testimonials.approve', $testimonial));

    $testimonial->refresh();

    expect($testimonial->approved)->toBeTrue()
        ->and($testimonial->getAttribute('hidden'))->toBeTrue()
        ->and($testimonial->favorite)->toBeTrue()
        ->and($testimonial->hearted)->toBeTrue()
        ->and($testimonial->consented)->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Hiding
|--------------------------------------------------------------------------
*/

test('a testimonial can be hidden', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    $testimonial = Testimonial::factory()->for($space)->create([
        'name' => 'Ada Lovelace',
        'hidden' => false,
    ]);

    $this->actingAs($user)
        ->post(route('dashboard.testimonials.hide', $testimonial))
        ->assertRedirect(route('dashboard.inbox'))
        ->assertSessionHas('status', 'The testimonial is now hidden.');

    expect($testimonial->refresh()->getAttribute('hidden'))->toBeTrue();
});

test('a hidden testimonial can be made visible again', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    $testimonial = Testimonial::factory()->for($space)->hidden()->create([
        'name' => 'Ada Lovelace',
    ]);

    $this->actingAs($user)
        ->post(route('dashboard.testimonials.hide', $testimonial))
        ->assertRedirect(route('dashboard.inbox'))
        ->assertSessionHas('status', 'The testimonial is visible again.');

    expect($testimonial->refresh()->getAttribute('hidden'))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Favouriting
|--------------------------------------------------------------------------
*/

test('a testimonial can be made a favourite', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    $testimonial = Testimonial::factory()->for($space)->create([
        'name' => 'Ada Lovelace',
        'favorite' => false,
    ]);

    $this->actingAs($user)
        ->post(route('dashboard.testimonials.favorite', $testimonial))
        ->assertRedirect(route('dashboard.inbox'))
        ->assertSessionHas('status', 'The testimonial is now a favourite.');

    expect($testimonial->refresh()->favorite)->toBeTrue();
});

test('a favourite testimonial can be unfavourited', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    $testimonial = Testimonial::factory()->for($space)->favorited()->create([
        'name' => 'Ada Lovelace',
    ]);

    $this->actingAs($user)
        ->post(route('dashboard.testimonials.favorite', $testimonial))
        ->assertRedirect(route('dashboard.inbox'))
        ->assertSessionHas('status', 'The testimonial is no longer a favourite.');

    expect($testimonial->refresh()->favorite)->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| The Wall of Love
|--------------------------------------------------------------------------
*/

test('a testimonial can be added to the wall', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    $testimonial = Testimonial::factory()->for($space)->create([
        'name' => 'Ada Lovelace',
        'hearted' => false,
    ]);

    $this->actingAs($user)
        ->post(route('dashboard.testimonials.heart', $testimonial))
        ->assertRedirect(route('dashboard.inbox'))
        ->assertSessionHas('status', 'The testimonial has been added to your Wall of Love.');

    expect($testimonial->refresh()->hearted)->toBeTrue();
});

test('a hearted testimonial can be removed from the wall', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    $testimonial = Testimonial::factory()->for($space)->hearted()->create([
        'name' => 'Ada Lovelace',
    ]);

    $this->actingAs($user)
        ->post(route('dashboard.testimonials.heart', $testimonial))
        ->assertRedirect(route('dashboard.inbox'))
        ->assertSessionHas('status', 'The testimonial has been removed from your Wall of Love.');

    expect($testimonial->refresh()->hearted)->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Guests
|--------------------------------------------------------------------------
*/

test('a guest cannot moderate a testimonial', function (string $action) {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    $testimonial = Testimonial::factory()->for($space)->create([
        'name' => 'Ada Lovelace',
        'approved' => false,
        'hidden' => false,
        'favorite' => false,
        'hearted' => false,
    ]);

    $this->post(route("dashboard.testimonials.{$action}", $testimonial))
        ->assertRedirect(route('login'));

    $testimonial->refresh();

    expect($testimonial->approved)->toBeFalse()
        ->and($testimonial->getAttribute('hidden'))->toBeFalse()
        ->and($testimonial->favorite)->toBeFalse()
        ->and($testimonial->hearted)->toBeFalse();
})->with(['approve', 'hide', 'favorite', 'heart']);

/*
|--------------------------------------------------------------------------
| Another account
|--------------------------------------------------------------------------
*/

// A testimonial of another account is reported as missing rather than as
// forbidden, so that the response does not reveal that the id belongs to a
// real testimonial.
test('another account cannot moderate a testimonial', function (string $action) {
    $owner = User::factory()->create(['name' => 'Inbox Owner']);
    $other = User::factory()->create(['name' => 'Foreign Owner']);

    $space = Space::factory()->for($owner)->create(['name' => 'Owned Space']);

    $testimonial = Testimonial::factory()->for($space)->create([
        'name' => 'Ada Lovelace',
        'approved' => false,
        'hidden' => false,
        'favorite' => false,
        'hearted' => false,
    ]);

    $this->actingAs($other)
        ->post(route("dashboard.testimonials.{$action}", $testimonial))
        ->assertNotFound();

    $testimonial->refresh();

    expect($testimonial->approved)->toBeFalse()
        ->and($testimonial->getAttribute('hidden'))->toBeFalse()
        ->and($testimonial->favorite)->toBeFalse()
        ->and($testimonial->hearted)->toBeFalse();
})->with(['approve', 'hide', 'favorite', 'heart']);

/*
|--------------------------------------------------------------------------
| A deleted space
|--------------------------------------------------------------------------
*/

test('a testimonial of a soft deleted space cannot be moderated', function (string $action) {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    $testimonial = Testimonial::factory()->for($space)->create([
        'name' => 'Ada Lovelace',
        'approved' => false,
        'hidden' => false,
        'favorite' => false,
        'hearted' => false,
    ]);

    $space->delete();

    $this->actingAs($user)
        ->post(route("dashboard.testimonials.{$action}", $testimonial))
        ->assertNotFound();

    $testimonial->refresh();

    expect($testimonial->approved)->toBeFalse()
        ->and($testimonial->getAttribute('hidden'))->toBeFalse()
        ->and($testimonial->favorite)->toBeFalse()
        ->and($testimonial->hearted)->toBeFalse();
})->with(['approve', 'hide', 'favorite', 'heart']);

/*
|--------------------------------------------------------------------------
| Missing testimonials
|--------------------------------------------------------------------------
*/

test('moderating a testimonial that does not exist is not found', function (string $action) {
    $user = User::factory()->create(['name' => 'Inbox Owner']);

    $this->actingAs($user)
        ->post(route("dashboard.testimonials.{$action}", 999999))
        ->assertNotFound();
})->with(['approve', 'hide', 'favorite', 'heart']);

test('moderating a testimonial with a non numeric id is not found', function (string $action) {
    $user = User::factory()->create(['name' => 'Inbox Owner']);

    $this->actingAs($user)
        ->post(route("dashboard.testimonials.{$action}", 'not-a-number'))
        ->assertNotFound();
})->with(['approve', 'hide', 'favorite', 'heart']);
