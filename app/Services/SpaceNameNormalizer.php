<?php

namespace App\Services;

use Normalizer;

/**
 * Produces the case-insensitive key used to enforce unique space names.
 *
 * The key is the NFC-normalised, case-folded form of the name. It is
 * stored in the spaces table and guarded by a composite unique index
 * on (user_id, name_key).
 */
class SpaceNameNormalizer
{
    /**
     * Convert any byte sequence into valid UTF-8.
     *
     * Malformed byte sequences are replaced using mbstring's configured substitution character. Different invalid inputs may therefore produce the same sanitized value.
     */
    public static function toValidUtf8(string $value): string
    {
        $converted = mb_convert_encoding($value, 'UTF-8', 'UTF-8');

        return is_string($converted) ? $converted : '';
    }

    /**
     * Normalise a name to NFC.
     *
     * Normalizer::normalize() returns false when it cannot handle the
     * input. That result is handled explicitly here and is never passed
     * on to another function.
     */
    public static function normalize(string $name): string
    {
        $trimmed = trim($name);

        $normalized = Normalizer::normalize(self::toValidUtf8($trimmed), Normalizer::FORM_C);

        if ($normalized === false) {
            // The normaliser could not process the input. Fall back to the
            // sanitised value so a deterministic key is still produced.
            return self::toValidUtf8($trimmed);
        }

        return $normalized;
    }

    /**
     * The case-insensitive key used for name uniqueness.
     *
     * MB_CASE_FOLD is used rather than a plain lowercase because it folds
     * characters such as ß to ss, matching how the names are compared.
     */
    public static function key(string $name): string
    {
        $normalized = self::normalize($name);

        return mb_convert_case($normalized, MB_CASE_FOLD, 'UTF-8');
    }
}
