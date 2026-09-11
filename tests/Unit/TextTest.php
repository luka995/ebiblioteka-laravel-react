<?php

use App\Support\Text;

test('transliterates serbian latin to cyrillic preserving digraphs and diacritics', function () {
    expect(Text::cyr('Filipović'))->toBe('Филиповић')
        ->and(Text::cyr('Džak'))->toBe('Џак')
        ->and(Text::cyr('Njegoš'))->toBe('Његош');
});

test('transliterates serbian cyrillic to latin preserving digraphs and diacritics', function () {
    expect(Text::lat('Филиповић'))->toBe('Filipović')
        ->and(Text::lat('Џак'))->toBe('Džak')
        ->and(Text::lat('Његош'))->toBe('Njegoš')
        ->and(Text::lat('Љубав'))->toBe('Ljubav');
});

test('leaves text in the source script unchanged', function () {
    expect(Text::lat('Beograd'))->toBe('Beograd')
        ->and(Text::cyr('Београд'))->toBe('Београд');
});

test('builds unique search variants covering both scripts', function () {
    expect(Text::searchVariants('Филиповић'))->toBe(['Филиповић', 'Filipović'])
        ->and(Text::searchVariants('Beograd'))->toBe(['Beograd', 'Београд'])
        ->and(Text::searchVariants(''))->toBe([]);
});
