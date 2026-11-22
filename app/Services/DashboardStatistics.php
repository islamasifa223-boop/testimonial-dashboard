<?php

namespace App\Services;

use App\Models\Testimonial;
use App\Models\User;

/**
 * The figures shown on the dashboard overview.
 *
 * Every number is derived from the account's own testimonials, and every
 * number comes from a single query, so the cards on the overview page can
 * never disagree with one another.
 *
 * The rules the figures follow:
 *
 * - The total counts every testimonial the account has collected, whatever
 *   its moderation state. A pending or hidden testimonial is still a
 *   testimonial the owner has to deal with, so it is counted.
 * - Unique customers counts distinct email addresses, because the same
 *   person may leave several testimonials across spaces.
 * - This month counts the testimonials created since the first day of the
 *   current calendar month.
 * - The average rating is taken over the testimonials that carry a rating.
 *   A rating is optional, so the ratings that are missing are left out of
 *   the average rather than counting as zero.
 */
class DashboardStatistics
{
    /**
     * The figures for the given account.
     *
     * @return array{
     *     totalTestimonials: int,
     *     uniqueCustomers: int,
     *     thisMonth: int,
     *     averageRating: float|null,
     *     ratingCount: int
     * }
     */
    public function forUser(User $user): array
    {
        $monthStart = now()->startOfMonth();

        // One row, five figures.
        //
        // The expressions are written by hand rather than through the
        // aggregate helpers because each helper returns a single value
        // under a single alias, so each figure would need a query of its own.
        //
        // The this month figure is a sum and not a count: a count would
        // count the zero that the case expression produces for every
        // testimonial outside the month, because a zero is not null.
        //
        // Every expression is standard SQL that SQLite and MySQL both
        // understand, so the same query runs in the tests and in
        // production.
        $row = Testimonial::query()
            ->ownedBy($user)
            ->selectRaw(implode(', ', [
                'count(*) as total_testimonials',
                'count(distinct email) as unique_customers',
                'sum(case when created_at >= ? then 1 else 0 end) as this_month',
                'avg(rating) as average_rating',
                'count(rating) as rating_count',
            ]), [$monthStart])
            ->first();

        // The counts come back as integers on SQLite and as strings on
        // MySQL, so they are cast before they are used.
        $ratingCount = (int) ($row?->rating_count ?? 0);

        // The average comes back as a float on SQLite and as a string on
        // MySQL, so it is cast before it is rounded.
        $average = $row?->average_rating;

        return [
            'totalTestimonials' => (int) ($row?->total_testimonials ?? 0),
            'uniqueCustomers' => (int) ($row?->unique_customers ?? 0),
            'thisMonth' => (int) ($row?->this_month ?? 0),
            'averageRating' => $ratingCount === 0 || $average === null
                ? null
                : round((float) $average, 1),
            'ratingCount' => $ratingCount,
        ];
    }
}
