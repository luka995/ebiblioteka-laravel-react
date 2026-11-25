<?php

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Library;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

test('database restricts deleting an author linked to a book', function () {
    $library = Library::factory()->create();
    $author = Author::factory()->for($library)->create();
    $book = Book::factory()->for($library)->create();
    $book->authors()->attach($author->id);

    expect(fn () => DB::table('authors')->where('id', $author->id)->delete())
        ->toThrow(QueryException::class);
});

test('database restricts deleting a primary category used by a book', function () {
    $library = Library::factory()->create();
    $category = Category::factory()->for($library)->create();
    Book::factory()->for($library)->create(['category_primary_id' => $category->id]);

    expect(fn () => DB::table('categories')->where('id', $category->id)->delete())
        ->toThrow(QueryException::class);
});

test('database restricts deleting a secondary category used by a book', function () {
    $library = Library::factory()->create();
    $category = Category::factory()->for($library)->create();
    Book::factory()->for($library)->create(['category_secondary_id' => $category->id]);

    expect(fn () => DB::table('categories')->where('id', $category->id)->delete())
        ->toThrow(QueryException::class);
});

test('deleting a book still cascades its author links', function () {
    $library = Library::factory()->create();
    $author = Author::factory()->for($library)->create();
    $book = Book::factory()->for($library)->create();
    $book->authors()->attach($author->id);

    DB::table('books')->where('id', $book->id)->delete();

    expect(DB::table('book_authors')->where('book_id', $book->id)->exists())->toBeFalse();
});
