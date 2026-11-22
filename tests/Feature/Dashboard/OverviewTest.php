<?php

use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use App\Services\DashboardStatistics;
use App\Services\SubmissionTrend;

beforeEach(function () {
    seedPlans();
});

/*
|--------------------------------------------------------------------------
| The dashboard statistics
|--------------------------------------------------------------------------
|
| The overview page shows four figures about the account's testimonials:
| the total, the number of distinct customers, the number collected this
| month and the average rating.
|
| The figures are produced by a single aggregate query, so they are
| asserted through the service as well as through the page. A test that
| only checked the page would not notice a figure that was right by
| accident.
|
| A note on names. The layout renders the name of the signed in user and
| the name of every space. Both factories generate random names, so every
| fixture here pins an explicit name. Without that, a random name could
| collide with a name a test asserts is absent, and the assertion would
| fail for the wrong reason.
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
 * The slug is pinned so the space path on the page is predictable.
 */
function overviewSpace(User $user, array $attributes = []): Space
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
function overviewTestimonial(Space $space, array $attributes = []): Testimonial
{
    return Testimonial::factory()->for($space)->create(array_merge([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
        'rating' => 5,
    ], $attributes));
}

/*
|--------------------------------------------------------------------------
| The total
|--------------------------------------------------------------------------
*/

test('the total counts the testimonials of the account', function () {
    $user = User::factory()->create(['name' => 'Overview Owner']);
    $space = overviewSpace($user);

    overviewTestimonial($space);
    overviewTestimonial($space);
    overviewTestimonial($space);

    $this->actingAs($user)
        ->get(route('dashboard.overview'))
        ->assertOk()
        ->assertSee('3');
});

test('the total counts the testimonials of every space of the account', function () {
    $user = User::factory()->create(['name' => 'Overview Owner']);

    $first = overviewSpace($user, ['name' => 'First Space', 'slug' => 'first-space']);
    $second = overviewSpace($user, ['name' => 'Second Space', 'slug' => 'second-space']);

    overviewTestimonial($first);
    overviewTestimonial($second);
    overviewTestimonial($second);

    $this->actingAs($user)
        ->get(route('dashboard.overview'))
        ->assertOk()
        ->assertSee('3');
});

test('the total counts a testimonial whatever its moderation state', function () {
    $user = User::factory()->create(['name' => 'Overview Owner']);
    $space = overviewSpace($user);

    overviewTestimonial($space, ['approved' => false, 'consented' => false, 'hidden' => false, 'hearted' => false]);
    overviewTestimonial($space, ['approved' => true, 'consented' => false, 'hidden' => false, 'hearted' => true]);
    overviewTestimonial($space, ['approved' => false, 'consented' => true, 'hidden' => false, 'hearted' => false]);
    overviewTestimonial($space, ['approved' => true, 'consented' => true, 'hidden' => true, 'hearted' => true]);
    overviewTestimonial($space, ['approved' => false, 'consented' => false, 'hidden' => true, 'hearted' => false]);
    overviewTestimonial($space, ['approved' => true, 'consented' => true, 'hidden' => false, 'hearted' => true]);

    expect(app(DashboardStatistics::class)->forUser($user)['totalTestimonials'])->toBe(6);
});

test('the total is zero when the account has no testimonial', function () {
    $user = User::factory()->create(['name' => 'Overview Owner']);

    overviewSpace($user);

    expect(app(DashboardStatistics::class)->forUser($user)['totalTestimonials'])->toBe(0);

    $this->actingAs($user)
        ->get(route('dashboard.overview'))
        ->assertOk();
});

/*
|--------------------------------------------------------------------------
| The unique customers
|--------------------------------------------------------------------------
*/

test('the unique customers count a repeated address once', function () {
    $user = User::factory()->create(['name' => 'Overview Owner']);
    $space = overviewSpace($user);

    overviewTestimonial($space, ['email' => 'ada@example.com']);
    overviewTestimonial($space, ['email' => 'ada@example.com']);
    overviewTestimonial($space, ['email' => 'grace@example.com']);

    expect(app(DashboardStatistics::class)->forUser($user)['uniqueCustomers'])->toBe(2);
});

