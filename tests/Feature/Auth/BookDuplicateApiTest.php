<?php

use App\Models\Author;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Library;
use App\Models\User;

function duplicateHeaders(array $extra = []): array
{
    return array_merge([
        'Origin' => 'http://localhost:3001',
        'Accept' => 'application/json',
    ], $extra);
}

function makeDuplicateLibrary(): Library
{
    return Library::factory()->create();
}

test('duplicate check finds a title with the same title and author', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = makeDuplicateLibrary();
    $book = Book::factory()->for($library)->create(['name' => 'Na Drini ćuprija']);
    $author = Author::factory()->for($library)->create(['name' => 'Ivo Andrić']);
    $book->authors()->attach($author->id);

    $url = '/api/v1/books/duplicate-check?'.http_build_query([
        'name' => 'Na Drini ćuprija',
        'authors' => ['Ivo Andrić'],
        'library_id' => $library->id,
    ]);

    $this->actingAs($admin)->getJson($url, duplicateHeaders())
        ->assertOk()
        ->assertJsonPath('matches.0.id', $book->id);
});

test('duplicate check falls back to title when no author is given', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = makeDuplicateLibrary();
    $book = Book::factory()->for($library)->create(['name' => 'Samo naslov']);

    $url = '/api/v1/books/duplicate-check?'.http_build_query([
        'name' => 'Samo naslov',
        'library_id' => $library->id,
    ]);

    $this->actingAs($admin)->getJson($url, duplicateHeaders())
        ->assertOk()
        ->assertJsonPath('matches.0.id', $book->id);
});

test('duplicate check ignores titles from another library', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = makeDuplicateLibrary();
    $other = makeDuplicateLibrary();
    Book::factory()->for($other)->create(['name' => 'Tuđi naslov']);

    $url = '/api/v1/books/duplicate-check?'.http_build_query([
        'name' => 'Tuđi naslov',
        'library_id' => $library->id,
    ]);

    $this->actingAs($admin)->getJson($url, duplicateHeaders())
        ->assertOk()
        ->assertJsonCount(0, 'matches');
});

test('creating a duplicate title returns 409 with candidates', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = makeDuplicateLibrary();
    $book = Book::factory()->for($library)->create(['name' => 'Ista knjiga']);
    $author = Author::factory()->for($library)->create(['name' => 'Isti Autor']);
    $book->authors()->attach($author->id);

    $this->actingAs($admin)->postJson('/api/v1/books', [
        'name' => 'Ista knjiga',
        'library_id' => $library->id,
        'authors' => ['Isti Autor'],
    ], duplicateHeaders())
        ->assertStatus(409)
        ->assertJsonPath('duplicate_books.0.id', $book->id);

    expect(Book::where('name', 'Ista knjiga')->count())->toBe(1);
});

test('confirm_duplicate allows creating a new title', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = makeDuplicateLibrary();
    $book = Book::factory()->for($library)->create(['name' => 'Ista knjiga']);
    $author = Author::factory()->for($library)->create(['name' => 'Isti Autor']);
    $book->authors()->attach($author->id);

    $this->actingAs($admin)->postJson('/api/v1/books', [
        'name' => 'Ista knjiga',
        'library_id' => $library->id,
        'authors' => ['Isti Autor'],
        'confirm_duplicate' => true,
    ], duplicateHeaders())
        ->assertCreated()
        ->assertJsonPath('data.name', 'Ista knjiga');

    expect(Book::where('name', 'Ista knjiga')->count())->toBe(2);
});

test('confirm_duplicate creates a new title with a copy atomically', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = makeDuplicateLibrary();
    $book = Book::factory()->for($library)->create(['name' => 'Ista knjiga']);
    $author = Author::factory()->for($library)->create(['name' => 'Isti Autor']);
    $book->authors()->attach($author->id);

    $this->actingAs($admin)->postJson('/api/v1/books', [
        'name' => 'Ista knjiga',
        'library_id' => $library->id,
        'authors' => ['Isti Autor'],
        'with_copies' => true,
        'copies' => 2,
        'isbn' => '978-86-81131-21-3',
        'confirm_duplicate' => true,
    ], duplicateHeaders())
        ->assertCreated()
        ->assertJsonPath('data.copies_count', 2);

    expect(Book::where('name', 'Ista knjiga')->count())->toBe(2)
        ->and(BookCopy::where('isbn', '978-86-81131-21-3')->count())->toBe(2);
});
