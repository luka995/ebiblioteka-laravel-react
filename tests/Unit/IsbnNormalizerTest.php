<?php

use App\Services\Isbn\IsbnNormalizer;

function isbnNormalizer(): IsbnNormalizer
{
    return new IsbnNormalizer;
}

test('normalizes isbn by stripping separators', function () {
    expect(isbnNormalizer()->normalize('978-0-306-40615-7'))->toBe('9780306406157')
        ->and(isbnNormalizer()->normalize(' 978 0 306 40615 7 '))->toBe('9780306406157');
});

test('validates isbn10 and isbn13 checksums', function () {
    expect(isbnNormalizer()->isValid('9780306406157'))->toBeTrue()
        ->and(isbnNormalizer()->isValid('9780306406158'))->toBeFalse()
        ->and(isbnNormalizer()->isValid('0306406152'))->toBeTrue()
        ->and(isbnNormalizer()->isValid('0306406153'))->toBeFalse()
        ->and(isbnNormalizer()->isValid('123'))->toBeFalse();
});

test('returns both isbn forms for matching', function () {
    expect(isbnNormalizer()->forms('9780306406157'))->toContain('9780306406157')
        ->and(isbnNormalizer()->forms('9780306406157'))->toContain('0306406152');
});
