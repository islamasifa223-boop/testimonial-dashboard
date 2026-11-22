<?php

use App\Models\Space;
use App\Models\Testimonial;
use App\Models\User;
use App\Services\TestimonialPhotoStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/*
 * The owner can delete a testimonial from the inbox. The delete removes
 * the row and the private photo that belongs to it.
 *
 * The order matters. The row goes first, so the testimonial is gone even
 * when the photo cannot be removed. A photo that fails to delete is
 * reported and swallowed: an orphaned file is untidy, but far less bad
 * than a testimonial the owner deleted and is still looking at.
 *
 * A testimonial is deleted for good. The table has no deleted_at column
 * and the model does not use soft deletes, so there is no trashed copy
 * to recover and no withTrashed() to reach for.
 *
 * Every check that a row is gone reads the table directly through DB,
 * not through the model. The model query applies the global scopes, so
 * a soft deleted row would be hidden by them and the check would pass
 * even though nothing had been deleted at all. Reading the table asks
 * the question that actually matters: is the row still there?
 *
 * A note on names. The layout renders the name of the signed in user,
 * and the name of the space appears as a badge on every testimonial row.
 * Both factories generate random names, so every fixture here pins an
 * explicit name. Without that, a random name could collide with a name
 * a test asserts is absent, and the assertion would fail for the wrong
 * reason.
 */

beforeEach(function () {
    seedPlans();

    // Every photo test runs against a faked "local" disk, which only
    // ever writes to storage/framework/testing/disks/local. The real
    // private disk is never touched.
    Storage::fake(TestimonialPhotoStorage::DISK);
});

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * A storage service whose photo deletion always fails.
 *
 * The private disk is configured not to throw, and the underlying
 * filesystem only fails when the file really cannot be removed. Neither
 * is something a test can rely on, so the failure is injected here
 * instead. The controller resolves this service from the container, so
 * replacing it here replaces it for the request.
 */
function failingPhotoStorage(): TestimonialPhotoStorage
{
    return new class extends TestimonialPhotoStorage
    {
        public function delete(?string $path): void
        {
            throw new RuntimeException('The photo could not be removed.');
        }
    };
}

/**
 * Whether the row of a testimonial is still in the table.
 *
 * The table is read directly, so the answer does not depend on the
 * global scopes of the model.
 */
function testimonialRowExists(int $id): bool
{
    return DB::table('testimonials')->where('id', $id)->exists();
}

/*
|--------------------------------------------------------------------------
| Deleting
|--------------------------------------------------------------------------
*/

test('a testimonial can be deleted by its owner', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    $testimonial = Testimonial::factory()->for($space)->create([
        'name' => 'Ada Lovelace',
        'body' => 'A wonderfully ordinary testimonial.',
    ]);

    $this->actingAs($user)
        ->delete(route('dashboard.testimonials.destroy', $testimonial))
        ->assertRedirect(route('dashboard.inbox'))
        ->assertSessionHas('status', 'The testimonial has been deleted.');

    expect(testimonialRowExists($testimonial->getKey()))->toBeFalse();
});

test('the deleted testimonial is gone for good', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    $testimonial = Testimonial::factory()->for($space)->create([
        'name' => 'Ada Lovelace',
    ]);

    $this->actingAs($user)
        ->delete(route('dashboard.testimonials.destroy', $testimonial));

    // The table has no deleted_at column, so the row is gone and there
    // is no trashed copy hiding behind a scope.
    expect(testimonialRowExists($testimonial->getKey()))->toBeFalse()
        ->and(DB::table('testimonials')->count())->toBe(0);
});

test('the deleted testimonial no longer appears in the inbox', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    $testimonial = Testimonial::factory()->for($space)->create([
        'name' => 'Ada Lovelace',
    ]);

    $this->actingAs($user)
        ->delete(route('dashboard.testimonials.destroy', $testimonial));

    // The row is gone, so the empty inbox is the result of the delete
    // and not of an inbox that had nothing to show in the first place.
    expect(testimonialRowExists($testimonial->getKey()))->toBeFalse();

    $this->actingAs($user)
        ->get(route('dashboard.inbox'))
        ->assertOk()
        ->assertDontSee('Ada Lovelace')
        ->assertSee('No testimonials yet');
});

test('deleting a testimonial leaves the other testimonials alone', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    $testimonial = Testimonial::factory()->for($space)->create([
        'name' => 'Ada Lovelace',
    ]);

    $keeper = Testimonial::factory()->for($space)->create([
        'name' => 'Grace Hopper',
    ]);

    $this->actingAs($user)
        ->delete(route('dashboard.testimonials.destroy', $testimonial));

    // The other testimonial is still in the table and still live to the
    // application, so it is untouched rather than merely hidden.
    expect(testimonialRowExists($keeper->getKey()))->toBeTrue()
        ->and(Testimonial::query()->whereKey($keeper->getKey())->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| The photo
|--------------------------------------------------------------------------
*/

test('the private photo of a deleted testimonial is removed', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    $path = 'testimonials/'.$space->id.'/portrait.png';

    Storage::disk(TestimonialPhotoStorage::DISK)->put($path, 'the photo itself');

    $testimonial = Testimonial::factory()->for($space)->create([
        'name' => 'Ada Lovelace',
        'photo_path' => $path,
    ]);

    $this->actingAs($user)
        ->delete(route('dashboard.testimonials.destroy', $testimonial))
        ->assertRedirect(route('dashboard.inbox'));

    Storage::disk(TestimonialPhotoStorage::DISK)->assertMissing($path);

    expect(Storage::disk(TestimonialPhotoStorage::DISK)->allFiles())->toBe([]);
});

test('a testimonial without a photo leaves no file behind', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    // A file that has nothing to do with the testimonial. It is the
    // witness: if the delete touched the disk at all, this file would be
    // the first thing to suffer.
    $unrelated = 'unrelated.txt';

    Storage::disk(TestimonialPhotoStorage::DISK)->put($unrelated, 'nothing to do with a testimonial');

    $testimonial = Testimonial::factory()->for($space)->create([
        'name' => 'Ada Lovelace',
        'photo_path' => null,
    ]);

    $this->actingAs($user)
        ->delete(route('dashboard.testimonials.destroy', $testimonial))
        ->assertRedirect(route('dashboard.inbox'));

    // The file that was already there is still there, untouched.
    Storage::disk(TestimonialPhotoStorage::DISK)
        ->assertExists($unrelated, 'nothing to do with a testimonial');

    // And it is the only file on the disk, so the delete neither removed
    // anything it should have kept nor left anything behind.
    expect(Storage::disk(TestimonialPhotoStorage::DISK)->allFiles())->toBe([$unrelated]);
});

