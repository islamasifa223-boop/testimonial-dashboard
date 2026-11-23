 <?php use App\Models\Space;
use App\Models\Testimonial;
use App\Models\Widget;
use App\Services\TestimonialPhotoStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| The public widget photo
|--------------------------------------------------------------------------
|
| The photos live on the private disk, so the widget photo route is the
| only way a photo ever reaches a browser. Every request is checked
| against the widget rules again, because a photo that was shown
| yesterday may have been hidden, unapproved or have its consent
| withdrawn since.
|
| Every way of failing is the same 404. A different error for each
| cause would tell a visitor which testimonial ids exist and why one
| of them was refused, which is exactly what the widget must not
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
 * A space with a widget.
 *
 * The helper names are prefixed with "widgetPhoto", because the helper
 * names of another test file are not available when this file is run on
 * its own.
 */
function widgetPhotoSpace(): Space
{
    return Space::factory()->create();
}

/**
 * A widget of the given space.
 */
function widgetPhotoWidget(Space $space): Widget
{
    return Widget::factory()->for($space)->create();
}

/**
 * A testimonial that may be shown in a widget.
 *
 * All three conditions hold: approved, not hidden and consented. The
 * heart is deliberately left off, because a widget does not need it.
 */
function widgetPhotoTestimonial(
    Space $space,
    ?string $photoPath = null,
    array $attributes = [],
): Testimonial {
    return Testimonial::factory()
        ->for($space)
        ->approved()
        ->consented()
        ->create(array_merge(
            ['photo_path' => $photoPath],
            $attributes,
        ));
}

/**
 * The path a photo of the given space has on the disk.
 *
 * The name is a generated one, because the widget only serves a photo
 * whose path is shaped the way the service writes it.
 */
function widgetPhotoPath(Space $space, string $extension = 'png'): string
{
    return 'testimonials/'.$space->id.'/'.Str::uuid()->toString().'.'.$extension;
}

/**
 * Write a photo of the given space to the fake disk.
 *
 * The contents are a genuine PNG, so the mime type is detected from
 * the file itself and the served response is a real image.
 */
function widgetPhotoStore(Space $space, string $extension = 'png'): string
{
    $path = widgetPhotoPath($space, $extension);

    $upload = UploadedFile::fake()->image('portrait.png', 10, 10);

    Storage::disk(TestimonialPhotoStorage::DISK)->put($path, (string)
  file_get_contents($upload->getRealPath()));

    return $path;
}

/*
|--------------------------------------------------------------------------
| The photo is served
|--------------------------------------------------------------------------
*/

test('the photo of a testimonial shown in a widget is served', function () {
    $space = widgetPhotoSpace();

    $widget = widgetPhotoWidget($space);

    $path = widgetPhotoStore($space);

    $testimonial = widgetPhotoTestimonial($space, $path);

    $this->get(route('widgets.photos.show', [$widget->public_id, $testimonial->getKey()]))
        ->assertOk()
        ->assertStreamed();
});

test('the served photo is the photo that was stored', function () {
    $space = widgetPhotoSpace();

    $widget = widgetPhotoWidget($space);

    $path = widgetPhotoStore($space);

    $testimonial = widgetPhotoTestimonial($space, $path);

    $expected = Storage::disk(TestimonialPhotoStorage::DISK)->get($path);

    $this->get(route('widgets.photos.show', [$widget->public_id, $testimonial->getKey()]))
        ->assertOk()
        ->assertStreamedContent($expected);
});

