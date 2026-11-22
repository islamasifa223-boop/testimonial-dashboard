<?php

namespace App\Services;

use App\Exceptions\SlugGenerationFailed;
use App\Models\Space;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Generates the public slug for a space.
 *
 * Slugs are unique across every account and are never reused: a
 * soft-deleted space keeps its slug reserved. Generation happens on the
 * server, and a collision is resolved by trying the next candidate.
 */
class SpaceSlugGenerator
{
    /**
     * How many candidates are tried before giving up.
     */
    public const MAX_ATTEMPTS = 10;

    /**
     * The base slug derived from a space name.
     */
    public function base(string $name): string
    {
        $slug = Str::slug($name);

        return $slug === '' ? 'space' : $slug;
    }

    /**
     * The candidates tried, in order, for a given name.
     *
     * @return array<int, string>
     */
    public function candidates(string $name): array
    {
        $base = $this->base($name);

        $candidates = [$base];

        for ($suffix = 2; $suffix <= self::MAX_ATTEMPTS; $suffix++) {
            $candidates[] = $base.'-'.$suffix;
        }

        return $candidates;
    }

    /**
     * Create a space with a unique slug.
     *
     * Each attempt runs inside its own nested transaction, which becomes a
     * savepoint, so a failed attempt rolls back only that attempt and the
     * outer transaction continues with the next candidate.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createFor(Space $space, array $attributes): Space
    {
        $name = (string) ($attributes['name'] ?? $space->name);

        foreach ($this->candidates($name) as $candidate) {
            try {
                return DB::transaction(function () use ($space, $attributes, $candidate): Space {
                    $space->slug = $candidate;
                    $space->fill($attributes);
                    $space->save();

                    return $space;
                });
            } catch (UniqueConstraintViolationException $exception) {
                if (! $this->isSlugViolation($exception)) {
                    // A different unique constraint failed - let it surface.
                    throw $exception;
                }
            }
        }

        throw new SlugGenerationFailed($name, self::MAX_ATTEMPTS);
    }

    /**
     * Determine whether the violation was caused by the slug index.
     */
    public function isSlugViolation(UniqueConstraintViolationException $exception): bool
    {
        return str_contains($exception->getMessage(), 'spaces.slug')
            || str_contains($exception->getMessage(), 'spaces_slug_unique');
    }
}
