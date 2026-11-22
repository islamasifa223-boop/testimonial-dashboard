<?php

namespace App\Services;

use App\Models\Testimonial;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * The daily submission trend of an account.
 *
 * The trend is the number of testimonials the account collected on each
 * day of the selected range. It is the series the dashboard chart draws.
 *
 * Three decisions shape what this service returns:
 *
 *  - Every day of the range gets a point, even a day on which nothing
 *    was collected. A chart that skipped the empty days would show a
 *    trend that never happened, and a line that jumped across a gap
 *    would read as if the days in between had been left out on purpose.
 *  - The days are counted in the application's timezone rather than in
 *    the timezone of the database server. The two are not always the
 *    same, and a testimonial that arrived at half past midnight belongs
 *    to the day the owner saw it on.
 *  - The rows are counted in PHP rather than in the database. The
 *    grouping would otherwise need a date function, and SQLite and
 *    MySQL spell that function differently. Counting in PHP keeps one
 *    query that runs the same way on both.
 *
 * Only the account's own testimonials are counted. The ownership query
 * goes through the space, so the testimonials of a soft deleted space
 * are excluded as well, without a second rule to remember.
 */
class SubmissionTrend
{
    /**
     * The ranges the trend may be shown for.
     *
     * The number is the number of days the range covers, including the
     * current day. A range of seven days therefore shows the last seven
     * days, ending today.
     *
     * The list is a closed set. A range that is not on it is refused
     * rather than clamped, so a request cannot ask for a range that
     * was never designed.
     *
     * @var array<int, int>
     */
    public const RANGES = [7, 30, 90];

    /**
     * The range used when none was asked for, or when the one that was
     * asked for is not a range this service knows.
     */
    public const DEFAULT_RANGE = 7;

    /**
     * The daily trend for the given account and range.
     *
     * @return array{
     *     range: int,
     *     points: array<int, array{date: string, label: string, count: int}>
     * }
     */
    public function forUser(User $user, int $range = self::DEFAULT_RANGE): array
    {
        $days = $this->normaliseRange($range);

        // The first day of the range is the day that is "days - 1" days
        // before today, and the last day is today itself.
        $today = CarbonImmutable::now()->startOfDay();
        $start = $today->subDays($days - 1);

        // The buckets are built first, so every day of the range is
        // present with a count of zero before any row is counted.
        $counts = [];

        for ($day = $start; $day->lessThanOrEqualTo($today); $day = $day->addDay()) {
            $counts[$day->toDateString()] = 0;
        }

        // Only the creation date is read, because the chart counts
        // submissions and never shows their content.
        $rows = Testimonial::query()
            ->ownedBy($user)
            ->whereBetween('created_at', [$start->startOfDay(), $today->endOfDay()])
            ->get(['created_at']);

        foreach ($rows as $row) {
            $key = $row->created_at->toDateString();

            // A row outside the buckets is ignored rather than added, so
            // a timestamp that falls outside the range can never create
            // a point that was not asked for.
            if (! array_key_exists($key, $counts)) {
                continue;
            }

            $counts[$key]++;
        }

        $points = [];

        foreach ($counts as $date => $count) {
            $points[] = [
                'date' => $date,
                'label' => CarbonImmutable::parse($date)->format('j M'),
                'count' => $count,
            ];
        }

        return [
            'range' => $days,
            'points' => $points,
        ];
    }

    /**
     * The range that will actually be used.
     *
     * A range that is not one of the supported ranges falls back to the
     * default. The caller never has to check the value, so a range that
     * arrives from the browser cannot reach the query unexamined.
     */
    public function normaliseRange(int $range): int
    {
        return in_array($range, self::RANGES, true) ? $range : self::DEFAULT_RANGE;
    }

    /**
     * Whether the given value is one of the supported ranges.
     *
     * The value comes from the browser as a string, so it is compared as
     * a string against the string form of the supported ranges.
     */
    public function isValidRange(mixed $range): bool
    {
        if (! is_string($range) && ! is_int($range)) {
            return false;
        }

        return in_array((string) $range, array_map('strval', self::RANGES), true);
    }
}