test('the served photo is an image', function () {
    $space = widgetPhotoSpace();

    $widget = widgetPhotoWidget($space);

    $path = widgetPhotoStore($space);

    $testimonial = widgetPhotoTestimonial($space, $path);

    // The mime type is detected from the contents of the file, so a
    // browser renders the photo rather than downloading it.
    $this->get(route('widgets.photos.show', [$widget->public_id, $testimonial->getKey()]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');
});

test('the served photo is not cached', function () {
    $space = widgetPhotoSpace();

    $widget = widgetPhotoWidget($space);

    $path = widgetPhotoStore($space);

    $testimonial = widgetPhotoTestimonial($space, $path);

    $response = $this->get(route('widgets.photos.show', [$widget->public_id,
        $testimonial->getKey()]));

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
| The widget
|--------------------------------------------------------------------------
*/

test('an unknown widget returns a 404', function () {
    $this->get(route('widgets.photos.show', ['no-such-widget', 1]))->assertNotFound();
});

test('a widget of a soft deleted space returns a 404', function () {
    $space = widgetPhotoSpace();

    $widget = widgetPhotoWidget($space);

    $path = widgetPhotoStore($space);

    $testimonial = widgetPhotoTestimonial($space, $path);

    $space->delete();

    $this->get(route('widgets.photos.show', [$widget->public_id, $testimonial->getKey()]))
        ->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| The testimonial
|--------------------------------------------------------------------------
*/

test('a testimonial that does not exist returns a 404', function () {
    $space = widgetPhotoSpace();

    $widget = widgetPhotoWidget($space);

    $this->get(route('widgets.photos.show', [$widget->public_id, 999999]))->assertNotFound();
});

test('a testimonial of another space returns a 404', function () {
    $space = widgetPhotoSpace();

    $other = widgetPhotoSpace();

    $widget = widgetPhotoWidget($space);

    $path = widgetPhotoStore($space);

    $testimonial = widgetPhotoTestimonial($other, $path);

    // The photo belongs to the first space, and the testimonial to the
    // second. Neither of them is the space the route asked for, so the
    // photo is refused.
    $this->get(route('widgets.photos.show', [$widget->public_id, $testimonial->getKey()]))
        ->assertNotFound();
});

test('a non numeric testimonial id returns a 404', function () {
    $space = widgetPhotoSpace();

    $widget = widgetPhotoWidget($space);

    $this->get('/widget/'.$widget->public_id.'/photo/not-a-number')->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| The widget conditions
|--------------------------------------------------------------------------
*/

test('a testimonial that is not approved returns a 404', function () {
    $space = widgetPhotoSpace();

    $widget = widgetPhotoWidget($space);

    $path = widgetPhotoStore($space);

    $testimonial = Testimonial::factory()
        ->for($space)
        ->consented()
        ->create(['photo_path' => $path]);

    $this->get(route('widgets.photos.show', [$widget->public_id, $testimonial->getKey()]))
        ->assertNotFound();
});

test('a hidden testimonial returns a 404', function () {
    $space = widgetPhotoSpace();

    $widget = widgetPhotoWidget($space);

    $path = widgetPhotoStore($space);

    $testimonial = Testimonial::factory()
        ->for($space)
        ->approved()
        ->consented()
        ->hidden()
        ->create(['photo_path' => $path]);

    $this->get(route('widgets.photos.show', [$widget->public_id, $testimonial->getKey()]))
        ->assertNotFound();
});

test('a testimonial without consent returns a 404', function () {
    $space = widgetPhotoSpace();

    $widget = widgetPhotoWidget($space);

    $path = widgetPhotoStore($space);

    $testimonial = Testimonial::factory()
        ->for($space)
        ->approved()
        ->create(['photo_path' => $path]);

    $this->get(route('widgets.photos.show', [$widget->public_id, $testimonial->getKey()]))
        ->assertNotFound();
});

test('a testimonial without a heart is served', function () {
    $space = widgetPhotoSpace();

    $widget = widgetPhotoWidget($space);

    $path = widgetPhotoStore($space);

    $testimonial = widgetPhotoTestimonial($space, $path);

    // The heart belongs to the Wall of Love. It is the owner's own
    // selection there and does not decide whether a photo may be shown.
    expect($testimonial->hearted)->toBeFalse();

    $this->get(route('widgets.photos.show', [$widget->public_id, $testimonial->getKey()]))
        ->assertOk()
        ->assertStreamed();
});

/*
|--------------------------------------------------------------------------
| The photo
|--------------------------------------------------------------------------
*/

test('a testimonial without a photo returns a 404', function () {
    $space = widgetPhotoSpace();

    $widget = widgetPhotoWidget($space);

    $testimonial = widgetPhotoTestimonial($space, null);

    $this->get(route('widgets.photos.show', [$widget->public_id, $testimonial->getKey()]))
        ->assertNotFound();
});

test('a photo path outside the space directory returns a 404', function () {
    $space = widgetPhotoSpace();

    $other = widgetPhotoSpace();

    $widget = widgetPhotoWidget($space);

    // A well formed path, but it belongs to another space. The path is
    // taken from a row, so it is treated as untrusted input and has to
    // belong to the space the route asked for.
    $path = widgetPhotoPath($other);

    Storage::disk(TestimonialPhotoStorage::DISK)->put($path, 'the photo itself');

    $testimonial = widgetPhotoTestimonial($space, $path);

    $this->get(route('widgets.photos.show', [$widget->public_id, $testimonial->getKey()]))
        ->assertNotFound();
});

test('a photo path that escapes the space directory returns a 404', function () {
    $space = widgetPhotoSpace();

    $widget = widgetPhotoWidget($space);

    $path = 'testimonials/'.$space->id.'/../../other/'.Str::uuid()->toString().'.png';

    $testimonial = widgetPhotoTestimonial($space, $path);

    $this->get(route('widgets.photos.show', [$widget->public_id, $testimonial->getKey()]))
        ->assertNotFound();
});

test('a photo path that is not a server generated name returns a 404', function () {
    $space = widgetPhotoSpace();

    $widget = widgetPhotoWidget($space);

    $path = 'testimonials/'.$space->id.'/portrait.png';

    $testimonial = widgetPhotoTestimonial($space, $path);

    $this->get(route('widgets.photos.show', [$widget->public_id, $testimonial->getKey()]))
        ->assertNotFound();
});

test('a photo that is not on the disk returns a 404', function () {
    $space = widgetPhotoSpace();

    $widget = widgetPhotoWidget($space);

    // The path is well formed and belongs to the space, but there is no
    // file behind it. Without the check for the file, the response would
    // be built with an empty body instead of a 404.
    $path = widgetPhotoPath($space);

    $testimonial = widgetPhotoTestimonial($space, $path);

    $this->get(route('widgets.photos.show', [$widget->public_id, $testimonial->getKey()]))
        ->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| The rules are checked on every request
|--------------------------------------------------------------------------
*/

test('unapproving the testimonial takes the photo away at once', function () {
    $space = widgetPhotoSpace();

    $widget = widgetPhotoWidget($space);

    $path = widgetPhotoStore($space);

    $testimonial = widgetPhotoTestimonial($space, $path);

    $url = route('widgets.photos.show', [$widget->public_id, $testimonial->getKey()]);

    $this->get($url)->assertOk();

    $testimonial->approved = false;
    $testimonial->save();

    $this->get($url)->assertNotFound();
});

test('hiding the testimonial takes the photo away at once', function () {
    $space = widgetPhotoSpace();

    $widget = widgetPhotoWidget($space);

    $path = widgetPhotoStore($space);

    $testimonial = widgetPhotoTestimonial($space, $path);

    $url = route('widgets.photos.show', [$widget->public_id, $testimonial->getKey()]);

    $this->get($url)->assertOk();

    $testimonial->hidden = true;
    $testimonial->save();

    $this->get($url)->assertNotFound();
});

test('withdrawing the consent takes the photo away at once', function () {
    $space = widgetPhotoSpace();

    $widget = widgetPhotoWidget($space);

    $path = widgetPhotoStore($space);

    $testimonial = widgetPhotoTestimonial($space, $path);

    $url = route('widgets.photos.show', [$widget->public_id, $testimonial->getKey()]);

    $this->get($url)->assertOk();

    $testimonial->consented = false;
    $testimonial->save();

    $this->get($url)->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| Nothing private is exposed
|--------------------------------------------------------------------------
*/

test('the route does not expose the storage path', function () {
    $space = widgetPhotoSpace();

    $widget = widgetPhotoWidget($space);

    $path = widgetPhotoStore($space);

    $testimonial = widgetPhotoTestimonial($space, $path);

    $response = $this->get(route('widgets.photos.show', [$widget->public_id,
        $testimonial->getKey()]));

    $response->assertOk();

    // The path is an internal detail of the private disk. It appears in
    // no header of the response.
    expect($response->headers->get('Content-Disposition'))->not->toContain($path);

    foreach ($response->headers->all() as $name => $values) {
        foreach ((array) $values as $value) {
            expect($value)->not->toContain('testimonials/'.$space->id.'/');
        }
    }
});
