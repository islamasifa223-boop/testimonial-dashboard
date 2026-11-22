<?php

namespace App\Http\Controllers\Public;

use App\Exceptions\PlanLimitExceeded;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreTestimonialRequest;
use App\Models\Space;
use App\Models\Testimonial;
use App\Services\PlanEntitlement;
use App\Services\TestimonialPhotoStorage;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class TestimonialController extends Controller
{
    /**
     * The prefix used for the per-space submission lock.
     */
    public const SUBMISSION_LOCK_PREFIX = 'testimonial-submission-space:';

    /**
     * How long the submission lock is held before it expires.
     *
     * The lease covers the whole transaction: the row lock, the insert,
     * the photo write and the second entitlement check. A lease that
     * expires while the work is still running would let a second
     * submission through, so the lease is comfortably longer than any
     * reasonable request. Should it ever expire anyway, the row lock
     * inside the transaction and the second entitlement check still
     * reject the extra submission.
     */
    public const LOCK_LEASE_SECONDS = 30;

    /**
     * How long a visitor waits for a busy space before giving up.
     */
    public const LOCK_WAIT_SECONDS = 5;

    public function __construct(
        protected TestimonialPhotoStorage $photos,
        protected PlanEntitlement $entitlement,
    ) {}

    /**
     * Store a testimonial submitted to a space.
     */
    public function store(StoreTestimonialRequest $request): RedirectResponse
    {
        $space = $request->space();

        $lock = Cache::lock(self::SUBMISSION_LOCK_PREFIX.$space->id, self::LOCK_LEASE_SECONDS);

        try {
            $acquired = $lock->block(self::LOCK_WAIT_SECONDS);
        } catch (LockTimeoutException) {
            $acquired = false;
        }

        if (! $acquired) {
            return $this->spaceIsNotAccepting($space);
        }

        try {
            return DB::transaction(function () use ($request, $space): RedirectResponse {
                $locked = Space::query()
                    ->whereKey($space->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->entitlement->assertCanSubmitTestimonial($locked);

                // The path of the photo that has been written to the
                // disk. It is declared before the rollback callback is
                // registered, so the callback can see the value that is
                // set later.
                $storedPhotoPath = null;

                // Registered inside the transaction, because a callback
                // registered outside one is never attached to a
                // transaction record and would never run.
                DB::afterRollBack(function () use (&$storedPhotoPath): void {
                    if ($storedPhotoPath !== null) {
                        $this->photos->delete($storedPhotoPath);

                        $storedPhotoPath = null;
                    }
                });

                $testimonial = $locked->testimonials()->create($request->testimonialAttributes());

                $testimonial->consented = $request->consented();
                $testimonial->save();

                $this->storePhoto($testimonial, $request->photo(), $locked, $storedPhotoPath);

                // The count includes the row this request just inserted,
                // so one is taken off to allow a submission that fills the
                // quota exactly.
                if ($this->entitlement->testimonialsUsed($locked) - 1 >= $this->entitlement->testimonialsLimitFor($locked)) {
                    throw new PlanLimitExceeded(
                        'testimonial',
                        $this->entitlement->testimonialsLimitFor($locked)
                    );
                }

                return redirect()
                    ->route('spaces.testimonials.thanks', $locked->slug)
                    ->with('status', 'Your testimonial has been received.');
            });
        } catch (PlanLimitExceeded) {
            return $this->spaceIsNotAccepting($space);
        } finally {
            $lock->release();
        }
    }

    /**
     * Show the page a visitor lands on after a successful submission.
     */
    public function thanks(string $slug): View
    {
        $space = Space::where('slug', $slug)->firstOrFail();

        return view('public.spaces.thanks', ['space' => $space]);
    }

    /**
     * The response used when the space cannot accept a testimonial.
     */
    protected function spaceIsNotAccepting(Space $space): RedirectResponse
    {
        return redirect()
            ->route('spaces.show', $space->slug)
            ->with('status', 'This space is not accepting new testimonials at the moment.');
    }

    /**
     * Store the submitted photo and record its path on the testimonial.
     *
     * A photo that was asked for but could not be stored is a failure,
     * not something to accept quietly. The exception is reported and then
     * rethrown, so the database transaction is rolled back and the
     * testimonial is not saved without its photo.
     *
     * The path is written back through the reference so the rollback
     * callback can remove the file if the transaction is rolled back
     * after this point.
     */
    protected function storePhoto(
        Testimonial $testimonial,
        ?UploadedFile $photo,
        Space $space,
        ?string &$storedPhotoPath = null
    ): void {
        if ($photo === null) {
            return;
        }

        try {
            $path = $this->photos->store($space, $photo);
        } catch (Throwable $exception) {
            report($exception);

            throw $exception;
        }

        if ($path === null) {
            $failure = new RuntimeException('The submitted photo could not be stored for testimonial '.$testimonial->id.'.');

            report($failure);

            throw $failure;
        }

        $storedPhotoPath = $path;

        try {
            $testimonial->photo_path = $path;
            $testimonial->save();
        } catch (Throwable $exception) {
            // The file was written but the path never reached the
            // database. Remove it so no orphaned photo is left behind,
            // and clear the reference so the rollback callback does not
            // try to remove it a second time.
            $this->photos->delete($path);

            $storedPhotoPath = null;

            report($exception);

            throw $exception;
        }
    }
}
