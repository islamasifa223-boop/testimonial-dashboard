<?php

use App\Services\SpaceNameNormalizer;

/*
|--------------------------------------------------------------------------
| Space Name Normalizer
|--------------------------------------------------------------------------
|
| The normaliser produces the case-insensitive key used to enforce unique
| space names. It is pure string logic with no database and no HTTP, so
| it is tested here rather than through a feature test.
|
*/

/*
|--------------------------------------------------------------------------
| toValidUtf8
|--------------------------------------------------------------------------
*/

test('it converts a byte sequence into valid utf-8', function () {
    expect(mb_check_encoding(SpaceNameNormalizer::toValidUtf8('Acme Reviews'), 'UTF-8'))->toBeTrue();
});

test('it replaces invalid byte sequences rather than passing them on', function () {
    $sanitized = SpaceNameNormalizer::toValidUtf8("Bad\xFFName");

    expect(mb_check_encoding($sanitized, 'UTF-8'))->toBeTrue()
        ->and($sanitized)->not->toBe("Bad\xFFName");
});

test('it leaves a valid string unchanged', function () {
    expect(SpaceNameNormalizer::toValidUtf8('Acme Reviews'))->toBe('Acme Reviews');
});

/*
|--------------------------------------------------------------------------
| normalize
|--------------------------------------------------------------------------
*/

test('it trims surrounding whitespace before normalising', function () {
    expect(SpaceNameNormalizer::normalize('  Acme Reviews  '))->toBe('Acme Reviews');
});

test('it normalises a name to NFC', function () {
    $decomposed = "Cafe\u{0301}";

    expect(SpaceNameNormalizer::normalize($decomposed))->toBe(SpaceNameNormalizer::normalize('Café'))
        ->and(SpaceNameNormalizer::normalize($decomposed))->toBe('Café');
});

test('it preserves the case of the name', function () {
    expect(SpaceNameNormalizer::normalize('ACME Reviews'))->toBe('ACME Reviews');
});

test('it produces an empty string for an empty name', function () {
    expect(SpaceNameNormalizer::normalize(''))->toBe('');
});

test('it returns valid utf-8 for a name containing invalid bytes', function () {
    expect(mb_check_encoding(SpaceNameNormalizer::normalize("Bad\xFFName"), 'UTF-8'))->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| key
|--------------------------------------------------------------------------
*/

test('it produces a lowercased key from the name', function () {
    expect(SpaceNameNormalizer::key('Acme Reviews'))->toBe('acme reviews');
});

test('the key ignores the case of the name', function () {
    expect(SpaceNameNormalizer::key('ACME'))->toBe(SpaceNameNormalizer::key('acme'))
        ->and(SpaceNameNormalizer::key('AcMe'))->toBe(SpaceNameNormalizer::key('acme'));
});

test('the key ignores surrounding whitespace in the name', function () {
    expect(SpaceNameNormalizer::key('  Acme Reviews  '))->toBe('acme reviews');
});

test('the key folds a sharp s to ss', function () {
    expect(SpaceNameNormalizer::key('Straße'))->toBe('strasse')
        ->and(SpaceNameNormalizer::key('STRASSE'))->toBe('strasse');
});

test('the key is equal for names that differ only in unicode composition', function () {
    expect(SpaceNameNormalizer::key("Cafe\u{0301}"))->toBe(SpaceNameNormalizer::key('Café'));
});

test('the key keeps characters that are not ascii', function () {
    expect(SpaceNameNormalizer::key('Ünïcödé'))->toBe('ünïcödé');
});

test('the key is empty for an empty name', function () {
    expect(SpaceNameNormalizer::key(''))->toBe('');
});
