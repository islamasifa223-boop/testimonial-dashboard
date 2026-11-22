<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use App\Services\DashboardStatistics;
use App\Services\PlanEntitlement;
use App\Services\SubmissionTrend;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OverviewController extends Controller
{
    /**
     * How many recent submissions the overview lists.
     */
    public const RECENT_SUBMISSIONS = 5;

    public function __construct(
        private readonly PlanEntitlement $entitlement,
        private readonly DashboardStatistics $statistics,
        private readonly SubmissionTrend $trend,
    ) {}

    /**
     * Show the dashboard overview.
     *
     * Every figure is derived from the account's own records. With no
     * activity yet the counts are zero rather than illustrative data.
     */
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $spacesUsed = $this->entitlement->spacesUsed($user);

        // The testimonial figures come from a single query, so the cards
        // on the page are all read from the same moment in time.
        $statistics = $this->statistics->forUser($user);

        // The range comes from the browser, so it is checked against the
        // ranges the trend knows before it is used. A range that is not
        // one of them falls back to the default rather than reaching the
        // query.
        $range = $this->trend->normaliseRange($this->requestedRange($request));

        $trend = $this->trend->forUser($user, $range);

        return view('dashboard.overview', [
            'spacesUsed' => $spacesUsed,
            'spacesLimit' => $this->entitlement->spacesLimit($user),
            'spacesRemaining' => $this->entitlement->spacesRemaining($user),
            'recentSpaces' => $user->spaces()->latest()->take(5)->get(),
            'totalSpaces' => Space::where('user_id', $user->id)->count(),

            'totalTestimonials' => $statistics['totalTestimonials'],
            'uniqueCustomers' => $statistics['uniqueCustomers'],
            'thisMonth' => $statistics['thisMonth'],
            'averageRating' => $statistics['averageRating'],
            'ratingCount' => $statistics['ratingCount'],

            'trendRange' => $trend['range'],
            'trendPoints' => $trend['points'],
            'trendTotal' => array_sum(array_column($trend['points'], 'count')),

            'recentSubmissions' => $this->recentSubmissions($user),
        ]);
    }

    /**
     * The range the browser asked for.
     *
     * The value is read as a string and is only ever one of the ranges
     * the trend supports. Anything else is refused here and the default
     * is used, so an unexamined value never reaches the query.
     */
    protected function requestedRange(Request $request): int
    {
        $value = $request->query('range');

        if ($value === null) {
            return SubmissionTrend::DEFAULT_RANGE;
        }

        if (! $this->trend->isValidRange($value)) {
            return SubmissionTrend::DEFAULT_RANGE;
        }

        return (int) $value;
    }

    /**
     * The most recent submissions of the account.
     *
     * The list is scoped to the account's own spaces through the same
     * ownership query the statistics use, so the testimonials of another
     * account and the testimonials of a soft deleted space are both left
     * out.
     *
     * Only the fields the owner needs to recognise a submission are
     * read. The email of the customer is not among them, and neither is
     * the body: the list is a summary, and the inbox is where a
     * submission is read in full.
     *
     * @return Collection<int, Testimonial>
     */
    protected function recentSubmissions(User $user)
    {
        return Testimonial::query()
            ->ownedBy($user)
            ->with('space')
            ->latest('created_at')
            ->latest('id')
            ->take(self::RECENT_SUBMISSIONS)
            ->get(['id', 'space_id', 'name', 'company_name', 'rating', 'approved', 'hidden', 'created_at']);
    }
}
