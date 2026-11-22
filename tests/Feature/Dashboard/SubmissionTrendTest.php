<?php

use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use App\Services\SubmissionTrend;
use Carbon\CarbonImmutable;

beforeEach(function () {
    seedPlans();
});

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function trendUser(string $name = 'Trend Owner'): User
{
    return User::factory()->create(['name' => $name]);
}

function trendSpace(User $user, array $attributes = []): Space
{
    return Space::factory()->for($user)->create(array_merge([
        'name' => 'Trend Space',
        'slug' => 'trend-space',
    ], $attributes));
}

/**
 * A testimonial of the given space, created at the given moment.
 *
 * The creation date is set explicitly, because a testimonial that was
 * created now cannot be placed on an earlier day of the range.
 */
function trendTestimonial(Space $space, string $createdAt, array $attributes = []): Testimonial
{
    $testimonial = Testimonial::factory()->for($space)->create(array_merge([
        'name' => 'Ada Lovelace',
        'email' => 'ada@example.com',
    ], $attributes));

    $testimonial->forceFill([
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ])->saveQuietly();

    return $testimonial->refresh();
}

/**
 * The count of the point on the given day.
 */
function trendCountOn(array $points, string $date): int
{
    foreach ($points as $point) {
        if ($point['date'] === $date) {
            return $point['count'];
        }
    }

    return -1;
}

/*
|--------------------------------------------------------------------------
| The shape of the series
|--------------------------------------------------------------------------
*/

test('a seven day range returns one point for every day', function () {
    $trend = app(SubmissionTrend::class)->forUser(trendUser());

    expect($trend['range'])->toBe(7)
        ->and($trend['points'])->toHaveCount(7);
});

test('a thirty day range returns one point for every day', function () {
    $trend = app(SubmissionTrend::class)->forUser(trendUser(), 30);

    expect($trend['range'])->toBe(30)
        ->and($trend['points'])->toHaveCount(30);
});

test('a ninety day range returns one point for every day', function () {
    $trend = app(SubmissionTrend::class)->forUser(trendUser(), 90);

    expect($trend['range'])->toBe(90)
        ->and($trend['points'])->toHaveCount(90);
});

test('the points run from the oldest day to today', function () {
    $trend = app(SubmissionTrend::class)->forUser(trendUser(), 7);

    $dates = array_column($trend['points'], 'date');

    expect($dates[0])->toBe(now()->subDays(6)->toDateString())
        ->and($dates[6])->toBe(now()->toDateString());
});

test('the points cover every day without a gap', function () {
    $trend = app(SubmissionTrend::class)->forUser(trendUser(), 30);

    $dates = array_column($trend['points'], 'date');

    expect($dates)->toHaveCount(30);

    // Every day after the first is exactly one day after the day
    // before it, so no day is skipped and no day appears twice.
    foreach ($dates as $index => $date) {
        if ($index === 0) {
            continue;
        }

        $previous = CarbonImmutable::parse($dates[$index - 1]);
        $current = CarbonImmutable::parse($date);

        // The absolute value is taken because Carbon measures the
        // difference from the receiver towards the argument. The gap
        // between two consecutive days is one day either way round.
        expect($current->diffInDays($previous, true))->toBe(1.0);
    }
});

test('a day without a submission is present with a count of zero', function () {
    $user = trendUser();
    $space = trendSpace($user);

    trendTestimonial($space, now()->toDateTimeString());

    $trend = app(SubmissionTrend::class)->forUser($user, 7);

    expect(trendCountOn($trend['points'], now()->toDateString()))->toBe(1)
        ->and(trendCountOn($trend['points'], now()->subDays(3)->toDateString()))->toBe(0);
});

test('every point carries a label and a count', function () {
    $trend = app(SubmissionTrend::class)->forUser(trendUser(), 7);

    foreach ($trend['points'] as $point) {
        expect($point)->toHaveKeys(['date', 'label', 'count'])
            ->and($point['label'])->toBeString()->not->toBeEmpty()
            ->and($point['count'])->toBeInt();
    }
});

