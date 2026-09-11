<?php

use App\Support\BarCodeImage;

test('renders a valid EAN-13 barcode as an SVG with 95 modules', function () {
    $svg = BarCodeImage::render('4006381333931');

    expect($svg)
        ->toContain('<svg ')
        ->toContain('width="190"')
        ->toContain('height="60"')
        ->toContain('viewBox="0 0 190 60"')
        ->toContain('<desc>EAN-13 barcode 4006381333931</desc>');

    preg_match_all('/<rect /', $svg, $matches);

    expect($matches[0])->not->toBeEmpty();
    expect(substr_count($svg, 'width="2"'))->toBeGreaterThan(0);
});

test('rejects an invalid EAN-13 barcode for image rendering', function () {
    expect(fn () => BarCodeImage::render('4006381333932'))
        ->toThrow(InvalidArgumentException::class);
});

test('rejects non-positive barcode dimensions', function () {
    expect(fn () => BarCodeImage::render('4006381333931', 0))
        ->toThrow(InvalidArgumentException::class);
});
