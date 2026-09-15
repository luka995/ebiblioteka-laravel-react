<?php

use App\Models\Category;
use App\Models\Library;
use App\Services\Catalog\CobissCategoryMapper;

function categoryIdByName(Library $library, string $name): int
{
    return (int) Category::query()
        ->where('library_id', $library->id)
        ->where('name', $name)
        ->value('id');
}

test('cobiss category mapper seeds the tree and maps genres', function () {
    $library = Library::factory()->create();
    $mapper = app(CobissCategoryMapper::class);

    expect($mapper->categoryId($library, 'роман', '821.163.41-31'))
        ->toBe(categoryIdByName($library, 'Књижевност'));

    expect($mapper->categoryId($library, 'поезија', '821.163.41-1'))
        ->toBe(categoryIdByName($library, 'Поезија'));

    expect($mapper->categoryId($library, 'драма', null))
        ->toBe(categoryIdByName($library, 'Драма'));

    expect($mapper->categoryId($library, 'бајке', null))
        ->toBe(categoryIdByName($library, 'Лектира'));

    expect($mapper->categoryId($library, 'енциклопедија', null))
        ->toBe(categoryIdByName($library, 'Енциклопедије и приручници'));
});

test('cobiss category mapper falls back to udk prefixes', function () {
    $library = Library::factory()->create();
    $mapper = app(CobissCategoryMapper::class);

    expect($mapper->categoryId($library, null, '821.163.41-31'))
        ->toBe(categoryIdByName($library, 'Домаћа књижевност'));

    expect($mapper->categoryId($library, null, '821.111-31'))
        ->toBe(categoryIdByName($library, 'Светска књижевност'));

    expect($mapper->categoryId($library, null, '53'))
        ->toBe(categoryIdByName($library, 'Природне науке'));

    expect($mapper->categoryId($library, null, '94(497.11)'))
        ->toBe(categoryIdByName($library, 'Историја'));

    expect($mapper->categoryId($library, null, '913(497.11)'))
        ->toBe(categoryIdByName($library, 'Географија'));

    expect($mapper->categoryId($library, null, null))->toBeNull();
});
