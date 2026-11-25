<?php

use App\DTOs\Queries\AuthorListQueryDTO;
use App\DTOs\Queries\BookListQueryDTO;
use App\DTOs\Queries\SortingDTO;
use App\Models\Author;
use App\Models\Book;
use App\Models\Library;
use App\Models\User;
use App\Services\Queries\AuthorQueryService;
use App\Services\Queries\BookQueryService;

test('query services can be used directly with DTOs without an HTTP request', function () {
    $library = Library::factory()->create();
    $actor = User::factory()->superAdmin()->create();

    Author::factory()->for($library)->create(['name' => 'Андрић, Иво']);
    Author::factory()->for($library)->create(['name' => 'Црњански, Милош']);

    $authorQuery = app(AuthorQueryService::class)->build(
        new AuthorListQueryDTO(
            new SortingDTO('name', 'desc'),
            libraryId: $library->id,
        ),
        $actor,
        $library,
    );

    expect($authorQuery->get()->first()?->name)->toBe('Црњански, Милош');

    Book::factory()->for($library)->create(['name' => 'Ана Карењина']);
    Book::factory()->for($library)->create(['name' => 'Злочин и казна']);

    $bookQuery = app(BookQueryService::class)->build(
        new BookListQueryDTO(new SortingDTO('name', 'desc')),
        $library,
    );

    expect($bookQuery->get()->first()?->name)->toBe('Злочин и казна');
});
