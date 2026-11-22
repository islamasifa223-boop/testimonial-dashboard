<?php

namespace App\Services;

use App\Models\Space;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Throwable;

/**
 * Issues and verifies the one-time tokens that protect public submissions.
 *
 * A token is an encrypted payload holding the issue time, a random nonce
 * and the id of the space it was issued for. The payload is protected by
 * the application key, so it cannot be forged. The nonce is claimed in the
 * cache, so a token can be consumed only once, and the space id is
 * checked on use, so a token cannot be replayed against a different space.
 *
 * Every failure returns false. No internal exception is ever allowed to
 * escape the guard, so a caller can turn any outcome into one generic
 * error without leaking the reason.
 */
class TestimonialSubmissionGuard
{
    /**
     * The shortest time allowed between issuing a token and using it.
     */
    public const MINIMUM_SECONDS = 3;

    /**
     * The longest time a token stays usable.
     */
    public const MAXIMUM_AGE_SECONDS = 86400;

    /**
     * The prefix used for the consumed-nonce cache keys.
     */
    public const CACHE_PREFIX = 'testimonial-submission-nonce:';

    /**
     * Issue a token for the given space.
     */
    public function issueToken(Space $space): string
    {
        $payload = [
            'iat' => now()->getTimestamp(),
            'nonce' => Str::random(40),
            'sid' => $space->id,
        ];

        return Crypt::encryptString(json_encode($payload) ?: '');
    }

    /**
     * Determine whether the token may be used to submit to the given space.
     *
     * The token is consumed as part of the check, so a second attempt
     * with the same token always fails.
     */
    public function consumeToken(?string $token, Space $space): bool
    {
        $payload = $this->decode($token);

        if ($payload === null) {
            return false;
        }

        if (! $this->isWithinTimeWindow($payload['iat'])) {
            return false;
        }

        // The token was issued for a different space.
        if ((int) $payload['sid'] !== (int) $space->id) {
            return false;
        }

        // The add claims the nonce. A false result means the token has
        // already been consumed and this attempt is a replay.
        return Cache::add(
            self::CACHE_PREFIX.$payload['nonce'],
            true,
            self::MAXIMUM_AGE_SECONDS
        );
    }

    /**
     * Decode the token payload, or null when the token cannot be trusted.
     *
     * A malformed payload is never trusted and never throws: the caller
     * only learns that the token was unusable.
     *
     * @return array{iat: int, nonce: string, sid: int}|null
     */
    protected function decode(?string $token): ?array
    {
        if (! is_string($token) || $token === '') {
            return null;
        }

        try {
            $decrypted = Crypt::decryptString($token);
        } catch (DecryptException) {
            return null;
        } catch (Throwable) {
            // A malformed payload must never surface an internal error.
            return null;
        }

        $payload = json_decode($decrypted, true);

        if (! is_array($payload)) {
            return null;
        }

        if (! isset($payload['iat'], $payload['nonce'], $payload['sid'])) {
            return null;
        }

        if (! is_int($payload['iat']) || ! is_string($payload['nonce']) || ! is_int($payload['sid'])) {
            return null;
        }

        return $payload;
    }

    /**
     * Whether the issue time sits inside the allowed window.
     */
    protected function isWithinTimeWindow(int $issuedAt): bool
    {
        $age = now()->getTimestamp() - $issuedAt;

        if ($age < self::MINIMUM_SECONDS) {
            return false;
        }

        if ($age > self::MAXIMUM_AGE_SECONDS) {
            return false;
        }

        return true;
    }
}