test('the unique customers count an address once across spaces', function () {
    $user = User::factory()->create(['name' => 'Overview Owner']);

    $first = overviewSpace($user, ['name' => 'First Space', 'slug' => 'first-space']);
    $second = overviewSpace($user, ['name' => 'Second Space', 'slug' => 'second-space']);

    overviewTestimonial($first, ['email' => 'ada@example.com']);
    overviewTestimonial($second, ['email' => 'ada@example.com']);

    expect(app(DashboardStatistics::class)->forUser($user)['uniqueCustomers'])->toBe(1);
});

test('the unique customers count a customer that left several testimonials once', function () {
    $user = User::factory()->create(['name' => 'Overview Owner']);
    $space = overviewSpace($user);

    overviewTestimonial($space, ['email' => 'ada@example.com']);
    overviewTestimonial($space, ['email' => 'ada@example.com']);
    overviewTestimonial($space, ['email' => 'ada@example.com']);

    expect(app(DashboardStatistics::class)->forUser($user)['uniqueCustomers'])->toBe(1);
});

test('the unique customers are zero when the account has no testimonial', function () {
    $user = User::factory()->create(['name' => 'Overview Owner']);

    overviewSpace($user);

    expect(app(DashboardStatistics::class)->forUser($user)['uniqueCustomers'])->toBe(0);
});

/*
|--------------------------------------------------------------------------
| The testimonials of this month
|--------------------------------------------------------------------------
*/

test('the count of this month counts the testimonials of the current month', function () {
    $user = User::factory()->create(['name' => 'Overview Owner']);
    $space = overviewSpace($user);

    overviewTestimonial($space, ['created_at' => now()]);
    overviewTestimonial($space, ['created_at' => now()->startOfMonth()]);
    overviewTestimonial($space, ['created_at' => now()->startOfMonth()->subMonth()]);

    expect(app(DashboardStatistics::class)->forUser($user)['thisMonth'])->toBe(2);
});

test('the count of this month ignores a testimonial from a previous month', function () {
    $user = User::factory()->create(['name' => 'Overview Owner']);
    $space = overviewSpace($user);

    overviewTestimonial($space, ['created_at' => now()->startOfMonth()->subMonth()]);
    overviewTestimonial($space, ['created_at' => now()->subMonths(3)]);
    overviewTestimonial($space, ['created_at' => now()->subYear()]);

    expect(app(DashboardStatistics::class)->forUser($user)['thisMonth'])->toBe(0);
});

test('the count of this month is zero when the account has no testimonial', function () {
    $user = User::factory()->create(['name' => 'Overview Owner']);

    overviewSpace($user);

    expect(app(DashboardStatistics::class)->forUser($user)['thisMonth'])->toBe(0);
});

/*
|--------------------------------------------------------------------------
| The average rating
|--------------------------------------------------------------------------
*/

test('the average rating is the mean of the ratings', function () {
    $user = User::factory()->create(['name' => 'Overview Owner']);
    $space = overviewSpace($user);

    overviewTestimonial($space, ['rating' => 5]);
    overviewTestimonial($space, ['rating' => 4]);
    overviewTestimonial($space, ['rating' => 3]);

    expect(app(DashboardStatistics::class)->forUser($user)['averageRating'])->toBe(4.0);
});

test('the average rating leaves the testimonials without a rating out', function () {
    $user = User::factory()->create(['name' => 'Overview Owner']);
    $space = overviewSpace($user);

    overviewTestimonial($space, ['rating' => 5]);
    overviewTestimonial($space, ['rating' => 4]);
    overviewTestimonial($space, ['rating' => null]);

    expect(app(DashboardStatistics::class)->forUser($user)['averageRating'])->toBe(4.5);
});

test('the average rating is rounded to one decimal place', function () {
    $user = User::factory()->create(['name' => 'Overview Owner']);
    $space = overviewSpace($user);

    overviewTestimonial($space, ['rating' => 5]);
    overviewTestimonial($space, ['rating' => 4]);
    overviewTestimonial($space, ['rating' => 4]);

    expect(app(DashboardStatistics::class)->forUser($user)['averageRating'])->toBe(4.3);
});

