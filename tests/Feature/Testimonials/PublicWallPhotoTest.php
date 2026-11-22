<?php

use App\Models\Space;
use App\Models\Testimonial;
use App\Services\TestimonialPhotoStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| The public Wall of Love photo
|--------------------------------------------------------------------------
|
| The photos live on the private disk, so the wall photo route is the
| only way a photo ever reaches a browser. Every request is checked
| against the wall rules again, because a photo that was on the wall
| yesterday may have been taken off it since.
|
| Every way of failing has to be the same 404. A different error for
| each cause would tell a visitor which testimonial ids exist and why
| one of them was refused, which is exactly what the wall must not
| reveal.
|
| Every test runs against a faked "local" disk, which only ever writes
| to storage/framework/testing/disks/local. The real private disk is
| never touched.
|
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
 * A space with a wall.
 *
 * No slug is set, so the factory generates a unique one. A test that
 * creates two spaces would otherwise collide on the unique slug.
 */
function wallPhotoSpace(): Space
{
    return Space::factory()->create();
}

/**
 * A testimonial that is on the wall.
 *
 * All four conditions hold: hearted, approved, not hidden and
 * consented. A test that needs one of them broken overrides the state
 * it cares about.
 */
function wallPhotoTestimonial(Space $space, ?string $photoPath = null): Testimonial
{
    return Testimonial::factory()
        ->for($space)
        ->approved()
        ->consented()
        ->hearted()
        ->create(['photo_path' => $photoPath]);
}

/**
 * The path a photo of the given space has on the disk.
 *
 * The name is a generated one, because the wall only serves a photo
 * whose path is shaped the way the service writes it.
 */
function wallPhotoPath(Space $space, string $extension = 'png'): string
{
    return 'testimonials/'.$space->id.'/'.Str::uuid()->toString().'.'.$extension;
}

/**
 * Write a photo of the given space to the fake disk.
 *
 * The contents are a genuine PNG, so the mime type is detected from
 * the file itself and the served response is a real image.
 */
function wallPhotoStore(Space $space, string $extension = 'png'): string
{
    $path = wallPhotoPath($space, $extension);

    $upload = UploadedFile::fake()->image('portrait.png', 10, 10);

    Storage::disk(TestimonialPhotoStorage::DISK)->put($path, (string) file_get_contents($upload->getRealPath()));

    return $path;
}

/*
|--------------------------------------------------------------------------
| The photo is served
|--------------------------------------------------------------------------
*/

test('the photo of a testimonial on the wall is served', function () {
    $space = wallPhotoSpace();

    $path = wallPhotoStore($space);

    $testimonial = wallPhotoTestimonial($space, $path);

    $this->get(route('walls.photos.show', [$space->slug, $testimonial->getKey()]))
        ->assertOk()
        ->assertStreamed();
});

test('the served photo is the photo that was stored', function () {
    $space = wallPhotoSpace();

    $path = wallPhotoStore($space);

    $testimonial = wallPhotoTestimonial($space, $path);

    $expected = Storage::disk(TestimonialPhotoStorage::DISK)->get($path);

    $this->get(route('walls.photos.show', [$space->slug, $testimonial->getKey()]))
        ->assertOk()
        ->assertStreamedContent($expected);
});

