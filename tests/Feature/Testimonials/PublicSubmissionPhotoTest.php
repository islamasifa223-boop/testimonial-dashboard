<?php

use App\Models\Space;
use App\Models\Testimonial;
use App\Services\TestimonialPhotoStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Photo uploads for public testimonial submissions
|--------------------------------------------------------------------------
|
| These tests cover the photo half of the anonymous submission flow at
| /s/{slug}. They prove that an uploaded photo is written to the private
| disk under a generated name, and that the file is removed again when
| the database transaction rolls back or the testimonial cannot be
| saved, so no orphaned photo is ever left behind.
|
| Every test runs against a faked "local" disk, which only ever writes
| to storage/framework/testing/disks/local. The real private disk is
| never touched.
|
*/

beforeEach(function () {
    seedPlans();

    Storage::fake(TestimonialPhotoStorage::DISK);

    // The tests below make the controller fail on purpose. The handler
    // reports those failures, which would otherwise be written to the
    // real application log. The spy takes the place of the logger, so
    // nothing is written to disk.
    Log::spy();
});

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * Create a space that collects photos.
 *
 * The factory does not collect photos by default, and a space that does
 * not collect them has the upload removed from the request before it
 * reaches the controller.
 */
function photoSpace(array $attributes = []): Space
{
    return Space::factory()->create(array_merge([
        'slug' => 'photo-space',
        'photo_enabled' => true,
        'photo_required' => false,
    ], $attributes));
}

/**
 * A real PNG image, small enough to pass the size rule.
 */
function pngUpload(string $name = 'avatar.png'): UploadedFile
{
    return UploadedFile::fake()->image($name, 10, 10);
}

/*
|--------------------------------------------------------------------------
| A successful upload
|--------------------------------------------------------------------------
*/

test('a submission with a photo stores the file and records its path', function () {
    $space = photoSpace();

    $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space, [
        'photo' => pngUpload(),
    ]))
        ->assertRedirect(route('spaces.testimonials.thanks', $space->slug));

    $testimonial = Testimonial::query()->firstOrFail();

    expect($testimonial->photo_path)->not->toBeNull();

    Storage::disk(TestimonialPhotoStorage::DISK)->assertExists($testimonial->photo_path);
});

test('the stored photo is named by the server and lives under the space directory', function () {
    $space = photoSpace();

    $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space, [
        'photo' => pngUpload('portrait.png'),
    ]))
        ->assertRedirect(route('spaces.testimonials.thanks', $space->slug));

    $path = Testimonial::query()->firstOrFail()->photo_path;

    // The name of the upload is never used on disk, so a visitor cannot
    // choose where the file lands or what it is called.
    expect($path)->toStartWith('testimonials/'.$space->id.'/')
        ->and(basename($path))->not->toBe('portrait.png')
        ->and(basename($path))->toEndWith('.png')
        // A relative path, so it is safe to store and to serve again.
        ->and($path)->not->toStartWith('/');
});

test('a submission without a photo stores no path and writes no file', function () {
    $space = photoSpace();

    $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space))
        ->assertRedirect(route('spaces.testimonials.thanks', $space->slug));

    $testimonial = Testimonial::query()->firstOrFail();

    expect($testimonial->photo_path)->toBeNull();

    expect(Storage::disk(TestimonialPhotoStorage::DISK)->allFiles())->toBe([]);
});

/*
|--------------------------------------------------------------------------
| Cleanup when the transaction rolls back
|--------------------------------------------------------------------------
*/

test('the photo is removed when the transaction rolls back', function () {
    $space = photoSpace();

    $listener = function (): void {
        throw new RuntimeException('The testimonial could not be created.');
    };

    Testimonial::created($listener);

    try {
        $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space, [
            'photo' => pngUpload(),
        ]))->assertStatus(500);
    } finally {
        removeModelEventListener('created', Testimonial::class, $listener);
    }

    // The row is gone because the transaction rolled back...
    expect(Testimonial::query()->count())->toBe(0);

    // ...and so is the file, because the rollback callback deleted it.
    expect(Storage::disk(TestimonialPhotoStorage::DISK)->allFiles())->toBe([]);
});

/*
|--------------------------------------------------------------------------
| Cleanup when the testimonial cannot be saved
|--------------------------------------------------------------------------
*/

test('the photo is removed when saving the testimonial fails', function () {
    $space = photoSpace();

    // The controller saves the testimonial twice: once when the row is
    // created, and once more after the photo path is attached. The
    // listener only fails the second save, which is the save that
    // happens after the file has already been written to the disk.
    $listener = function (Testimonial $testimonial): void {
        if ($testimonial->photo_path !== null) {
            throw new RuntimeException('The testimonial could not be saved.');
        }
    };

    Testimonial::saving($listener);

    try {
        $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space, [
            'photo' => pngUpload(),
        ]))->assertStatus(500);
    } finally {
        removeModelEventListener('saving', Testimonial::class, $listener);
    }

    expect(Testimonial::query()->count())->toBe(0);

    expect(Storage::disk(TestimonialPhotoStorage::DISK)->allFiles())->toBe([]);
});

/*
|--------------------------------------------------------------------------
| Failures are reported
|--------------------------------------------------------------------------
*/

test('a failure while saving the testimonial is reported', function () {
    $space = photoSpace();

    Log::spy();

    $listener = function (Testimonial $testimonial): void {
        if ($testimonial->photo_path !== null) {
            throw new RuntimeException('The testimonial could not be saved.');
        }
    };

    Testimonial::saving($listener);

    try {
        $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space, [
            'photo' => pngUpload(),
        ]))->assertStatus(500);
    } finally {
        removeModelEventListener('saving', Testimonial::class, $listener);
    }

    Log::shouldHaveReceived('error');
});

/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

test('a file that is not an image is rejected and nothing is stored', function () {
    $space = photoSpace();

    $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space, [
        'photo' => UploadedFile::fake()->create('notes.txt', 8, 'text/plain'),
    ]))
        ->assertSessionHasErrors('photo');

    expect(Testimonial::query()->count())->toBe(0);

    expect(Storage::disk(TestimonialPhotoStorage::DISK)->allFiles())->toBe([]);
});

test('a photo larger than the limit is rejected and nothing is stored', function () {
    $space = photoSpace();

    $this->post(route('spaces.testimonials.store', $space->slug), validSubmission($space, [
        'photo' => UploadedFile::fake()->create('huge.png', 3000, 'image/png'),
    ]))
        ->assertSessionHasErrors('photo');

    expect(Testimonial::query()->count())->toBe(0);

    expect(Storage::disk(TestimonialPhotoStorage::DISK)->allFiles())->toBe([]);
});