test('the average rating is null when no testimonial carries a rating', function () {
    $user = User::factory()->create(['name' => 'Overview Owner']);
    $space = overviewSpace($user);

    overviewTestimonial($space, ['rating' => null]);
    overviewTestimonial($space, ['rating' => null]);

    expect(app(DashboardStatistics::class)->forUser($user)['averageRating'])->toBeNull();
});

test('the average rating is null when the account has no testimonial', function () {
    $user = User::factory()->create(['name' => 'Overview Owner']);

    overviewSpace($user);

    expect(app(DashboardStatistics::class)->forUser($user)['averageRating'])->toBeNull();
});

test('the average rating counts the ratings it is based on', function () {
    $user = User::factory()->create(['name' => 'Overview Owner']);
    $space = overviewSpace($user);

    overviewTestimonial($space, ['rating' => 5]);
    overviewTestimonial($space, ['rating' => 4]);
    overviewTestimonial($space, ['rating' => null]);

    expect(app(DashboardStatistics::class)->forUser($user)['ratingCount'])->toBe(2);
});

/*
|--------------------------------------------------------------------------
| The page
|--------------------------------------------------------------------------
*/

test('the page shows the total number of testimonials', function () {
    $user = User::factory()->create(['name' => 'Overview Owner']);
    $space = overviewSpace($user);

    overviewTestimonial($space);
    overviewTestimonial($space);
    overviewTestimonial($space);
    overviewTestimonial($space);

    $this->actingAs($user)
        ->get(route('dashboard.overview'))
        ->assertOk()
        ->assertSee('Total testimonials')
        ->assertSee('4');
});

test('the page shows the number of unique customers', function () {
    $user = User::factory()->create(['name' => 'Overview Owner']);
    $space = overviewSpace($user);

    overviewTestimonial($space, ['email' => 'ada@example.com']);
    overviewTestimonial($space, ['email' => 'ada@example.com']);
    overviewTestimonial($space, ['email' => 'grace@example.com']);

    $this->actingAs($user)
        ->get(route('dashboard.overview'))
        ->assertOk()
        ->assertSee('Unique customers')
        ->assertSee('2');
});

test('the page shows the number of testimonials of this month', function () {
    $user = User::factory()->create(['name' => 'Overview Owner']);
    $space = overviewSpace($user);

    overviewTestimonial($space, ['created_at' => now()]);
    overviewTestimonial($space, ['created_at' => now()->startOfMonth()->subMonth()]);

    $this->actingAs($user)
        ->get(route('dashboard.overview'))
        ->assertOk()
        ->assertSee('Testimonials this month')
        ->assertSee('1');
});

test('the page shows the average rating with one decimal place', function () {
    $user = User::factory()->create(['name' => 'Overview Owner']);
    $space = overviewSpace($user);

    overviewTestimonial($space, ['rating' => 5]);
    overviewTestimonial($space, ['rating' => 4]);

    $this->actingAs($user)
        ->get(route('dashboard.overview'))
        ->assertOk()
        ->assertSee('Average rating')
        ->assertSee('4.5');
});

test('the page shows a dash when there is no rating', function () {
    $user = User::factory()->create(['name' => 'Overview Owner']);
    $space = overviewSpace($user);

    overviewTestimonial($space, ['rating' => null]);

    $this->actingAs($user)
        ->get(route('dashboard.overview'))
        ->assertOk()
        ->assertSee('Average rating')
        ->assertSee('No ratings yet')
        ->assertSee('—');
});

test('the page no longer shows the phase three placeholder', function () {
    $user = User::factory()->create(['name' => 'Overview Owner']);

    overviewSpace($user);

    $this->actingAs($user)
        ->get(route('dashboard.overview'))
        ->assertOk()
        ->assertDontSee('Available in Phase 3');
});

test('the page still shows the total number of spaces', function () {
    $user = User::factory()->create(['name' => 'Overview Owner']);

    overviewSpace($user, ['name' => 'First Space', 'slug' => 'first-space']);
    overviewSpace($user, ['name' => 'Second Space', 'slug' => 'second-space']);

    $this->actingAs($user)
        ->get(route('dashboard.overview'))
        ->assertOk()
        ->assertSee('Total spaces')
        ->assertSee('2');
});

