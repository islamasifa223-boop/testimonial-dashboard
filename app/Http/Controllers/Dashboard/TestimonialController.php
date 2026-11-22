<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\UpdateTestimonialRequest;
use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use App\Services\TestimonialPhotoStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class TestimonialController extends Controller
{
    /**
     * The number of testimonials shown on one page of the inbox.
     */
    public const PER_PAGE = 25;

    public function __construct(private readonly TestimonialPhotoStorage $photos) {}

    /**
     * The owner's inbox: every testimonial of every space, filtered.
     *
     * The space filter needs three cases told apart, because two of them
     * look identical once the value has been resolved:
     *
     *  - no space filter was requested: show every owned testimonial;
     *  - the requested space belongs to the account: filter by it;
     *  - a space was requested but it does not exist or belongs to
     *    another account: show nothing.
     *
     * The third case must not fall back to the first one. Falling back
     * would show every testimonial and make it look as though the
     * filter had been applied, which is both misleading and a quiet
     * hint about what the account holds.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $space = $this->selectedSpace($request, $user);

        $query = Testimonial::query()
            ->ownedBy($user)
            ->search($this->text($request, 'q'))
            ->forSpace($space)
            ->withStatus($this->text($request, 'status'))
            ->withRating($this->selectedRating($request))
            ->favoritesFirst();

        if ($this->hasSpaceFilter($request) && $space === null) {
            $query->whereRaw('1 = 0');
        }

        $testimonials = $query->paginate(self::PER_PAGE)->withQueryString();

        return view('dashboard.inbox', [
            'testimonials' => $testimonials,
            'spaces' => $user->spaces()->latest()->get(),
            'filters' => [
                'q' => $this->text($request, 'q'),
                'status' => $this->text($request, 'status'),
                'rating' => $this->text($request, 'rating'),
                'space' => $this->text($request, 'space'),
            ],
        ]);
    }

    /**
     * The form for editing the text and the rating of a testimonial.
     */
    public function edit(Request $request, int $testimonial): View
    {
        $model = $this->findTestimonial($request, $testimonial, 'view');

        return view('dashboard.testimonials.edit', ['testimonial' => $model]);
    }

    /**
     * Save the owner's edit.
     *
     * Only the text and the rating are written. The request decides
     * which attributes those are, so the moderation flags and the
     * consent stay out of reach of this form.
     */
    public function update(UpdateTestimonialRequest $request, int $testimonial): RedirectResponse
    {
        $model = $this->findTestimonial($request, $testimonial, 'update');

        $model->fill($request->testimonialAttributes());
        $model->save();

        return redirect()->route('dashboard.inbox')
            ->with('status', 'The testimonial has been updated.');
    }

    /**
     * Approve or withdraw the approval of a testimonial.
     */
    public function approve(Request $request, int $testimonial): RedirectResponse
    {
        $model = $this->findTestimonial($request, $testimonial, 'approve');

        $model->approved = ! $model->approved;
        $model->save();

        return redirect()->route('dashboard.inbox')
            ->with('status', $model->approved
                ? 'The testimonial has been approved.'
                : 'The approval has been withdrawn.');
    }

    /**
     * Hide or reveal a testimonial.
     */
    public function hide(Request $request, int $testimonial): RedirectResponse
    {
        $model = $this->findTestimonial($request, $testimonial, 'hide');

        $model->hidden = ! $model->hidden;
        $model->save();

        return redirect()->route('dashboard.inbox')
            ->with('status', $model->hidden
                ? 'The testimonial is now hidden.'
                : 'The testimonial is visible again.');
    }

    /**
     * Favourite or unfavourite a testimonial.
     */
    public function favorite(Request $request, int $testimonial): RedirectResponse
    {
        $model = $this->findTestimonial($request, $testimonial, 'favorite');

        $model->favorite = ! $model->favorite;
        $model->save();

        return redirect()->route('dashboard.inbox')
            ->with('status', $model->favorite
                ? 'The testimonial is now a favourite.'
                : 'The testimonial is no longer a favourite.');
    }

    /**
     * Add or remove a testimonial from the Wall of Love.
     */
    public function heart(Request $request, int $testimonial): RedirectResponse
    {
        $model = $this->findTestimonial($request, $testimonial, 'heart');

        $model->hearted = ! $model->hearted;
        $model->save();

        return redirect()->route('dashboard.inbox')
            ->with('status', $model->hearted
                ? 'The testimonial has been added to your Wall of Love.'
                : 'The testimonial has been removed from your Wall of Love.');
    }

    /**
     * Delete a testimonial together with its private photo.
     *
     * The row goes first, so the testimonial is gone even if the photo
     * cannot be removed. A photo that fails to delete is reported and
     * swallowed: an orphaned file is untidy but far less bad than a
     * testimonial that the owner deleted and is still looking at.
     */
    public function destroy(Request $request, int $testimonial): RedirectResponse
    {
        $model = $this->findTestimonial($request, $testimonial, 'delete');

        $photoPath = $model->photo_path;

        $model->delete();

        try {
            $this->photos->delete($photoPath);
        } catch (Throwable $exception) {
            report($exception);
        }

        return redirect()->route('dashboard.inbox')
            ->with('status', 'The testimonial has been deleted.');
    }

    /**
     * Find a testimonial and authorise the requested ability on it.
     *
     * The testimonial is loaded with its space, so the policy can read
     * the owner of the space without a second query. A testimonial that
     * does not exist is a 404. A testimonial that belongs to another
     * account, or whose space has been soft deleted, is a 403.
     */
    private function findTestimonial(Request $request, int $id, string $ability): Testimonial
    {
        $testimonial = Testimonial::query()->with('space')->whereKey($id)->firstOrFail();

        $this->authorize($ability, $testimonial);

        return $testimonial;
    }

    /**
     * Whether a space filter was asked for at all.
     *
     * This only reports that a space parameter was supplied, not that it
     * was any good. An array such as ?space[]=1 counts as supplied, so
     * that a malformed filter still empties the inbox rather than
     * quietly turning the filter off.
     */
    private function hasSpaceFilter(Request $request): bool
    {
        $raw = $request->query('space');

        if ($raw === null) {
            return false;
        }

        if (is_array($raw)) {
            return true;
        }

        return is_string($raw) && $raw !== '';
    }

    /**
     * The space the inbox is filtered by.
     *
     * Returns the space when the account owns it, and null when no
     * space filter was requested or when the requested space cannot be
     * honoured. The caller tells those two apart with hasSpaceFilter(),
     * because only the first of them means "show every space".
     */
    private function selectedSpace(Request $request, User $user): ?Space
    {
        $raw = $request->query('space');

        if (! is_string($raw) || $raw === '' || ! ctype_digit($raw)) {
            return null;
        }

        $id = (int) $raw;

        if ($id <= 0) {
            return null;
        }

        return $user->spaces()->whereKey($id)->first();
    }

    /**
     * The rating the inbox is filtered by.
     *
     * A rating of zero is the filter for the testimonials that were
     * left without one, so zero is a real selection and not "no
     * filter". A malformed rating is ignored rather than guessed at.
     */
    private function selectedRating(Request $request): ?int
    {
        $raw = $request->query('rating');

        if (! is_string($raw) || $raw === '' || ! ctype_digit($raw)) {
            return null;
        }

        $rating = (int) $raw;

        if ($rating < 0 || $rating > 5) {
            return null;
        }

        return $rating;
    }

    /**
     * A query string value as plain text.
     *
     * An array value, which is what a crafted query string such as
     * ?q[]=a produces, is not text and is therefore ignored.
     */
    private function text(Request $request, string $key): string
    {
        $value = $request->query($key);

        return is_string($value) ? $value : '';
    }
}