test('deleting a testimonial without a photo still succeeds', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    $testimonial = Testimonial::factory()->for($space)->create([
        'name' => 'Ada Lovelace',
        'photo_path' => null,
    ]);

    $this->actingAs($user)
        ->delete(route('dashboard.testimonials.destroy', $testimonial))
        ->assertRedirect(route('dashboard.inbox'))
        ->assertSessionHas('status', 'The testimonial has been deleted.');

    expect(testimonialRowExists($testimonial->getKey()))->toBeFalse();
});

test('a failure to remove the photo does not undo the deletion', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    $this->instance(TestimonialPhotoStorage::class, failingPhotoStorage());

    $testimonial = Testimonial::factory()->for($space)->create([
        'name' => 'Ada Lovelace',
        'photo_path' => 'testimonials/'.$space->id.'/portrait.png',
    ]);

    // The request still succeeds, because the row is gone and the
    // orphaned file is the lesser problem.
    $this->actingAs($user)
        ->delete(route('dashboard.testimonials.destroy', $testimonial))
        ->assertRedirect(route('dashboard.inbox'))
        ->assertSessionHas('status', 'The testimonial has been deleted.');

    expect(testimonialRowExists($testimonial->getKey()))->toBeFalse();
});

test('a failure to remove the photo is reported', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    // The handler writes the failure to the log. The spy takes the place
    // of the logger, so nothing is written to disk.
    Log::spy();

    $this->instance(TestimonialPhotoStorage::class, failingPhotoStorage());

    $testimonial = Testimonial::factory()->for($space)->create([
        'name' => 'Ada Lovelace',
        'photo_path' => 'testimonials/'.$space->id.'/portrait.png',
    ]);

    // The request completed and the row is gone, so the error in the log
    // is the swallowed photo failure and not a request that fell over.
    $this->actingAs($user)
        ->delete(route('dashboard.testimonials.destroy', $testimonial))
        ->assertRedirect(route('dashboard.inbox'));

    expect(testimonialRowExists($testimonial->getKey()))->toBeFalse();

    Log::shouldHaveReceived('error');
});

/*
|--------------------------------------------------------------------------
| Guests
|--------------------------------------------------------------------------
*/

test('a guest cannot delete a testimonial', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    $testimonial = Testimonial::factory()->for($space)->create([
        'name' => 'Ada Lovelace',
    ]);

    $this->delete(route('dashboard.testimonials.destroy', $testimonial))
        ->assertRedirect(route('login'));

    // The row is untouched and still live to the application.
    expect(testimonialRowExists($testimonial->getKey()))->toBeTrue()
        ->and(Testimonial::query()->whereKey($testimonial->getKey())->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Another account
|--------------------------------------------------------------------------
*/

test('another account cannot delete a testimonial', function () {
    $owner = User::factory()->create(['name' => 'Inbox Owner']);
    $other = User::factory()->create(['name' => 'Foreign Owner']);

    $space = Space::factory()->for($owner)->create(['name' => 'Owned Space']);

    $testimonial = Testimonial::factory()->for($space)->create([
        'name' => 'Ada Lovelace',
    ]);

    $this->actingAs($other)
        ->delete(route('dashboard.testimonials.destroy', $testimonial))
        ->assertForbidden();

    // The row is untouched and still live to the application.
    expect(testimonialRowExists($testimonial->getKey()))->toBeTrue()
        ->and(Testimonial::query()->whereKey($testimonial->getKey())->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| A deleted space
|--------------------------------------------------------------------------
*/

test('a testimonial of a soft deleted space cannot be deleted', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);
    $space = Space::factory()->for($user)->create(['name' => 'Owned Space']);

    $testimonial = Testimonial::factory()->for($space)->create([
        'name' => 'Ada Lovelace',
    ]);

    $space->delete();

    $this->actingAs($user)
        ->delete(route('dashboard.testimonials.destroy', $testimonial))
        ->assertForbidden();

    // The row is untouched and still live to the application.
    expect(testimonialRowExists($testimonial->getKey()))->toBeTrue()
        ->and(Testimonial::query()->whereKey($testimonial->getKey())->count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Missing testimonials
|--------------------------------------------------------------------------
*/

test('deleting a testimonial that does not exist is not found', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);

    $this->actingAs($user)
        ->delete(route('dashboard.testimonials.destroy', 999999))
        ->assertNotFound();
});

test('deleting a testimonial with a non numeric id is not found', function () {
    $user = User::factory()->create(['name' => 'Inbox Owner']);

    $this->actingAs($user)
        ->delete(route('dashboard.testimonials.destroy', 'not-a-number'))
        ->assertNotFound();
});
