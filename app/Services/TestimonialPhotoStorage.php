<?php

namespace App\Services;

use App\Models\Space;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Stores submitted photos on the private disk.
 *
 * The file name is always generated, never taken from the upload, so a
 * visitor can never choose where the file lands or what it is called.
 */
class TestimonialPhotoStorage
{
    /**
     * The disk the photos are written to.
     */
    public const DISK = 'local';

    /**
     * The accepted mime types and the extension used for each of them.
     *
     * @var array<string, string>
     */
    public const ALLOWED_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * Store the photo and return its path relative to the disk.
     *
     * Returns null when the disk refuses the write without throwing, so
     * a failed upload can never leave a path behind that points at
     * nothing.
     */
    public function store(Space $space, UploadedFile $photo): ?string
    {
        $extension = $this->extensionFor($photo);

        if ($extension === null) {
            return null;
        }

        $directory = $this->directoryFor($space);
        $name = Str::uuid()->toString().'.'.$extension;

        $stored = Storage::disk(self::DISK)->putFileAs($directory, $photo, $name);

        // putFileAs() returns false when the write fails. The exception is
        // handled by the caller, so both failure modes are covered here.
        if ($stored === false) {
            return null;
        }

        return $directory.'/'.$name;
    }

    /**
     * Remove a stored photo.
     */
    public function delete(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        Storage::disk(self::DISK)->delete($path);
    }

    /**
     * The directory the photos of a space are stored in.
     *
     * Every photo of a space lives under this directory and nowhere
     * else. The directory is derived from the space id, so a photo
     * path can be checked against the space it is supposed to belong
     * to before the file is served.
     */
    public function directoryFor(Space $space): string
    {
        return 'testimonials/'.$space->id;
    }

    /**
     * Whether the path is a photo of the given space.
     *
     * The path has to have the exact shape store() writes: three
     * segments, the space directory, and a server generated file name.
     * Every part of the shape is checked, because a prefix match on the
     * directory alone would accept a path such as
     * testimonials/1/../../other/portrait.png, which starts with the
     * directory but escapes it when the file is read.
     *
     * A path is only ever as trustworthy as the row it was read from,
     * so this treats the stored path as untrusted input and validates
     * it completely before the file is served.
     */
    public function belongsToSpace(?string $path, Space $space): bool
    {
        if ($path === null || $path === '') {
            return false;
        }

        $segments = explode('/', $path);

        if (count($segments) !== 3) {
            return false;
        }

        [$root, $spaceSegment, $filename] = $segments;

        // The directory the photo has to live in is the one store()
        // writes to, so the two can never disagree.
        [$expectedRoot, $expectedSpace] = explode('/', $this->directoryFor($space));

        if ($root !== $expectedRoot) {
            return false;
        }

        if ($spaceSegment !== $expectedSpace) {
            return false;
        }

        // A backslash is a path separator on Windows, so a name that
        // carries one could escape the directory on a server that
        // resolves it that way.
        if (str_contains($filename, '\\')) {
            return false;
        }

        if (in_array($filename, ['.', '..'], true)) {
            return false;
        }

        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $name = pathinfo($filename, PATHINFO_FILENAME);

        if (! in_array($extension, ['jpg', 'png', 'webp'], true)) {
            return false;
        }

        return Str::isUuid($name);
    }

    /**
     * The disk the photos are stored on.
     */
    public function disk(): FilesystemAdapter
    {
        return Storage::disk(self::DISK);
    }

    /**
     * Whether a photo is stored at the given path.
     */
    public function exists(?string $path): bool
    {
        if ($path === null || $path === '') {
            return false;
        }

        return $this->disk()->exists($path);
    }

    /**
     * A response that shows the photo at the given path.
     *
     * The response is not cached. A photo that is later taken off the
     * wall, hidden, or has its consent withdrawn has to stop being
     * available at once, and a cached copy would keep serving it.
     */
    public function response(string $path): StreamedResponse
    {
        return $this->disk()->response($path, headers: [
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * The extension used for the uploaded file's validated mime type.
     */
    protected function extensionFor(UploadedFile $photo): ?string
    {
        return self::ALLOWED_EXTENSIONS[$photo->getMimeType()] ?? null;
    }
}