test('the served photo is an image', function () {
    $space = wallPhotoSpace();

    $path = wallPhotoStore($space);

    $testimonial = wallPhotoTestimonial($space, $path);

    // The mime type is detected from the contents of the file, so a
    // browser renders the photo rather than downloading it.
    $this->get(route('walls.photos.show', [$space->slug, $testimonial->getKey()]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

test('the served photo is not cached', function () {
    $space = wallPhotoSpace();

    $path = wallPhotoStore($space);

    $testimonial = wallPhotoTestimonial($space, $path);

    $response = $this->get(route('walls.photos.show', [$space->slug, $testimonial->getKey()]));

    $response->assertOk();

    // The header is read through the directive lookup rather than
    // compared as a string, because Symfony re-serialises the header
    // in its own order.
    expect($response->headers->hasCacheControlDirective('private'))->toBeTrue()
        ->and($response->headers->getCacheControlDirective('private'))->toBeTrue()
        ->and($response->headers->hasCacheControlDirective('no-store'))->toBeTrue()
        ->and($response->headers->getCacheControlDirective('no-store'))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| The space
|--------------------------------------------------------------------------
*/

test('an unknown space returns a 404', function () {
    $this->get(route('walls.photos.show', ['no-such-space', 1]))->assertNotFound();
});

test('a soft deleted space returns a 404', function () {
    $space = wallPhotoSpace();

    $path = wallPhotoStore($space);

    $testimonial = wallPhotoTestimonial($space, $path);

    $space->delete();

    $this->get(route('walls.photos.show', [$space->slug, $testimonial->getKey()]))
        ->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| The testimonial
|--------------------------------------------------------------------------
*/

test('a testimonial that does not exist returns a 404', function () {
    $space = wallPhotoSpace();

    $this->get(route('walls.photos.show', [$space->slug, 999999]))->assertNotFound();
});

test('a testimonial of another space returns a 404', function () {
    $space = wallPhotoSpace();

    $other = wallPhotoSpace();

    $path = wallPhotoStore($space);

    $testimonial = wallPhotoTestimonial($other, $path);

    // The photo belongs to the first space, and the testimonial to the
    // second. Neither of them is the space the route asked for, so the
    // photo is refused.
    $this->get(route('walls.photos.show', [$space->slug, $testimonial->getKey()]))
        ->assertNotFound();
});

test('a non numeric testimonial id returns a 404', function () {
    $space = wallPhotoSpace();

    $this->get('/wall/'.$space->slug.'/photo/not-a-number')->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| The wall conditions
|--------------------------------------------------------------------------
*/

test('a testimonial without a heart returns a 404', function () {
    $space = wallPhotoSpace();

    $path = wallPhotoStore($space);

    $testimonial = Testimonial::factory()
        ->for($space)
        ->approved()
        ->consented()
        ->create(['photo_path' => $path]);

    $this->get(route('walls.photos.show', [$space->slug, $testimonial->getKey()]))
        ->assertNotFound();
});

test('a testimonial that is not approved returns a 404', function () {
    $space = wallPhotoSpace();

    $path = wallPhotoStore($space);

    $testimonial = Testimonial::factory()
        ->for($space)
        ->consented()
        ->hearted()
        ->create(['photo_path' => $path]);

    $this->get(route('walls.photos.show', [$space->slug, $testimonial->getKey()]))
        ->assertNotFound();
});

test('a hidden testimonial returns a 404', function () {
    $space = wallPhotoSpace();

    $path = wallPhotoStore($space);

    $testimonial = Testimonial::factory()
        ->for($space)
        ->approved()
        ->consented()
        ->hearted()
        ->hidden()
        ->create(['photo_path' => $path]);

    $this->get(route('walls.photos.show', [$space->slug, $testimonial->getKey()]))
        ->assertNotFound();
});

test('a testimonial without consent returns a 404', function () {
    $space = wallPhotoSpace();

    $path = wallPhotoStore($space);

    $testimonial = Testimonial::factory()
        ->for($space)
        ->approved()
        ->hearted()
        ->create(['photo_path' => $path]);

    $this->get(route('walls.photos.show', [$space->slug, $testimonial->getKey()]))
        ->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| The photo
|--------------------------------------------------------------------------
*/

test('a testimonial without a photo returns a 404', function () {
    $space = wallPhotoSpace();

    $testimonial = wallPhotoTestimonial($space, null);

    $this->get(route('walls.photos.show', [$space->slug, $testimonial->getKey()]))
        ->assertNotFound();
});

test('a photo path outside the space directory returns a 404', function () {
    $space = wallPhotoSpace();

    $other = wallPhotoSpace();

    // A well formed path, but it belongs to another space. The path is
    // taken from a row, so it is treated as untrusted input and has to
    // belong to the space the route asked for.
    $path = wallPhotoPath($other);

    Storage::disk(TestimonialPhotoStorage::DISK)->put($path, 'the photo itself');

    $testimonial = wallPhotoTestimonial($space, $path);

    $this->get(route('walls.photos.show', [$space->slug, $testimonial->getKey()]))
        ->assertNotFound();
});

test('a photo path that escapes the space directory returns a 404', function () {
    $space = wallPhotoSpace();

    $path = 'testimonials/'.$space->id.'/../../other/'.Str::uuid()->toString().'.png';

    $testimonial = wallPhotoTestimonial($space, $path);

    $this->get(route('walls.photos.show', [$space->slug, $testimonial->getKey()]))
        ->assertNotFound();
});

test('a photo path that is not a server generated name returns a 404', function () {
    $space = wallPhotoSpace();

    $path = 'testimonials/'.$space->id.'/portrait.png';

    $testimonial = wallPhotoTestimonial($space, $path);

    $this->get(route('walls.photos.show', [$space->slug, $testimonial->getKey()]))
        ->assertNotFound();
});

test('a photo that is not on the disk returns a 404', function () {
    $space = wallPhotoSpace();

    // The path is well formed and belongs to the space, but there is no
    // file behind it. Without the check for the file, the response
    // would be built with an empty body instead of a 404.
    $path = wallPhotoPath($space);

    $testimonial = wallPhotoTestimonial($space, $path);

    $this->get(route('walls.photos.show', [$space->slug, $testimonial->getKey()]))
        ->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| The wall rules are checked on every request
|--------------------------------------------------------------------------
*/

test('removing the heart takes the photo off the wall at once', function () {
    $space = wallPhotoSpace();

    $path = wallPhotoStore($space);

    $testimonial = wallPhotoTestimonial($space, $path);

    $url = route('walls.photos.show', [$space->slug, $testimonial->getKey()]);

    $this->get($url)->assertOk();

    $testimonial->hearted = false;
    $testimonial->save();

    $this->get($url)->assertNotFound();
});

test('hiding the testimonial takes the photo off the wall at once', function () {
    $space = wallPhotoSpace();

    $path = wallPhotoStore($space);

    $testimonial = wallPhotoTestimonial($space, $path);

    $url = route('walls.photos.show', [$space->slug, $testimonial->getKey()]);

    $this->get($url)->assertOk();

    $testimonial->hidden = true;
    $testimonial->save();

    $this->get($url)->assertNotFound();
});

test('withdrawing the consent takes the photo off the wall at once', function () {
    $space = wallPhotoSpace();

    $path = wallPhotoStore($space);

    $testimonial = wallPhotoTestimonial($space, $path);

    $url = route('walls.photos.show', [$space->slug, $testimonial->getKey()]);

    $this->get($url)->assertOk();

    $testimonial->consented = false;
    $testimonial->save();

    $this->get($url)->assertNotFound();
});
