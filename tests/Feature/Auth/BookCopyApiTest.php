<?php

use App\Enums\UserRole;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookCopyWriteOff;
use App\Models\Library;
use App\Models\User;
use App\Services\BookCopyAvailabilityService;
use App\Services\InventoryNumberService;
use App\Support\BarCode;

function bookCopyHeaders(array $extra = []): array
{
    return array_merge([
        'Origin' => 'http://localhost:3001',
        'Accept' => 'application/json',
    ], $extra);
}

function bookCopyLibrary(bool $auto = true): Library
{
    return Library::factory()->create(['inv_number_auto' => $auto]);
}

function bookCopyBook(Library $library): Book
{
    return Book::factory()->for($library)->create();
}

function bookCopyStaff(Library $library, UserRole $role = UserRole::Librarian): User
{
    $user = User::factory()->role($role)->create();
    $user->libraries()->attach($library->id);

    return $user;
}

test('auto mode creates the requested number of copies with sequential numbers and valid barcodes', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = bookCopyLibrary();
    $this->withSession(['active_library_id' => $library->id]);
    $book = bookCopyBook($library);

    $response = $this->actingAs($admin)->postJson("/api/v1/books/{$book->id}/copies", [
        'copies' => 3,
        'isbn' => '9788663580100',
        'publisher' => 'Prosveta',
    ], bookCopyHeaders());

    $response->assertCreated()->assertJsonCount(3, 'data');

    $numbers = $response->json('data.*.order_number');
    expect($numbers)->toBe(['1', '2', '3']);

    foreach ($response->json('data') as $copy) {
        expect(BarCode::validate($copy['barcode']))->toBeTrue()
            ->and($copy['isbn'])->toBe('9788663580100');
    }

    expect($library->inventorySequence()->first()->last_number)->toBe(3);
});

test('auto mode requires the number of copies', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = bookCopyLibrary();
    $this->withSession(['active_library_id' => $library->id]);
    $book = bookCopyBook($library);

    $this->actingAs($admin)->postJson("/api/v1/books/{$book->id}/copies", [], bookCopyHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('copies');
});

test('auto mode blocks creation when archived numbers make the sequence inconsistent', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = bookCopyLibrary();
    $this->withSession(['active_library_id' => $library->id]);
    $book = bookCopyBook($library);

    $archived = BookCopy::factory()->for($library)->for($book)->create(['order_number' => '9']);
    $archived->delete();

    $this->actingAs($admin)->postJson("/api/v1/books/{$book->id}/copies", ['copies' => 1], bookCopyHeaders())
        ->assertStatus(409)
        ->assertJsonPath('inventory_discrepancy.next_auto', 1)
        ->assertJsonPath('inventory_discrepancy.max_used', 9)
        ->assertJsonCount(1, 'inventory_discrepancy.archived_copies');

    expect(BookCopy::where('book_id', $book->id)->count())->toBe(0);
});

test('manual mode creates a single copy with a manual inventory number and derived barcode', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = bookCopyLibrary(auto: false);
    $book = bookCopyBook($library);

    $response = $this->actingAs($admin)->postJson("/api/v1/books/{$book->id}/copies", [
        'copies' => 5,
        'order_number' => '1',
    ], bookCopyHeaders());

    $response->assertCreated()->assertJsonCount(1, 'data');
    expect(BarCode::validate($response->json('data.0.barcode')))->toBeTrue();
});

test('manual mode rejects duplicates and skips', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = bookCopyLibrary(auto: false);
    $book = bookCopyBook($library);

    $this->actingAs($admin)->postJson("/api/v1/books/{$book->id}/copies", ['order_number' => '1'], bookCopyHeaders())->assertCreated();

    $this->actingAs($admin)->postJson("/api/v1/books/{$book->id}/copies", ['order_number' => '1'], bookCopyHeaders())
        ->assertUnprocessable();

    $this->actingAs($admin)->postJson("/api/v1/books/{$book->id}/copies", ['order_number' => '9'], bookCopyHeaders())
        ->assertUnprocessable();

    $this->actingAs($admin)->postJson("/api/v1/books/{$book->id}/copies", ['order_number' => '2'], bookCopyHeaders())
        ->assertCreated();
});