/*
|--------------------------------------------------------------------------
| Counting the submissions
|--------------------------------------------------------------------------
*/

test('a submission of today is counted on today', function () {
    $user = trendUser();
    $space = trendSpace($user);

    trendTestimonial($space, now()->toDateTimeString());

    $trend = app(SubmissionTrend::class)->forUser($user, 7);

    expect(trendCountOn($trend['points'], now()->toDateString()))->toBe(1);
});

test('several submissions of the same day are counted together', function () {
    $user = trendUser();
    $space = trendSpace($user);

    trendTestimonial($space, now()->toDateTimeString());
    trendTestimonial($space, now()->toDateTimeString());
    trendTestimonial($space, now()->toDateTimeString());

    $trend = app(SubmissionTrend::class)->forUser($user, 7);

    expect(trendCountOn($trend['points'], now()->toDateString()))->toBe(3);
});

test('a submission of an earlier day is counted on that day', function () {
    $user = trendUser();
    $space = trendSpace($user);

    $day = now()->subDays(4)->toDateTimeString();

    trendTestimonial($space, $day);

    $trend = app(SubmissionTrend::class)->forUser($user, 7);

    expect(trendCountOn($trend['points'], now()->subDays(4)->toDateString()))->toBe(1)
        ->and(trendCountOn($trend['points'], now()->toDateString()))->toBe(0);
});

test('the submissions of every space of the account are counted', function () {
    $user = trendUser();
    $first = trendSpace($user, ['name' => 'First Space', 'slug' => 'first-space']);
    $second = trendSpace($user, ['name' => 'Second Space', 'slug' => 'second-space']);

    trendTestimonial($first, now()->toDateTimeString());
    trendTestimonial($second, now()->toDateTimeString());

    $trend = app(SubmissionTrend::class)->forUser($user, 7);

    expect(trendCountOn($trend['points'], now()->toDateString()))->toBe(2);
});

test('a submission whatever its moderation state is counted', function () {
    $user = trendUser();
    $space = trendSpace($user);

    trendTestimonial($space, now()->toDateTimeString(), ['approved' => false, 'consented' => false]);
    trendTestimonial($space, now()->toDateTimeString(), ['approved' => true, 'consented' => true]);
    trendTestimonial($space, now()->toDateTimeString(), ['approved' => true, 'consented' => true, 'hidden' => true]);

    $trend = app(SubmissionTrend::class)->forUser($user, 7);

    expect(trendCountOn($trend['points'], now()->toDateString()))->toBe(3);
});

test('the count of the range matches the number of submissions in it', function () {
    $user = trendUser();
    $space = trendSpace($user);

    trendTestimonial($space, now()->toDateTimeString());
    trendTestimonial($space, now()->subDays(2)->toDateTimeString());
    trendTestimonial($space, now()->subDays(6)->toDateTimeString());

    $trend = app(SubmissionTrend::class)->forUser($user, 7);

    $total = array_sum(array_column($trend['points'], 'count'));

    expect($total)->toBe(3);
});

/*
|--------------------------------------------------------------------------
| The edges of the range
|--------------------------------------------------------------------------
*/

test('a submission on the first day of the range is counted', function () {
    $user = trendUser();
    $space = trendSpace($user);

    $day = now()->subDays(6)->toDateTimeString();

    trendTestimonial($space, $day);

    $trend = app(SubmissionTrend::class)->forUser($user, 7);

    expect(trendCountOn($trend['points'], now()->subDays(6)->toDateString()))->toBe(1);
});

test('a submission before the range is left out', function () {
    $user = trendUser();
    $space = trendSpace($user);

    trendTestimonial($space, now()->subDays(7)->toDateTimeString());

    $trend = app(SubmissionTrend::class)->forUser($user, 7);

    $total = array_sum(array_column($trend['points'], 'count'));

    expect($total)->toBe(0)
        ->and($trend['points'])->toHaveCount(7);
});