/*
|--------------------------------------------------------------------------
| The ownership
|--------------------------------------------------------------------------
*/

test('the figures ignore the testimonials of another account', function () {
    $user = User::factory()->create(['name' => 'Overview Owner']);
    $foreign = User::factory()->create(['name' => 'Foreign Owner']);

    $space = overviewSpace($user);
    $foreignSpace = overviewSpace($foreign, ['name' => 'Foreign Space', 'slug' => 'foreign-space']);

    overviewTestimonial($space);
    overviewTestimonial($foreignSpace);

    $statistics = app(DashboardStatistics::class)->forUser($user);

    expect($statistics['totalTestimonials'])->toBe(1)
        ->and($statistics['uniqueCustomers'])->toBe(1)
        ->and($statistics['thisMonth'])->toBe(1)
        ->and($statistics['ratingCount'])->toBe(1);
});

test('the page of an account with no testimonial shows zero figures', function () {
    $user = User::factory()->create(['name' => 'Overview Owner']);
    $foreign = User::factory()->create(['name' => 'Foreign Owner']);

    $foreignSpace = overviewSpace($foreign, ['name' => 'Foreign Space', 'slug' => 'foreign-space']);
    overviewTestimonial($foreignSpace);

    $this->actingAs($user)
        ->get(route('dashboard.overview'))
        ->assertOk()
        ->assertSee('Total testimonials')
        ->assertSee('No ratings yet');
});

/*
|--------------------------------------------------------------------------
| The soft deleted spaces
|--------------------------------------------------------------------------
*/

test('the figures ignore the testimonials of a soft deleted space', function () {
    $user = User::factory()->create(['name' => 'Overview Owner']);
    $space = overviewSpace($user);

    overviewTestimonial($space);
    overviewTestimonial($space);

    $space->delete();

    $statistics = app(DashboardStatistics::class)->forUser($user);

    expect($statistics['totalTestimonials'])->toBe(0)
        ->and($statistics['uniqueCustomers'])->toBe(0)
        ->and($statistics['thisMonth'])->toBe(0)
        ->and($statistics['averageRating'])->toBeNull();
});

test('the figures keep the testimonials of the spaces that remain', function () {
    $user = User::factory()->create(['name' => 'Overview Owner']);

    $deleted = overviewSpace($user, ['name' => 'Deleted Space', 'slug' => 'deleted-space']);
    $kept = overviewSpace($user, ['name' => 'Kept Space', 'slug' => 'kept-space']);

    overviewTestimonial($deleted);
    overviewTestimonial($kept);

    $deleted->delete();

    $statistics = app(DashboardStatistics::class)->forUser($user);

    expect($statistics['totalTestimonials'])->toBe(1);
});

/*
|--------------------------------------------------------------------------
| The submissions chart
|--------------------------------------------------------------------------
|
| The chart shows the daily submissions of the account over a range of
| seven, thirty or ninety days. The range comes from the browser, so it
| is checked against a fixed set of ranges before it is used.
|
*/

test('the page shows the submissions chart', function () {
    $user = User::factory()->create(['name' => 'Overview Owner']);
    $space = overviewSpace($user);

    overviewTestimonial($space, ['created_at' => now()]);

    $this->actingAs($user)
        ->get(route('dashboard.overview'))
        ->assertOk()
        ->assertSee('Submissions over time')
        ->assertSee('data-submission-chart', false);
});

test('the page no longer shows the submissions placeholder', function () {
    $user = User::factory()->create(['name' => 'Overview Owner']);

    overviewSpace($user);

    $this->actingAs($user)
        ->get(route('dashboard.overview'))
        ->assertOk()
        ->assertDontSee('No submission data yet');
});

test('the page shows an empty state when there are no submissions', function () {
    $user = User::factory()->create(['name' => 'Overview Owner']);

    overviewSpace($user);

    $this->actingAs($user)
        ->get(route('dashboard.overview'))
        ->assertOk()
        ->assertSee('No submissions in the last 7 days')
        ->assertDontSee('data-submission-chart', false);
});

