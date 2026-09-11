<?php

use App\Support\BarCode;

test('calculates and validates a known EAN-13 code', function () {
    expect(BarCode::calculateChecksum('400638133393'))->toBe(1)
        ->and(BarCode::validate('4006381333931'))->toBeTrue()
        ->and(BarCode::validate('4006381333932'))->toBeFalse();
});

test('generates EAN-13 from a short base number with leading zero padding', function () {
    $barcode = BarCode::generateFromBaseNumber('123');

    expect($barcode)->toBe('0000000001236')
        ->and(BarCode::validate($barcode))->toBeTrue();
});

test('rejects a base number longer than twelve digits', function () {
    expect(fn () => BarCode::generateFromBaseNumber('1234567890123'))
        ->toThrow(InvalidArgumentException::class);
});

test('rejects a non-numeric base number', function () {
    expect(fn () => BarCode::generateFromBaseNumber('123-45'))
        ->toThrow(InvalidArgumentException::class);
});

test('normalizes a twelve digit barcode search value with a leading zero', function () {
    expect(BarCode::normalizeSearchInput('000000001236'))->toBe('0000000001236')
        ->and(BarCode::normalizeSearchInput('123456789012'))->toBe('0123456789012')
        ->and(BarCode::normalizeSearchInput(' 000000001236 '))->toBe('0000000001236')
        ->and(BarCode::normalizeSearchInput('0000000001236'))->toBe('0000000001236')
        ->and(BarCode::normalizeSearchInput('12345'))->toBe('12345');
});
