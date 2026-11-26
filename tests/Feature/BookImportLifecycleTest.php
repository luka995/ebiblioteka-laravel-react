<?php

use App\Enums\BookImportFailureEnum;
use App\Enums\BookImportRowStatusEnum;
use App\Enums\BookImportStatusEnum;
use App\Enums\LibraryRole;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookImport;
use App\Models\BookImportRow;
use App\Models\Library;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

function lifecycleAdmin(Library $library): User
{
    $user = User::factory()->create();
    $user->libraries()->attach($library, ['role' => LibraryRole::LibraryAdmin->value]);

    return $user;
}

function lifecycleImport(Library $library, array $attributes = []): BookImport
{
    $path = 'imports/import-'.uniqid().'.xlsx';
    Storage::disk('imports')->put($path, 'test workbook');

    return BookImport::create([
        'library_id' => $library->id,
        'original_filename' => 'books.xlsx',
        'file_path' => $path,
        'file_size' => 100,
        'status' => BookImportStatusEnum::Ready,
        ...$attributes,
    ]);
}

function lifecycleRow(BookImport $import, array $attributes = []): BookImportRow
{
    return $import->rows()->create([
        'row_number' => $import->rows()->count() + 15,
        'raw_payload' => ['title' => 'Test title'],
        'status' => BookImportRowStatusEnum::Pending,
        ...$attributes,
    ]);
}

beforeEach(function () {
    Storage::fake('imports');
    app()->setLocale('sr-Latn');
});

test('a ready import without imported rows can be cancelled', function () {
    $library = Library::factory()->create();
    $actor = lifecycleAdmin($library);
    $import = lifecycleImport($library);
    lifecycleRow($import);

    $this->actingAs($actor)->withSession(['active_library_id' => $library->id])
        ->postJson("/api/v1/book-imports/{$import->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', BookImportStatusEnum::Cancelled->value)
        ->assertJsonPath('data.can.cancel', true)
        ->assertJsonPath('data.can.delete', true);

    expect($import->fresh()->processing_token)->toBeNull()
        ->and($import->fresh()->lease_expires_at)->toBeNull()
        ->and($import->fresh()->finished_at)->not->toBeNull();
});

test('an import with imported rows cannot be cancelled or deleted', function () {
    $library = Library::factory()->create();
    $actor = lifecycleAdmin($library);
    $book = Book::factory()->for($library)->create();
    $copy = BookCopy::factory()->for($library)->for($book)->create();
    $import = lifecycleImport($library, ['imported_rows' => 1]);
    lifecycleRow($import, [
        'status' => BookImportRowStatusEnum::Imported,
        'book_id' => $book->id,
        'book_copy_id' => $copy->id,
        'failure_code' => BookImportFailureEnum::MissingTitle,
        'row_number' => 15,
    ]);

    $this->actingAs($actor)->withSession(['active_library_id' => $library->id])
        ->postJson("/api/v1/book-imports/{$import->id}/cancel")
        ->assertStatus(409);

    $this->actingAs($actor)->withSession(['active_library_id' => $library->id])
        ->deleteJson("/api/v1/book-imports/{$import->id}")
        ->assertStatus(409);

    expect($import->fresh())->not->toBeNull()
        ->and($import->rows()->count())->toBe(1)
        ->and(Storage::disk('imports')->exists($import->file_path))->toBeTrue();
});

test('an import without imported rows is deleted with rows and stored file', function () {
    $library = Library::factory()->create();
    $actor = lifecycleAdmin($library);
    $import = lifecycleImport($library);
    $row = lifecycleRow($import);
    $path = $import->file_path;

    $this->actingAs($actor)->withSession(['active_library_id' => $library->id])
        ->deleteJson("/api/v1/book-imports/{$import->id}")
        ->assertNoContent();

    expect(BookImport::find($import->id))->toBeNull()
        ->and(BookImportRow::find($row->id))->toBeNull()
        ->and(Storage::disk('imports')->exists($path))->toBeFalse();
});

test('a processing import must be cancelled before deletion', function () {
    $library = Library::factory()->create();
    $actor = lifecycleAdmin($library);
    $import = lifecycleImport($library, [
        'status' => BookImportStatusEnum::Processing,
        'processing_token' => (string) str()->uuid(),
        'lease_expires_at' => now()->addMinute(),
    ]);

    $this->actingAs($actor)->withSession(['active_library_id' => $library->id])
        ->deleteJson("/api/v1/book-imports/{$import->id}")
        ->assertStatus(409);

    $this->actingAs($actor)->withSession(['active_library_id' => $library->id])
        ->postJson("/api/v1/book-imports/{$import->id}/cancel")
        ->assertOk();

    $this->actingAs($actor)->withSession(['active_library_id' => $library->id])
        ->deleteJson("/api/v1/book-imports/{$import->id}")
        ->assertNoContent();
});

test('cancelled imports cannot be started again', function () {
    $library = Library::factory()->create();
    $actor = lifecycleAdmin($library);
    $import = lifecycleImport($library, ['status' => BookImportStatusEnum::Cancelled]);
    $row = lifecycleRow($import);

    $this->actingAs($actor)->withSession(['active_library_id' => $library->id])
        ->postJson("/api/v1/book-imports/{$import->id}/start", [
            'mode' => 'excel_direct',
            'row_ids' => [$row->id],
        ])->assertStatus(409);
});
