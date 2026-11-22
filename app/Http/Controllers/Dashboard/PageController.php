<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Testimonial;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The pages of the areas that do not need a controller of their own.
 *
 * The Wall of Love page is built here because it only reads data: it
 * shows the account's spaces, their public wall links, and the
 * testimonials the owner hearted that the public wall does not show.
 */
class PageController extends Controller
{
    public function wall(Request $request): View
    {
        $user = $request->user();

        // The number of testimonials on the public wall of each space.
        // The count is taken from the wall rule itself, so it can never
        // disagree with the page the visitor actually sees.
        $spaces = $user->spaces()
            ->latest()
            ->withCount(['testimonials as wall_count' => fn ($query) => $query->visibleOnWall()])
            ->get();

        foreach ($spaces as $space) {
            $space->wall_count = (int) $space->wall_count;
        }

        // The hearted testimonials the public wall does not show.
        //
        // These are fetched by the heart alone and told apart in PHP,
        // because the wall rule is already written once in
        // isOnWall(). Repeating the rule in the query would let the
        // list of what is missing drift away from the rule that
        // decides what is shown.
        $hearted = Testimonial::query()
            ->ownedBy($user)
            ->where('hearted', true)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $excluded = $hearted
            ->reject(fn (Testimonial $testimonial) => $testimonial->isOnWall())
            ->map(fn (Testimonial $testimonial) => [
                'testimonial' => $testimonial,
                'reasons' => $this->exclusionReasons($testimonial),
            ])
            ->values();

        return view('dashboard.wall', [
            'spaces' => $spaces,
            'excluded' => $excluded,
        ]);
    }

    public function widgets(): View
    {
        return view('dashboard.widgets');
    }

    /**
     * Why a hearted testimonial is not on the public wall.
     *
     * Every reason that applies is returned, because the flags are
     * independent of one another and a testimonial can break more
     * than one of them at the same time.
     *
     * The three reasons must stay aligned with the wall eligibility
     * rule: if that rule changes, these reasons have to change with
     * it, or the list would no longer say why a testimonial is missing
     * from the wall.
     *
     * The hidden flag is read through getAttribute() rather than
     * $testimonial->hidden, because Eloquent's Model declares a
     * protected $hidden property for serialisation. Inside the model
     * that property is directly accessible, so $testimonial->hidden
     * would return that array instead of the column.
     */
    private function exclusionReasons(Testimonial $testimonial): array
    {
        $reasons = [];

        if (! $testimonial->approved) {
            $reasons[] = 'Not approved';
        }

        if ((bool) $testimonial->getAttribute('hidden')) {
            $reasons[] = 'Hidden';
        }

        if (! $testimonial->consented) {
            $reasons[] = 'No consent';
        }

        return $reasons;
    }
}