test('a copy can be updated and manual inventory number changes re-derive the barcode', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = bookCopyLibrary(auto: false);
    $book = bookCopyBook($library);

    $copy = BookCopy::factory()->for($library)->for($book)->create([
        'order_number' => '1',
        'barcode' => '0000000000000',
    ]);

    $this->actingAs($admin)->putJson("/api/v1/book-copies/{$copy->id}", [
        'publisher' => 'Nova izdanja',
        'order_number' => '2',
    ], bookCopyHeaders())
        ->assertOk()
        ->assertJsonPath('data.publisher', 'Nova izdanja')
        ->assertJsonPath('data.order_number', '2');

    expect(BarCode::validate($copy->fresh()->barcode))->toBeTrue();
});

test('borrowed copies cannot be deleted', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = bookCopyLibrary();
    $this->withSession(['active_library_id' => $library->id]);
    $book = bookCopyBook($library);
    $copy = BookCopy::factory()->for($library)->for($book)->create(['order_number' => '1', 'borrowed' => true]);

    $this->actingAs($admin)->deleteJson("/api/v1/book-copies/{$copy->id}", [], bookCopyHeaders())
        ->assertUnprocessable();
});

test('copy archive supports restore and permanent delete which frees the number', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = bookCopyLibrary();
    $this->withSession(['active_library_id' => $library->id]);
    $book = bookCopyBook($library);
    $copy = BookCopy::factory()->for($library)->for($book)->create(['order_number' => '7']);

    $copy->delete();

    $this->actingAs($admin)->getJson('/api/v1/book-copies/archive', bookCopyHeaders())
        ->assertOk()
        ->assertJsonFragment(['id' => $copy->id]);

    $this->actingAs($admin)->postJson("/api/v1/book-copies/{$copy->id}/restore", [], bookCopyHeaders())
        ->assertOk()
        ->assertJsonPath('data.deleted_at', null);

    $copy->delete();
    $this->actingAs($admin)->deleteJson("/api/v1/book-copies/{$copy->id}/force", [], bookCopyHeaders())
        ->assertNoContent();

    expect(BookCopy::withTrashed()->find($copy->id))->toBeNull();
    expect(app(InventoryNumberService::class)->orderNumberExists($library, '7'))->toBeFalse();
});

test('write-off and cancel change the copy status and preserve history', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = bookCopyLibrary();
    $this->withSession(['active_library_id' => $library->id]);
    $book = bookCopyBook($library);
    $copy = BookCopy::factory()->for($library)->for($book)->create(['order_number' => '1']);

    $this->actingAs($admin)->postJson("/api/v1/book-copies/{$copy->id}/write-off", [
        'reason' => 'unusable',
        'occurred_at' => '2026-03-01',
        'notice' => 'Oštećena korica.',
    ], bookCopyHeaders())
        ->assertOk()
        ->assertJsonPath('data.status', 'written_off')
        ->assertJsonPath('data.active_write_off.reason', 'unusable')
        ->assertJsonPath('data.active_write_off.occurred_at', '01.03.2026.');

    expect(BookCopyWriteOff::where('book_copy_id', $copy->id)->count())->toBe(1);

    $this->actingAs($admin)->postJson("/api/v1/book-copies/{$copy->id}/write-off/cancel", [], bookCopyHeaders())
        ->assertOk()
        ->assertJsonPath('data.status', 'available');

    expect(BookCopyWriteOff::where('book_copy_id', $copy->id)->whereNull('cancelled_at')->count())->toBe(0);
    expect(BookCopyWriteOff::where('book_copy_id', $copy->id)->count())->toBe(1);
});

test('rec_error requires a notice and affects availability', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = bookCopyLibrary();
    $this->withSession(['active_library_id' => $library->id]);
    $book = bookCopyBook($library);
    $copy = BookCopy::factory()->for($library)->for($book)->create(['order_number' => '1']);

    $this->actingAs($admin)->putJson("/api/v1/book-copies/{$copy->id}/rec-error", ['rec_error' => true], bookCopyHeaders())
        ->assertUnprocessable();

    $this->actingAs($admin)->putJson("/api/v1/book-copies/{$copy->id}/rec-error", [
        'rec_error' => true,
        'rec_error_notice' => 'Dupli unos.',
    ], bookCopyHeaders())
        ->assertOk()
        ->assertJsonPath('data.status', 'record_error');
});

test('librarian sees only copies from the active library', function () {
    $library = bookCopyLibrary();
    $this->withSession(['active_library_id' => $library->id]);
    $other = bookCopyLibrary();
    $librarian = bookCopyStaff($library);

    $own = BookCopy::factory()->for($library)->for(bookCopyBook($library))->create(['order_number' => '1']);
    $foreign = BookCopy::factory()->for($other)->for(bookCopyBook($other))->create(['order_number' => '1']);

    $this->actingAs($librarian)->getJson('/api/v1/book-copies', bookCopyHeaders())
        ->assertOk()
        ->assertJsonFragment(['id' => $own->id])
        ->assertJsonMissing(['id' => $foreign->id]);
});

