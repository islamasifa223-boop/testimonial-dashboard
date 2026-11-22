<?php

use App\Models\Space;
use App\Services\TestimonialPhotoStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/*
 * The private photo storage of a testimonial.
 *
 * The photos live on the private "local" disk, so nothing serves them
 * on its own. The service is the only place that knows how a photo
 * path is shaped and whether a path belongs to the space it was
 * supposed to be uploaded for, so these tests pin that shape down.
 *
 * Every test runs against a faked "local" disk, which only ever
 * writes to storage/framework/testing/disks/local. The real private
 * disk is never touched.
 */

beforeEach(function () {
    seedPlans();

    Storage::fake(TestimonialPhotoStorage::DISK);
});

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * A space with a photo ready to be stored.
 *
 * No slug is set, so the factory generates a unique one. Two spaces
 * in the same test would otherwise collide on the unique slug.
 */
function photoStorageSpace(): Space
{
    return Space::factory()->create();
}

/**
 * A genuine PNG upload.
 *
 * The extension check of the service is driven by the mime type of
 * the file, so the file has to be a real image. GD is loaded, so the
 * fake image is a real PNG.
 */
function photoStorageUpload(): UploadedFile
{
    return UploadedFile::fake()->image('portrait.png', 10, 10);
}

/**
 * The body of a streamed response.
 *
 * A StreamedResponse does not return its content through
 * getContent(), so the stream is read through an output buffer.
 * The buffer opened here is the one that is closed again, whether the
 * stream was read or the send failed part way through.
 */
function photoStorageStreamedBody(StreamedResponse $response): string
{
    $level = ob_get_level();

    ob_start();

    try {
        $response->sendContent();

        return (string) ob_get_clean();
    } finally {
        while (ob_get_level() > $level) {
            ob_end_clean();
        }
    }
}

/*
|--------------------------------------------------------------------------
| directoryFor()
|--------------------------------------------------------------------------
*/

test('the photo directory of a space is the space directory under testimonials', function () {
    $space = photoStorageSpace();

    $storage = app(TestimonialPhotoStorage::class);

    expect($storage->directoryFor($space))->toBe('testimonials/'.$space->id);
});

test('the photo directory of two spaces differs', function () {
    $first = photoStorageSpace();
    $second = photoStorageSpace();

    $storage = app(TestimonialPhotoStorage::class);

    expect($storage->directoryFor($first))->not->toBe($storage->directoryFor($second));
});

/*
|--------------------------------------------------------------------------
| belongsToSpace()
|--------------------------------------------------------------------------
*/

test('a stored photo path belongs to its own space', function (string $extension) {
    $space = photoStorageSpace();

    $storage = app(TestimonialPhotoStorage::class);

    $path = 'testimonials/'.$space->id.'/'.Str::uuid()->toString().'.'.$extension;

    expect($storage->belongsToSpace($path, $space))->toBeTrue();
})->with([
    'jpg' => 'jpg',
    'png' => 'png',
    'webp' => 'webp',
]);

test('a path that is not a photo path belongs to no space', function (?string $template) {
    $space = photoStorageSpace();

    $storage = app(TestimonialPhotoStorage::class);

    $path = $template === null
        ? null
        : str_replace('{space}', (string) $space->id, $template);

    expect($storage->belongsToSpace($path, $space))->toBeFalse();
})->with([
    'null' => null,
    'empty' => '',
    'a directory' => 'testimonials/{space}',
    'a path with a trailing slash' => 'testimonials/{space}/',
    'the root of the disk' => 'testimonials',
    'a path outside testimonials' => 'uploads/{space}/'.Str::uuid()->toString().'.png',
]);

test('a file name that is not a uuid belongs to no space', function (string $name) {
    $space = photoStorageSpace();

    $storage = app(TestimonialPhotoStorage::class);

    $path = 'testimonials/'.$space->id.'/'.$name.'.png';

    expect($storage->belongsToSpace($path, $space))->toBeFalse();
})->with([
    'a plain name' => 'portrait',
    'a uuid with a character that is not hex' => str_replace('-', 'z', Str::uuid()->toString()),
    'a uuid without dashes' => str_replace('-', '', Str::uuid()->toString()),
    'a name with a second extension' => 'portrait.png',
]);

test('a path that escapes the photo directory belongs to no space', function (string $template) {
    $space = photoStorageSpace();

    $storage = app(TestimonialPhotoStorage::class);

    $path = str_replace('{space}', (string) $space->id, $template);

    expect($storage->belongsToSpace($path, $space))->toBeFalse();
})->with([
    'a parent directory' => 'testimonials/{space}/../other/'.Str::uuid()->toString().'.png',
    'the current directory' => 'testimonials/{space}/./'.Str::uuid()->toString().'.png',
    'a traversal out of testimonials' => 'testimonials/{space}/../../other/'.Str::uuid()->toString().'.png',
    'a traversal out of the root' => '../testimonials/{space}/'.Str::uuid()->toString().'.png',
]);

