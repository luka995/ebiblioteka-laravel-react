<?php

use App\Enums\BookImportFailureEnum;
use App\Enums\BookImportModeEnum;
use App\Enums\BookImportRowStatusEnum;
use App\Enums\BookImportStatusEnum;
use App\Enums\LibraryRole;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookImport;
use App\Models\Library;
use App\Models\User;
use App\Services\AuthorizationService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

function bookImportRecord(Library $library, array $attributes = []): BookImport
{
    return BookImport::create(array_merge([
        'library_id' => $library->id,
        'original_filename' => 'books.xlsx',
        'file_path' => 'uploads/books.xlsx',
        'file_size' => 1024,
    ], $attributes));
}

test('import enum options use labels from the selected locale', function (string $locale) {
    app()->setLocale($locale);
    $translations = require lang_path($locale.'/book_imports.php');

    foreach ([
        BookImportStatusEnum::class => 'status',
        BookImportRowStatusEnum::class => 'row_status',
        BookImportModeEnum::class => 'modes',
        BookImportFailureEnum::class => 'failures',
    ] as $enum => $group) {
        $expected = [];

        foreach ($enum::cases() as $case) {
            $expected[$case->value] = $translations[$group][$case->value];
        }

        expect($enum::toArrayWithValue())->toBe($expected);
    }
})->with(['en', 'sr-Latn', 'sr-Cyrl']);

test('database rejects a second active import for the same library', function (
    BookImportStatusEnum $first,
    BookImportStatusEnum $second,
) {
    $library = Library::factory()->create();
    bookImportRecord($library, ['status' => $first]);

    expect(fn () => bookImportRecord($library, ['status' => $second]))
        ->toThrow(QueryException::class, 'book_imports_one_active_per_library');
})->with([
    [BookImportStatusEnum::Pending, BookImportStatusEnum::Pending],
    [BookImportStatusEnum::Pending, BookImportStatusEnum::Ready],
    [BookImportStatusEnum::Pending, BookImportStatusEnum::Processing],
    [BookImportStatusEnum::Ready, BookImportStatusEnum::Pending],
    [BookImportStatusEnum::Ready, BookImportStatusEnum::Ready],
    [BookImportStatusEnum::Ready, BookImportStatusEnum::Processing],
    [BookImportStatusEnum::Processing, BookImportStatusEnum::Pending],
    [BookImportStatusEnum::Processing, BookImportStatusEnum::Ready],
    [BookImportStatusEnum::Processing, BookImportStatusEnum::Processing],
]);

test('finished imports do not block new imports or imports in other libraries', function (
    BookImportStatusEnum $status,
) {
    $library = Library::factory()->create();
    bookImportRecord($library, ['status' => $status]);
    bookImportRecord($library, ['status' => $status]);
    $active = bookImportRecord($library);
    $other = bookImportRecord(Library::factory()->create());

    expect($active->fresh()->status)->toBe(BookImportStatusEnum::Pending)
        ->and($other->fresh()->status)->toBe(BookImportStatusEnum::Pending);
})->with([
    BookImportStatusEnum::Completed,
    BookImportStatusEnum::CompletedWithErrors,
    BookImportStatusEnum::Failed,
]);

test('database also prevents reactivating an old import while another is active', function () {
    $library = Library::factory()->create();
    $old = bookImportRecord($library, ['status' => BookImportStatusEnum::CompletedWithErrors]);
    bookImportRecord($library, ['status' => BookImportStatusEnum::Ready]);

    expect(fn () => $old->update(['status' => BookImportStatusEnum::Processing]))
        ->toThrow(QueryException::class, 'book_imports_one_active_per_library');
});

test('row numbers are unique within an import but can repeat across imports', function () {
    $first = bookImportRecord(Library::factory()->create());
    $second = bookImportRecord(Library::factory()->create());
    $data = ['row_number' => 15, 'raw_payload' => ['title' => 'Naslov']];
    $first->rows()->create($data);
    $second->rows()->create($data);

    expect(fn () => $first->rows()->create($data))
        ->toThrow(QueryException::class, 'book_import_rows_book_import_id_row_number_unique');
});