test('index filters copies by inventory number', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = bookCopyLibrary();
    $this->withSession(['active_library_id' => $library->id]);
    $book = bookCopyBook($library);

    $match = BookCopy::factory()->for($library)->for($book)->create(['order_number' => '1234']);
    $other = BookCopy::factory()->for($library)->for($book)->create(['order_number' => '5678']);

    $this->actingAs($admin)->getJson('/api/v1/book-copies?order_number=123', bookCopyHeaders())
        ->assertOk()
        ->assertJsonFragment(['id' => $match->id])
        ->assertJsonMissing(['id' => $other->id]);
});

test('index filters copies by book title across scripts', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = bookCopyLibrary();
    $this->withSession(['active_library_id' => $library->id]);

    $matchBook = Book::factory()->for($library)->create(['name' => 'Стари завет']);
    $otherBook = Book::factory()->for($library)->create(['name' => 'Нови завет']);

    $match = BookCopy::factory()->for($library)->for($matchBook)->create(['order_number' => '1']);
    $other = BookCopy::factory()->for($library)->for($otherBook)->create(['order_number' => '2']);

    $this->actingAs($admin)->getJson('/api/v1/book-copies?search=Stari', bookCopyHeaders())
        ->assertOk()
        ->assertJsonFragment(['id' => $match->id])
        ->assertJsonMissing(['id' => $other->id]);
});

test('index still filters copies by barcode', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = bookCopyLibrary();
    $this->withSession(['active_library_id' => $library->id]);
    $book = bookCopyBook($library);

    $match = BookCopy::factory()->for($library)->for($book)->create(['barcode' => '1234567890128']);
    $other = BookCopy::factory()->for($library)->for($book)->create(['barcode' => '1234567890135']);

    $this->actingAs($admin)->getJson('/api/v1/book-copies?barcode=90128', bookCopyHeaders())
        ->assertOk()
        ->assertJsonFragment(['id' => $match->id])
        ->assertJsonMissing(['id' => $other->id]);
});

test('barcode search restores a dropped leading zero from a twelve digit scan', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = bookCopyLibrary();
    $this->withSession(['active_library_id' => $library->id]);
    $book = bookCopyBook($library);

    $match = BookCopy::factory()->for($library)->for($book)->create(['barcode' => '0123456789012']);
    $other = BookCopy::factory()->for($library)->for($book)->create(['barcode' => '0123456789020']);

    $this->actingAs($admin)->getJson('/api/v1/book-copies?barcode=123456789012', bookCopyHeaders())
        ->assertOk()
        ->assertJsonFragment(['id' => $match->id])
        ->assertJsonMissing(['id' => $other->id]);
});

test('index filters copies by isbn', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = bookCopyLibrary();
    $this->withSession(['active_library_id' => $library->id]);
    $book = bookCopyBook($library);

    $match = BookCopy::factory()->for($library)->for($book)->create(['isbn' => '9788663580100']);
    $other = BookCopy::factory()->for($library)->for($book)->create(['isbn' => '9788612345678']);

    $this->actingAs($admin)->getJson('/api/v1/book-copies?isbn=8663580100', bookCopyHeaders())
        ->assertOk()
        ->assertJsonFragment(['id' => $match->id])
        ->assertJsonMissing(['id' => $other->id]);
});

test('index filters copies by publisher and binding', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = bookCopyLibrary();
    $this->withSession(['active_library_id' => $library->id]);
    $book = bookCopyBook($library);

    $match = BookCopy::factory()->for($library)->for($book)->create([
        'publisher' => 'Prosveta',
        'binding' => 't',
    ]);
    $other = BookCopy::factory()->for($library)->for($book)->create([
        'publisher' => 'Laguna',
        'binding' => 'b',
    ]);

    $this->actingAs($admin)->getJson('/api/v1/book-copies?publisher=Prosveta&binding=t', bookCopyHeaders())
        ->assertOk()
        ->assertJsonFragment(['id' => $match->id])
        ->assertJsonMissing(['id' => $other->id]);
});

