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

test('builds author keys insensitive to script punctuation diacritics and name order', function () {
    expect(Text::authorKey('Иво Андрић'))->toBe('andric ivo')
        ->and(Text::authorKey('Andrić, Ivo'))->toBe('andric ivo')
        ->and(Text::authorKey('Ivo Andric'))->toBe('andric ivo')
        ->and(Text::authorKey('Јовановић-Змај, Јован'))->toBe('jovan jovanovic zmaj');
});

test('builds label keys without reordering words', function () {
    expect(Text::labelKey('Научна фантастика'))->toBe('naucna fantastika')
        ->and(Text::labelKey('Naučna fantastika'))->toBe('naucna fantastika')
        ->and(Text::labelKey('fantastika naučna'))->toBe('fantastika naucna');
});