test('a submission before the range of a longer range is counted', function () {
    $user = trendUser();
    $space = trendSpace($user);

    trendTestimonial($space, now()->subDays(7)->toDateTimeString());

    $trend = app(SubmissionTrend::class)->forUser($user, 30);

    expect(trendCountOn($trend['points'], now()->subDays(7)->toDateString()))->toBe(1);
});

test('a submission of the future is left out', function () {
    $user = trendUser();
    $space = trendSpace($user);

    trendTestimonial($space, now()->addDays(2)->toDateTimeString());

    $trend = app(SubmissionTrend::class)->forUser($user, 7);

    $total = array_sum(array_column($trend['points'], 'count'));

    expect($total)->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Ownership
|--------------------------------------------------------------------------
*/

test('the submissions of another account are left out', function () {
    $user = trendUser();
    $other = trendUser('Other Owner');

    $space = trendSpace($user);
    $otherSpace = trendSpace($other, ['name' => 'Other Space', 'slug' => 'other-space']);

    trendTestimonial($space, now()->toDateTimeString());
    trendTestimonial($otherSpace, now()->toDateTimeString());

    $trend = app(SubmissionTrend::class)->forUser($user, 7);

    expect(trendCountOn($trend['points'], now()->toDateString()))->toBe(1);
});

test('the submissions of a soft deleted space are left out', function () {
    $user = trendUser();
    $space = trendSpace($user);

    trendTestimonial($space, now()->toDateTimeString());

    $space->delete();

    $trend = app(SubmissionTrend::class)->forUser($user, 7);

    $total = array_sum(array_column($trend['points'], 'count'));

    expect($total)->toBe(0);
});

test('the submissions of the spaces that remain are counted', function () {
    $user = trendUser();
    $kept = trendSpace($user, ['name' => 'Kept Space', 'slug' => 'kept-space']);
    $deleted = trendSpace($user, ['name' => 'Deleted Space', 'slug' => 'deleted-space']);

    trendTestimonial($kept, now()->toDateTimeString());
    trendTestimonial($deleted, now()->toDateTimeString());

    $deleted->delete();

    $trend = app(SubmissionTrend::class)->forUser($user, 7);

    expect(trendCountOn($trend['points'], now()->toDateString()))->toBe(1);
});

test('an account without a submission gets a series of zeroes', function () {
    $trend = app(SubmissionTrend::class)->forUser(trendUser(), 7);

    expect($trend['points'])->toHaveCount(7)
        ->and(array_sum(array_column($trend['points'], 'count')))->toBe(0);
});

/*
|--------------------------------------------------------------------------
| The range itself
|--------------------------------------------------------------------------
*/

test('an unsupported range falls back to seven days', function () {
    $trend = app(SubmissionTrend::class)->forUser(trendUser(), 45);

    expect($trend['range'])->toBe(7)
        ->and($trend['points'])->toHaveCount(7);
});

test('a range of zero falls back to seven days', function () {
    $trend = app(SubmissionTrend::class)->forUser(trendUser(), 0);

    expect($trend['range'])->toBe(7);
});

test('a negative range falls back to seven days', function () {
    $trend = app(SubmissionTrend::class)->forUser(trendUser(), -30);

    expect($trend['range'])->toBe(7);
});

test('the supported ranges are the only ones that are accepted', function () {
    $trend = app(SubmissionTrend::class);

    expect($trend->isValidRange('7'))->toBeTrue()
        ->and($trend->isValidRange('30'))->toBeTrue()
        ->and($trend->isValidRange('90'))->toBeTrue()
        ->and($trend->isValidRange(30))->toBeTrue()
        ->and($trend->isValidRange('45'))->toBeFalse()
        ->and($trend->isValidRange(''))->toBeFalse()
        ->and($trend->isValidRange('7 days'))->toBeFalse()
        ->and($trend->isValidRange(null))->toBeFalse()
        ->and($trend->isValidRange(true))->toBeFalse()
        ->and($trend->isValidRange([7]))->toBeFalse();
});