test('the chart carries the labels and the counts of the range', function () {
    $user = User::factory()->create(['name' => 'Overview Owner']);
    $space = overviewSpace($user);

    overviewTestimonial($space, ['created_at' => now()]);

    $response = $this->actingAs($user)->get(route('dashboard.overview'));

    $response->assertOk();

    $html = $response->getContent();

    expect($html)->toContain('"labels"')
        ->and($html)->toContain('"counts"');
});

test('the chart shows every day of the range', function () {
    $user = User::factory()->create(['name' => 'Overview Owner']);
    $space = overviewSpace($user);

    overviewTestimonial($space, ['created_at' => now()]);

    $response = $this->actingAs($user)->get(route('dashboard.overview', ['range' => 30]));

    $response->assertOk();

    $points = app(SubmissionTrend::class)->forUser($user, 30)['points'];

    expect($points)->toHaveCount(30);

    // The last label is today's, so a series of the requested length is
    // what the page was given.
    expect($response->getContent())->toContain(now()->format('j M'));
});

test('the chart offers the three ranges', function () {
    $user = User::factory()->create(['name' => 'Overview Owner']);

    overviewSpace($user);

    $this->actingAs($user)
        ->get(route('dashboard.overview'))
        ->assertOk()
        ->assertSee('7 days')
        ->assertSee('30 days')
        ->assertSee('90 days');
});

test('the page uses the thirty day range when it is asked for', function () {
    $user = User::factory()->create(['name' => 'Overview Owner']);
    $space = overviewSpace($user);

    overviewTestimonial($space, ['created_at' => now()]);

    $this->actingAs($user)
        ->get(route('dashboard.overview', ['range' => 30]))
        ->assertOk()
        ->assertDontSee('last 7 days')
        ->assertSee('last 30 days');
});

test('the page uses the ninety day range when it is asked for', function () {
    $user = User::factory()->create(['name' => 'Overview Owner']);
    $space = overviewSpace($user);

    overviewTestimonial($space, ['created_at' => now()]);

    $this->actingAs($user)
        ->get(route('dashboard.overview', ['range' => 90]))
        ->assertOk()
        ->assertSee('last 90 days');
});

test('the page falls back to seven days for an unsupported range', function () {
    $user = User::factory()->create(['name' => 'Overview Owner']);
    $space = overviewSpace($user);

    overviewTestimonial($space, ['created_at' => now()]);

    $this->actingAs($user)
        ->get(route('dashboard.overview', ['range' => 45]))
        ->assertOk()
        ->assertSee('last 7 days');
});

test('the page falls back to seven days for a range that is not a number', function () {
    $user = User::factory()->create(['name' => 'Overview Owner']);
    $space = overviewSpace($user);

    overviewTestimonial($space, ['created_at' => now()]);

    $this->actingAs($user)
        ->get(route('dashboard.overview', ['range' => 'abc']))
        ->assertOk()
        ->assertSee('last 7 days');
});

test('the page falls back to seven days for a negative range', function () {
    $user = User::factory()->create(['name' => 'Overview Owner']);
    $space = overviewSpace($user);

    overviewTestimonial($space, ['created_at' => now()]);

    $this->actingAs($user)
        ->get(route('dashboard.overview', ['range' => -30]))
        ->assertOk()
        ->assertSee('last 7 days');
});

test('the page shows seven days when no range is asked for', function () {
    $user = User::factory()->create(['name' => 'Overview Owner']);
    $space = overviewSpace($user);

    overviewTestimonial($space, ['created_at' => now()]);

    $this->actingAs($user)
        ->get(route('dashboard.overview'))
        ->assertOk()
        ->assertSee('last 7 days');
});

test('the chart ignores the submissions of another account', function () {
    $user = User::factory()->create(['name' => 'Overview Owner']);
    $foreign = User::factory()->create(['name' => 'Foreign Owner']);

    $space = overviewSpace($user);
    $foreignSpace = overviewSpace($foreign, ['name' => 'Foreign Space', 'slug' => 'foreign-space']);

    overviewTestimonial($space, ['created_at' => now()]);
    overviewTestimonial($foreignSpace, ['created_at' => now()]);

    $this->actingAs($user)
        ->get(route('dashboard.overview'))
        ->assertOk()
        ->assertSee('1 submission in the last 7 days');
});