test('import history preserves original input and diagnostics when related records are deleted', function () {
    $library = Library::factory()->create();
    $user = User::factory()->create();
    $book = Book::factory()->for($library)->create();
    $copy = BookCopy::factory()->for($library)->for($book)->create();
    $original = ['title' => 'Original', 'inventory_number' => '00123'];
    $import = bookImportRecord($library, [
        'user_id' => $user->id,
        'status' => BookImportStatusEnum::CompletedWithErrors,
        'headers' => ['Naslov', 'Inventarni broj'],
        'column_map' => ['title' => 0, 'inventory_number' => 1],
        'processed_rows' => 1,
        'failed_rows' => 1,
        'completed_attempts' => 2,
    ]);
    $row = $import->rows()->create([
        'row_number' => 15,
        'raw_payload' => $original,
        'input_overrides' => ['title' => 'Ispravka', 'inventory_number' => null],
        'input_version' => 2,
        'status' => BookImportRowStatusEnum::Failed,
        'failure_code' => BookImportFailureEnum::LookupFailed,
        'attempt_mode' => BookImportModeEnum::IsbnCheck,
        'attempts' => 3,
        'completed_attempts' => 2,
        'lookup_diagnostics' => ['source' => 'cobiss', 'status' => 'failed', 'reason' => 'timeout'],
        'book_id' => $book->id,
        'book_copy_id' => $copy->id,
        'processing_requested_at' => now(),
    ]);

    expect($row->bookImport->is($import))->toBeTrue()
        ->and($row->book->is($book))->toBeTrue()
        ->and($row->bookCopy->is($copy))->toBeTrue()
        ->and($import->library->is($library))->toBeTrue()
        ->and($import->user->is($user))->toBeTrue();

    DB::table('book_copies')->where('id', $copy->id)->delete();
    DB::table('books')->where('id', $book->id)->delete();
    DB::table('users')->where('id', $user->id)->delete();

    $row->refresh();
    $import->refresh();

    expect($row->book_id)->toBeNull()
        ->and($row->book_copy_id)->toBeNull()
        ->and($import->user_id)->toBeNull()
        ->and($row->raw_payload)->toBe($original)
        ->and($row->input_overrides)->toBe(['title' => 'Ispravka', 'inventory_number' => null])
        ->and($row->failure_code)->toBe(BookImportFailureEnum::LookupFailed)
        ->and($row->lookup_diagnostics['reason'])->toBe('timeout')
        ->and($row->attempt_mode)->toBe(BookImportModeEnum::IsbnCheck)
        ->and($row->attempts)->toBe(3)
        ->and($row->completed_attempts)->toBe(2)
        ->and($import->processed_rows)->toBe(1)
        ->and($import->completed_attempts)->toBe(2);
});

test('deleting a library cascades its imports and staged rows', function () {
    $library = Library::factory()->create();
    $import = bookImportRecord($library);
    $row = $import->rows()->create(['row_number' => 15, 'raw_payload' => []]);

    DB::table('libraries')->where('id', $library->id)->delete();

    $this->assertModelMissing($import);
    $this->assertModelMissing($row);
});

test('staff can manage imports only in their library', function (LibraryRole $role) {
    $library = Library::factory()->create();
    $actor = User::factory()->create();
    $actor->libraries()->attach($library, ['role' => $role->value]);
    $own = bookImportRecord($library);
    $foreign = bookImportRecord(Library::factory()->create());
    $gate = Gate::forUser($actor);

    foreach (['view', 'start', 'retry', 'updateRow'] as $action) {
        expect($gate->allows($action, $own))->toBeTrue()
            ->and($gate->allows($action, $foreign))->toBeFalse();
    }

    $this->actingAs($actor)->getJson('/api/v1/auth/me', [
        'Origin' => 'http://localhost:3001',
        'Accept' => 'application/json',
    ])->assertOk()
        ->assertJsonPath('permissions.book_imports.viewAny', true)
        ->assertJsonPath('permissions.book_imports.create', true);
})->with([LibraryRole::LibraryAdmin, LibraryRole::Librarian]);

test('members and guests cannot access imports', function () {
    $library = Library::factory()->create();
    $member = User::factory()->create();
    $member->libraries()->attach($library, ['role' => LibraryRole::User->value]);
    $import = bookImportRecord($library);

    foreach ([$member, null] as $actor) {
        foreach (['viewAny', 'create'] as $action) {
            expect(Gate::forUser($actor)->allows($action, BookImport::class))->toBeFalse();
        }

        foreach (['view', 'start', 'retry', 'updateRow'] as $action) {
            expect(Gate::forUser($actor)->allows($action, $import))->toBeFalse();
        }
    }

    expect(app(AuthorizationService::class)->globalPermissions($member)['book_imports'])
        ->toBe(['viewAny' => false, 'create' => false]);
});

test('superadmins can manage imports from any library', function () {
    $actor = User::factory()->superAdmin()->create();
    $import = bookImportRecord(Library::factory()->create());
    $gate = Gate::forUser($actor);

    foreach (['viewAny', 'create'] as $action) {
        expect($gate->allows($action, BookImport::class))->toBeTrue();
    }

    foreach (['view', 'start', 'retry', 'updateRow'] as $action) {
        expect($gate->allows($action, $import))->toBeTrue();
    }
});
