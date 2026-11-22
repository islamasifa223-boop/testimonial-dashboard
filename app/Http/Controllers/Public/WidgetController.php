<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Widget;
use App\Services\TestimonialPhotoStorage;
use App\Services\WidgetAppearance;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The public rendering of an embeddable widget.
 *
 * A widget is shown on somebody else's website, so everything it serves
 * is checked against the widget rules on every request. Nothing is
 * trusted because it was true when the page was rendered: a testimonial
 * that is hidden, unapproved or has its consent withdrawn has to stop
 * being shown at once.
 *
 * The rules are the public visibility rule without the heart. The heart
 * belongs to the Wall of Love and is the owner's own selection there;
 * it does not decide whether a testimonial may be shown at all.
 */
class WidgetController extends Controller
{
    /**
     * The caching rule for the widget page.
     *
     * The page is never cached. A testimonial that is hidden or has its
     * consent withdrawn has to stop being shown at once, and a cached
     * copy would keep serving it. The same rule applies to the photos,
     * which the photo storage service applies itself.
     */
    public const CACHE_CONTROL = 'no-store';

    public function __construct(
        private readonly TestimonialPhotoStorage $photos,
        private readonly WidgetAppearance $appearance,
    ) {}

    /**
     * Render a widget.
     *
     * The widget is looked up by its public id rather than route-model
     * bound, so the model's route key is left untouched. An id that
     * matches no widget results in a 404 rather than an error.
     *
     * A widget whose space has been soft deleted is refused with the
     * same 404: the space relation of a deleted space is empty, and a
     * widget without a space has no testimonials to show and no owner
     * to attribute it to.
     *
     * Only the testimonials that satisfy every widget condition are
     * handed to the view. The visiblePublicly scope asks the database
     * for the testimonials that are approved, not hidden and consented,
     * which is the same rule the photo route applies.
     */
    public function show(string $publicId): Response
    {
        $widget = Widget::where('public_id', $publicId)->firstOrFail();

        $space = $widget->space;

        if ($space === null) {
            abort(404);
        }

        $testimonials = $space->testimonials()
            ->visiblePublicly()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($widget->limit)
            ->get();

        return response()->view('public.widgets.show', [
            'widget' => $widget,
            'space' => $space,
            'testimonials' => $testimonials,
            'appearance' => $this->appearance->for($widget),
        ])->header('Cache-Control', self::CACHE_CONTROL);
    }

    /**
     * Serve the photo of a testimonial shown in a widget.
     *
     * The photo lives on the private disk, so this route is the only
     * way it reaches a browser. Every way of failing is the same 404:
     * a distinct error for each cause would tell a visitor which
     * testimonial ids exist and why one of them was refused.
     *
     * The checks run in this order:
     *
     *  - the widget exists and its space has not been soft deleted;
     *  - the testimonial belongs to that space;
     *  - the testimonial still satisfies every widget condition:
     *    approved, not hidden and consented;
     *  - the testimonial has a photo, and the path of that photo
     *    belongs to the space;
     *  - the file is really on the disk.
     *
     * The last check is needed because the response is built before
     * the file is read, and the private disk does not throw when a
     * file is missing. Without it, a path that has no file behind it
     * would be answered with an empty response instead of a 404.
     */
    public function photo(string $publicId, int $testimonial): StreamedResponse
    {
        $widget = Widget::where('public_id', $publicId)->firstOrFail();

        $space = $widget->space;

        if ($space === null) {
            abort(404);
        }

        $model = $space->testimonials()->whereKey($testimonial)->first();

        if ($model === null || ! $model->isVisiblePublicly()) {
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
