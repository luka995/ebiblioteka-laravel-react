<?php

use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Library;
use App\Models\User;

function orderingHeaders(): array
{
    return [
        'Origin' => 'http://localhost:3001',
        'Accept' => 'application/json',
    ];
}

test('book copies list is ordered numerically by inventory number', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = Library::factory()->create();
    $this->withSession(['active_library_id' => $library->id]);
    $book = Book::factory()->for($library)->create();

    foreach ([1, 2, 3, 9, 10, 11, 12] as $number) {
        BookCopy::factory()->for($library)->for($book)->create(['order_number' => (string) $number]);
    }

    $response = $this->actingAs($admin)
        ->getJson('/api/v1/book-copies?book_id='.$book->id, orderingHeaders())
        ->assertOk();

    expect(collect($response->json('data'))->pluck('order_number')->all())
        ->toBe(['1', '2', '3', '9', '10', '11', '12']);
});

test('isbn lookup returns existing copies ordered numerically', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = Library::factory()->create();
    $this->withSession(['active_library_id' => $library->id]);
    $book = Book::factory()->for($library)->create();

    foreach ([1, 2, 3, 10, 11, 12] as $number) {
        BookCopy::factory()->for($library)->for($book)->create([
            'order_number' => (string) $number,
            'isbn' => '9780306406157',
        ]);
    }

    $response = $this->actingAs($admin)
        ->getJson('/api/v1/books/isbn-lookup?isbn=9780306406157&library_id='.$library->id, orderingHeaders())
        ->assertOk()
        ->assertJsonPath('source', 'database');

    expect(collect($response->json('existing_copies'))->pluck('order_number')->all())
        ->toBe(['1', '2', '3', '10', '11', '12']);
});