test('index filters copies by origin and price range', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = bookCopyLibrary();
    $this->withSession(['active_library_id' => $library->id]);
    $book = bookCopyBook($library);

    $match = BookCopy::factory()->for($library)->for($book)->create(['origin' => 'ku', 'price' => 1500]);
    $other = BookCopy::factory()->for($library)->for($book)->create(['origin' => 'po', 'price' => 5000]);

    $this->actingAs($admin)->getJson('/api/v1/book-copies?origin=ku&price_from=1000&price_to=2000', bookCopyHeaders())
        ->assertOk()
        ->assertJsonFragment(['id' => $match->id])
        ->assertJsonMissing(['id' => $other->id]);
});

test('index filters copies by number of pages and date added range', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = bookCopyLibrary();
    $this->withSession(['active_library_id' => $library->id]);
    $book = bookCopyBook($library);

    $match = BookCopy::factory()->for($library)->for($book)->create([
        'num_of_pages' => 250,
        'date_add' => '2024-05-10',
    ]);
    $other = BookCopy::factory()->for($library)->for($book)->create([
        'num_of_pages' => 900,
        'date_add' => '2025-01-01',
    ]);

    $this->actingAs($admin)->getJson(
        '/api/v1/book-copies?num_of_pages_from=100&num_of_pages_to=400&date_add_from=2024-01-01&date_add_to=2024-12-31',
        bookCopyHeaders()
    )
        ->assertOk()
        ->assertJsonFragment(['id' => $match->id])
        ->assertJsonMissing(['id' => $other->id]);
});

test('index filters copies by notice across scripts', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = bookCopyLibrary();
    $this->withSession(['active_library_id' => $library->id]);
    $book = bookCopyBook($library);

    $match = BookCopy::factory()->for($library)->for($book)->create(['notice' => 'Стари фонд']);
    $other = BookCopy::factory()->for($library)->for($book)->create(['notice' => 'Нови фонд']);

    $this->actingAs($admin)->getJson('/api/v1/book-copies?notice=Stari', bookCopyHeaders())
        ->assertOk()
        ->assertJsonFragment(['id' => $match->id])
        ->assertJsonMissing(['id' => $other->id]);
});

test('index filters copies by boolean state', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = bookCopyLibrary();
    $this->withSession(['active_library_id' => $library->id]);
    $book = bookCopyBook($library);

    $available = BookCopy::factory()->for($library)->for($book)->create();
    $borrowed = BookCopy::factory()->for($library)->for($book)->create(['borrowed' => true]);
    $reserved = BookCopy::factory()->for($library)->for($book)->create(['reserved' => true]);
    $recordError = BookCopy::factory()->for($library)->for($book)->create(['rec_error' => true]);

    $this->actingAs($admin)->getJson('/api/v1/book-copies?borrowed=1', bookCopyHeaders())
        ->assertOk()
        ->assertJsonFragment(['id' => $borrowed->id])
        ->assertJsonMissing(['id' => $available->id]);

    $this->actingAs($admin)->getJson('/api/v1/book-copies?reserved=1', bookCopyHeaders())
        ->assertOk()
        ->assertJsonFragment(['id' => $reserved->id])
        ->assertJsonMissing(['id' => $available->id]);

    $this->actingAs($admin)->getJson('/api/v1/book-copies?rec_error=1', bookCopyHeaders())
        ->assertOk()
        ->assertJsonFragment(['id' => $recordError->id])
        ->assertJsonMissing(['id' => $available->id]);
});

test('index filters copies by derived status', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = bookCopyLibrary();
    $this->withSession(['active_library_id' => $library->id]);
    $book = bookCopyBook($library);

    $available = BookCopy::factory()->for($library)->for($book)->create();
    $borrowed = BookCopy::factory()->for($library)->for($book)->create(['borrowed' => true]);
    $recordError = BookCopy::factory()->for($library)->for($book)->create(['rec_error' => true]);
    $writtenOff = BookCopy::factory()->for($library)->for($book)->create();
    BookCopyWriteOff::factory()->forCopy($writtenOff)->create();

    $this->actingAs($admin)->getJson('/api/v1/book-copies?status=available', bookCopyHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $available->id);

    $this->actingAs($admin)->getJson('/api/v1/book-copies?status=borrowed', bookCopyHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $borrowed->id);

    $this->actingAs($admin)->getJson('/api/v1/book-copies?status=record_error', bookCopyHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $recordError->id);

    $this->actingAs($admin)->getJson('/api/v1/book-copies?status=written_off', bookCopyHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $writtenOff->id);
});

