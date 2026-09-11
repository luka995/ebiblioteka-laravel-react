<?php

use App\Enums\BarcodePrintFormat;
use Tests\TestCase;

uses(TestCase::class);

test('barcode print format exposes the supported values', function () {
    expect(BarcodePrintFormat::values())->toBe(['label', 'a4'])
        ->and(BarcodePrintFormat::from('label'))->toBe(BarcodePrintFormat::Label)
        ->and(BarcodePrintFormat::from('a4'))->toBe(BarcodePrintFormat::A4);
});

test('barcode print format exposes human readable labels', function () {
    expect(BarcodePrintFormat::Label->getLabel())->toBeString()->not->toBeEmpty()
        ->and(BarcodePrintFormat::A4->getLabel())->toBeString()->not->toBeEmpty();
});
