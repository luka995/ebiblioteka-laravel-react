<?php

use App\Enums\BarcodePrintFormat;
use App\Services\BarcodePdfService;
use App\Support\BarcodeLabel;
use Tests\TestCase;

uses(TestCase::class);

function barcodeLabels(int $count, string $code = '4006381333931'): array
{
    return array_fill(0, $count, new BarcodeLabel($code));
}

test('label format renders one page per barcode', function () {
    $pdf = app(BarcodePdfService::class)->render(barcodeLabels(3), BarcodePrintFormat::Label);

    expect($pdf)->toStartWith('%PDF')
        ->and(preg_match_all('/MediaBox/', $pdf))->toBe(3);
});

test('a4 format fits 48 barcodes on a single page', function () {
    $pdf = app(BarcodePdfService::class)->render(barcodeLabels(48), BarcodePrintFormat::A4);

    expect(preg_match_all('/MediaBox/', $pdf))->toBe(1);
});

test('a4 format paginates after 48 barcodes', function () {
    $pdf = app(BarcodePdfService::class)->render(barcodeLabels(49), BarcodePrintFormat::A4);

    expect(preg_match_all('/MediaBox/', $pdf))->toBe(2);
});

test('render rejects an invalid barcode', function () {
    app(BarcodePdfService::class)->render([new BarcodeLabel('123')], BarcodePrintFormat::Label);
})->throws(InvalidArgumentException::class);

test('render rejects an empty label list', function () {
    app(BarcodePdfService::class)->render([], BarcodePrintFormat::Label);
})->throws(InvalidArgumentException::class);