test('index ignores an unknown enum filter without failing', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = bookCopyLibrary();
    $this->withSession(['active_library_id' => $library->id]);
    $book = bookCopyBook($library);

    BookCopy::factory()->for($library)->for($book)->create();

    $this->actingAs($admin)->getJson('/api/v1/book-copies?binding=zzz', bookCopyHeaders())
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('regular user cannot access copies', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/v1/book-copies', bookCopyHeaders())->assertForbidden();
});

test('bulk print returns a PDF for selected copies', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = bookCopyLibrary();
    $this->withSession(['active_library_id' => $library->id]);
    $book = bookCopyBook($library);

    $response = $this->actingAs($admin)->postJson("/api/v1/books/{$book->id}/copies", ['copies' => 2], bookCopyHeaders());
    $ids = $response->json('data.*.id');

    $print = $this->actingAs($admin)->postJson('/api/v1/book-copies/bulk/print', [
        'ids' => $ids,
        'format' => 'label',
    ], bookCopyHeaders());

    $print->assertOk();
    expect($print->headers->get('content-type'))->toContain('application/pdf');
});

test('resolving the archive unblocks adding copies', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = bookCopyLibrary();
    $this->withSession(['active_library_id' => $library->id]);
    $book = bookCopyBook($library);

    $archived = BookCopy::factory()->for($library)->for($book)->create(['order_number' => '9']);
    $archived->delete();

    $this->actingAs($admin)->postJson("/api/v1/books/{$book->id}/copies", ['copies' => 1], bookCopyHeaders())
        ->assertStatus(409);

    $archived->forceDelete();

    $this->actingAs($admin)->postJson("/api/v1/books/{$book->id}/copies", ['copies' => 1], bookCopyHeaders())
        ->assertCreated()
        ->assertJsonPath('data.0.order_number', '1');
});

test('availability excludes written-off and archived copies', function () {
    $library = bookCopyLibrary();
    $this->withSession(['active_library_id' => $library->id]);
    $book = bookCopyBook($library);

    BookCopy::factory()->for($library)->for($book)->create(['order_number' => '1']);
    BookCopy::factory()->for($library)->for($book)->create(['order_number' => '2', 'borrowed' => true]);
    $writtenOff = BookCopy::factory()->for($library)->for($book)->create(['order_number' => '3']);
    $archived = BookCopy::factory()->for($library)->for($book)->create(['order_number' => '4']);

    BookCopyWriteOff::factory()->forCopy($writtenOff)->create();
    $archived->delete();

    $availability = app(BookCopyAvailabilityService::class)->forBook($book);

    expect($availability['total'])->toBe(2)
        ->and($availability['available'])->toBe(1)
        ->and($availability['written_off'])->toBe(1)
        ->and($availability['archived'])->toBe(1);
});

test('sequence sync realigns the auto counter to the greatest existing number', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = bookCopyLibrary();
    $this->withSession(['active_library_id' => $library->id]);
    $book = bookCopyBook($library);

    BookCopy::factory()->for($library)->for($book)->create(['order_number' => '4']);
    $archived = BookCopy::factory()->for($library)->for($book)->create(['order_number' => '9']);
    $archived->delete();
    $archived->forceDelete();

    $this->actingAs($admin)->postJson('/api/v1/book-copies/inventory-sequence/sync', [
        'library_id' => $library->id,
    ], bookCopyHeaders())
        ->assertOk()
        ->assertJsonPath('last_number', 4)
        ->assertJsonPath('next_auto', 5);
});

test('superadmin sees only copies of the active library', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = bookCopyLibrary();
    $other = bookCopyLibrary();
    $this->withSession(['active_library_id' => $library->id]);

    $own = BookCopy::factory()->for($library)->for(bookCopyBook($library))->create(['order_number' => '1']);
    BookCopy::factory()->for($other)->for(bookCopyBook($other))->create(['order_number' => '1']);

    $this->actingAs($admin)->getJson('/api/v1/book-copies', bookCopyHeaders())
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $own->id);
});

test('superadmin without an active library cannot list copies or the archive', function () {
    $admin = User::factory()->superAdmin()->create();
    $library = Library::factory()->create();
    BookCopy::factory()->for($library)->create();

    $this->actingAs($admin)->getJson('/api/v1/book-copies', bookCopyHeaders())
        ->assertStatus(422);

    $this->actingAs($admin)->getJson('/api/v1/book-copies/archive', bookCopyHeaders())
        ->assertStatus(422);
});