test('a path with a backslash belongs to no space', function (string $template) {
    $space = photoStorageSpace();

    $storage = app(TestimonialPhotoStorage::class);

    $path = str_replace('{space}', (string) $space->id, $template);

    expect($storage->belongsToSpace($path, $space))->toBeFalse();
})->with([
    'a windows separator' => 'testimonials/{space}\\'.Str::uuid()->toString().'.png',
    'a windows separator in the name' => 'testimonials/{space}/'.Str::uuid()->toString().'\\portrait.png',
]);

test('a path of another space belongs to no space', function (string $template) {
    $space = photoStorageSpace();

    $other = photoStorageSpace();

    $storage = app(TestimonialPhotoStorage::class);

    $path = str_replace('{space}', (string) $space->id, $template);

    expect($storage->belongsToSpace($path, $other))->toBeFalse();
})->with([
    'another space' => 'testimonials/{space}/'.Str::uuid()->toString().'.png',
]);

test('a path with an unsupported extension belongs to no space', function (string $extension) {
    $space = photoStorageSpace();

    $storage = app(TestimonialPhotoStorage::class);

    $path = 'testimonials/'.$space->id.'/'.Str::uuid()->toString().'.'.$extension;

    expect($storage->belongsToSpace($path, $space))->toBeFalse();
})->with([
    'gif' => 'gif',
    'svg' => 'svg',
    'txt' => 'txt',
    'php' => 'php',
]);

test('a path of a testimonial without a photo belongs to no space', function () {
    $space = photoStorageSpace();

    $storage = app(TestimonialPhotoStorage::class);

    expect($storage->belongsToSpace(null, $space))->toBeFalse()
        ->and($storage->belongsToSpace('', $space))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| store()
|--------------------------------------------------------------------------
*/

test('a stored photo lives in the directory of its space', function () {
    $space = photoStorageSpace();

    $storage = app(TestimonialPhotoStorage::class);

    $path = $storage->store($space, photoStorageUpload());

    expect($path)->toStartWith('testimonials/'.$space->id.'/');

    Storage::disk(TestimonialPhotoStorage::DISK)->assertExists($path);
});

test('a stored photo is named by the server and never by the upload', function () {
    $space = photoStorageSpace();

    $storage = app(TestimonialPhotoStorage::class);

    $path = $storage->store($space, photoStorageUpload());

    expect(pathinfo($path, PATHINFO_EXTENSION))->toBe('png')
        ->and(pathinfo($path, PATHINFO_FILENAME))->not->toBe('portrait');
});

/*
|--------------------------------------------------------------------------
| exists()
|--------------------------------------------------------------------------
*/

test('exists reports a photo that is on the disk', function () {
    $space = photoStorageSpace();

    $storage = app(TestimonialPhotoStorage::class);

    $path = $storage->store($space, photoStorageUpload());

    expect($storage->exists($path))->toBeTrue();
});

test('exists reports a photo that is not on the disk', function () {
    $space = photoStorageSpace();

    $storage = app(TestimonialPhotoStorage::class);

    $path = 'testimonials/'.$space->id.'/'.Str::uuid()->toString().'.png';

    expect($storage->exists($path))->toBeFalse();
});

test('exists reports nothing for a missing path', function () {
    $storage = app(TestimonialPhotoStorage::class);

    expect($storage->exists(null))->toBeFalse()
        ->and($storage->exists(''))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| response()
|--------------------------------------------------------------------------
*/

test('the response carries the photo bytes', function () {
    $space = photoStorageSpace();

    $storage = app(TestimonialPhotoStorage::class);

    $path = $storage->store($space, photoStorageUpload());

    $expected = Storage::disk(TestimonialPhotoStorage::DISK)->get($path);

    $response = $storage->response($path);

    expect(photoStorageStreamedBody($response))->toBe($expected);
});

test('the response is not cached', function () {
    $space = photoStorageSpace();

    $storage = app(TestimonialPhotoStorage::class);

    $path = $storage->store($space, photoStorageUpload());

    $response = $storage->response($path);

    // The header is read through the directive lookup rather than
    // compared as a string. Symfony re-serialises the Cache-Control
    // header in its own order, so a string comparison would fail on the
    // order alone even though the directives are the ones the photo
    // needs. The lookup is by directive name, so the order does not
    // matter.
    expect($response->headers->hasCacheControlDirective('private'))->toBeTrue()
        ->and($response->headers->getCacheControlDirective('private'))->toBeTrue()
        ->and($response->headers->hasCacheControlDirective('no-store'))->toBeTrue()
        ->and($response->headers->getCacheControlDirective('no-store'))->toBeTrue();
});
