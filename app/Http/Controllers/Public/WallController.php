<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Space;
use App\Services\TestimonialPhotoStorage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The public Wall of Love of a space.
 *
 * The wall is a public surface, so everything it serves is checked
 * against the wall rules on every request. Nothing is trusted because
 * it was true when the page was rendered: a heart that is removed, a
 * testimonial that is hidden and a consent that is withdrawn all take
 * effect at once.
 */
class WallController extends Controller
{
    public function __construct(private readonly TestimonialPhotoStorage $photos) {}

    /**
     * Show the public Wall of Love of a space.
     *
     * The space is looked up by slug rather than route-model bound, so
     * the model's global route key is left untouched for the dashboard
     * routes. A slug that matches no active space - including one that
     * belongs to a soft-deleted space - results in a 404 rather than
     * an error.
     *
     * Only the testimonials that satisfy every wall condition are
     * handed to the view: the visibleOnWall scope asks the database
     * for the testimonials that are hearted, approved, not hidden and
     * consented, which is the same rule the photo route applies.
     */
    public function show(string $slug): View
    {
        $space = Space::where('slug', $slug)->firstOrFail();

        $testimonials = $space->testimonials()
            ->visibleOnWall()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return view('public.walls.show', [
            'space' => $space,
            'testimonials' => $testimonials,
        ]);
    }

    /**
     * Serve the photo of a testimonial on the public Wall of Love.
     *
     * The photo lives on the private disk, so this route is the only
     * way it reaches a browser. Every way of failing is the same 404:
     * a distinct error for each cause would tell a visitor which
     * testimonial ids exist and why one of them was refused.
     *
     * The checks run in this order:
     *
     *  - the space exists and is not soft deleted;
     *  - the testimonial belongs to that space;
     *  - the testimonial still satisfies all four wall conditions:
     *    hearted, approved, not hidden and consented;
     *  - the testimonial has a photo, and the path of that photo
     *    belongs to the space;
     *  - the file is really on the disk.
     *
     * The last check is needed because the response is built before
     * the file is read, and the private disk does not throw when a
     * file is missing. Without it, a path that has no file behind it
     * would be answered with an empty response instead of a 404.
     */
    public function photo(string $slug, int $testimonial): StreamedResponse
    {
        $space = Space::where('slug', $slug)->firstOrFail();

        $model = $space->testimonials()->whereKey($testimonial)->first();

        if ($model === null || ! $model->isOnWall()) {
            abort(404);
        }

        $path = $model->photo_path;

        if ($path === null || ! $this->photos->belongsToSpace($path, $space)) {
            abort(404);
        }

        if (! $this->photos->exists($path)) {
            abort(404);
        }

        return $this->photos->response($path);
    }
}