test('the chart ignores the submissions of a soft deleted space', function () {
    $user = User::factory()->create(['name' => 'Overview Owner']);
    $space = overviewSpace($user);

    overviewTestimonial($space, ['created_at' => now()]);

    $space->delete();

    $this->actingAs($user)
        ->get(route('dashboard.overview'))
        ->assertOk()
        ->assertSee('No submissions in the last 7 days');
});

test('the chart does not expose the email of a customer', function () {
    $user = User::factory()->create(['name' => 'Overview Owner']);
    $space = overviewSpace($user);

    overviewTestimonial($space, ['email' => 'private.customer@example.com', 'created_at' => now()]);

    $this->actingAs($user)
        ->get(route('dashboard.overview'))
        ->assertOk()
        ->assertDontSee('private.customer@example.com');
});

/*
|--------------------------------------------------------------------------
| The recent submissions
|--------------------------------------------------------------------------
*/

test('the page lists the recent submissions', function () {
    $user = User::factory()->create(['name' => 'Overview Owner']);
    $space = overviewSpace($user);

    overviewTestimonial($space, ['name' => 'Recent Customer', 'created_at' => now()]);

    $this->actingAs($user)
        ->get(route('dashboard.overview'))
        ->assertOk()
        ->assertSee('Recent submissions')
        ->assertSee('Recent Customer');
});

test('the recent submissions show the space and the date', function () {
    $user = User::factory()->create(['name' => 'Overview Owner']);
    $space = overviewSpace($user, ['name' => 'Named Space', 'slug' => 'named-space']);

    overviewTestimonial($space, ['name' => 'Recent Customer', 'created_at' => now()]);

    $this->actingAs($user)
        ->get(route('dashboard.overview'))
        ->assertOk()
        ->assertSee('Named Space')
        ->assertSee(now()->format('j M Y'));
});

test('the recent submissions show the moderation state', function () {
    $user = User::factory()->create(['name' => 'Overview Owner']);
    $space = overviewSpace($user);

    overviewTestimonial($space, ['name' => 'Pending Customer', 'approved' => false]);
    overviewTestimonial($space, ['name' => 'Approved Customer', 'approved' => true]);

    $this->actingAs($user)
        ->get(route('dashboard.overview'))
        ->assertOk()
        ->assertSee('Pending')
        ->assertSee('Approved');
});

test('the recent submissions leave out the testimonials of another account', function () {
    $user = User::factory()->create(['name' => 'Overview Owner']);
    $foreign = User::factory()->create(['name' => 'Foreign Owner']);

    $foreignSpace = overviewSpace($foreign, ['name' => 'Foreign Space', 'slug' => 'foreign-space']);

    overviewTestimonial($foreignSpace, ['name' => 'Foreign Customer']);

    $this->actingAs($user)
        ->get(route('dashboard.overview'))
        ->assertOk()
        ->assertDontSee('Foreign Customer');
});

test('the recent submissions leave out the testimonials of a soft deleted space', function () {
    $user = User::factory()->create(['name' => 'Overview Owner']);
    $space = overviewSpace($user);

    overviewTestimonial($space, ['name' => 'Gone Customer']);

    $space->delete();

    $this->actingAs($user)
        ->get(route('dashboard.overview'))
        ->assertOk()
        ->assertDontSee('Gone Customer');
});

test('the recent submissions do not expose the email of a customer', function () {
    $user = User::factory()->create(['name' => 'Overview Owner']);
    $space = overviewSpace($user);

    overviewTestimonial($space, ['email' => 'private.customer@example.com']);

    $this->actingAs($user)
        ->get(route('dashboard.overview'))
        ->assertOk()
        ->assertDontSee('private.customer@example.com');
});

test('the recent submissions do not expose the body of a testimonial', function () {
    $user = User::factory()->create(['name' => 'Overview Owner']);
    $space = overviewSpace($user);

    overviewTestimonial($space, ['body' => 'A very private sentence that nobody else should read.']);

    $this->actingAs($user)
        ->get(route('dashboard.overview'))
        ->assertOk()
        ->assertDontSee('A very private sentence that nobody else should read.');
});
