<?php

use App\Services\Isbn\CategoryTranslator;

test('category translator leaves serbian cyrillic untouched', function () {
    config(['isbn.translate.enabled' => true]);

    expect(app(CategoryTranslator::class)->translate('кратка проза'))->toBe('кратка проза');
});

test('category translator returns raw value when translation is disabled', function () {
    config(['isbn.translate.enabled' => false]);

    expect(app(CategoryTranslator::class)->translate('Fiction'))->toBe('Fiction');
});

test('category translator falls back to raw value when google translate fails', function () {
    config([
        'isbn.translate.enabled' => true,
        'isbn.translate.url' => 'http://127.0.0.1:1/translate_a/single',
    ]);

    expect(app(CategoryTranslator::class)->translate('Fiction'))->toBe('Fiction');
});
