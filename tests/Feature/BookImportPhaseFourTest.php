<?php

use App\DTOs\Queries\BookImportRowListQueryDTO;
use App\DTOs\Queries\SortingDTO;
use App\Enums\BookImportFailureEnum;
use App\Enums\BookImportModeEnum;
use App\Enums\BookImportRowStatusEnum;
use App\Enums\BookImportStatusEnum;
use App\Enums\LibraryRole;
use App\Jobs\ProcessBookImportJob;
use App\Models\BookCopy;
use App\Models\BookImport;
use App\Models\BookImportRow;
use App\Models\Library;
use App\Models\User;
use App\Services\BookImports\BookImportStartService;
use App\Services\Queries\BookImportRowQueryService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

function phaseFourAdmin(Library $library): User
{
    $user = User::factory()->create();
    $user->libraries()->attach($library, ['role' => LibraryRole::LibraryAdmin->value]);

    return $user;
}

function phaseFourImport(Library $library, array $attributes = []): BookImport
{
    return BookImport::create([
        'library_id' => $library->id,
        'original_filename' => 'books.xlsx',
        'file_path' => 'imports/books.xlsx',
        'file_size' => 100,
        'status' => BookImportStatusEnum::Ready,
        'headers' => ['ISBN', 'Naslov', 'Inventarni broj'],
        'column_map' => ['isbn' => 0, 'title' => 1, 'inventory_number' => 2],
        'has_inventory_column' => true,
        ...$attributes,
    ]);
}

function phaseFourRow(BookImport $import, array $payload, array $attributes = []): BookImportRow
{
    return $import->rows()->create([
        'row_number' => $import->rows()->count() + 2,
        'raw_payload' => $payload,
        'isbn' => $payload['isbn'] ?? null,
        'title' => $payload['title'] ?? null,
        'inventory_number' => $payload['inventory_number'] ?? null,
        ...$attributes,
    ]);
}

beforeEach(function () {
    Queue::fake();
    Http::preventStrayRequests();
    app()->setLocale('sr-Latn');
});

test('rows endpoint paginates but returns full filtered counts and start selects matching rows across pages', function () {
    $library = Library::factory()->create();
    $actor = phaseFourAdmin($library);
    $import = phaseFourImport($library);

    foreach (range(1, 205) as $index) {
        phaseFourRow($import, [
            'isbn' => null,
            'title' => 'Андрић књига '.$index,
            'inventory_number' => (string) (1000 + $index),
        ], $index === 1 ? [
            'status' => BookImportRowStatusEnum::Failed,
            'failure_code' => BookImportFailureEnum::LookupFailed,
        ] : []);
    }

    $request = $this->actingAs($actor)->withSession(['active_library_id' => $library->id]);
    $request->getJson("/api/v1/book-imports/{$import->id}/rows?search=Andrić&per_page=2")
        ->assertOk()
        ->assertJsonPath('meta.matching_pending_rows', 204)
        ->assertJsonPath('meta.matching_failed_rows', 1)
        ->assertJsonCount(2, 'data');

    $request->postJson("/api/v1/book-imports/{$import->id}/start", [
        'mode' => 'excel_direct',
        'all_matching' => true,
        'filters' => ['status' => 'pending', 'search' => 'Andrić'],
    ])->assertAccepted()->assertJsonPath('data.selected_rows', 204);

    expect($import->fresh()->status)->toBe(BookImportStatusEnum::Processing)
        ->and($import->rows()->where('status', BookImportRowStatusEnum::Pending)->whereNotNull('processing_requested_at')->count())->toBe(204)
        ->and($import->rows()->where('status', BookImportRowStatusEnum::Failed)->count())->toBe(1);
    Queue::assertPushed(ProcessBookImportJob::class, fn (ProcessBookImportJob $job): bool => $job->mode === BookImportModeEnum::ExcelDirect);
});

test('row query service applies filters, transliteration in both directions and the sort whitelist', function () {
    $library = Library::factory()->create();
    $import = phaseFourImport($library);
    $latin = phaseFourRow($import, ['title' => 'Andrić izdanja']);
    phaseFourRow($import, ['title' => 'Андрић издања']);
    phaseFourRow($import, ['title' => 'Ne povezano']);

    $query = new BookImportRowListQueryDTO(
        new SortingDTO('untrusted_column', 'asc'),
        BookImportRowStatusEnum::Pending,
        search: 'Андрић',
        perPage: 1,
    );
    $rows = app(BookImportRowQueryService::class)->build($query, $import)->get();

    expect($rows->pluck('id')->all())->toBe([$latin->id, $latin->id + 1]);
});

test('selection endpoints reject mixed manual and filtered selection', function () {
    $library = Library::factory()->create();
    $actor = phaseFourAdmin($library);
    $import = phaseFourImport($library);
    $row = phaseFourRow($import, ['title' => 'A title']);

    $this->actingAs($actor)->withSession(['active_library_id' => $library->id])
        ->postJson("/api/v1/book-imports/{$import->id}/start", [
            'mode' => 'isbn_check',
            'row_ids' => [$row->id],
            'all_matching' => true,
            'filters' => [],
        ])->assertUnprocessable();
});

test('retry cannot reactivate an old import while another library import is active', function () {
    $library = Library::factory()->create();
    $actor = phaseFourAdmin($library);
    $finished = phaseFourImport($library, ['status' => BookImportStatusEnum::CompletedWithErrors]);
    $row = phaseFourRow($finished, ['title' => 'Failed title'], [
        'status' => BookImportRowStatusEnum::Failed,
        'failure_code' => BookImportFailureEnum::LookupFailed,
    ]);
    phaseFourImport($library);

    $this->actingAs($actor)->withSession(['active_library_id' => $library->id])
        ->postJson("/api/v1/book-imports/{$finished->id}/retry", [
            'mode' => 'excel_direct',
            'row_ids' => [$row->id],
        ])->assertStatus(409);

    expect($row->fresh()->status)->toBe(BookImportRowStatusEnum::Failed)
        ->and($finished->fresh()->status)->toBe(BookImportStatusEnum::CompletedWithErrors);
});

test('preview corrections are versioned without clearing failure and retry preserves attempt counters', function () {
    $library = Library::factory()->create();
    $actor = phaseFourAdmin($library);
    $import = phaseFourImport($library, [
        'status' => BookImportStatusEnum::CompletedWithErrors,
        'total_rows' => 1,
        'processed_rows' => 1,
        'failed_rows' => 1,
        'completed_attempts' => 4,
    ]);
    $row = phaseFourRow($import, [
        'isbn' => '9780000000001',
        'title' => 'Old title',
        'inventory_number' => '00102',
    ], [
        'status' => BookImportRowStatusEnum::Failed,
        'failure_code' => BookImportFailureEnum::LookupFailed,
        'attempts' => 7,
        'completed_attempts' => 4,
    ]);
    $url = "/api/v1/book-imports/{$import->id}/rows/{$row->id}";
    $request = $this->actingAs($actor)->withSession(['active_library_id' => $library->id]);

    $request->patchJson($url, [
        'input_version' => 1,
        'changes' => ['title' => 'Corrected title', 'inventory_number' => '00103'],
    ])->assertOk()
        ->assertJsonPath('data.input_version', 2)
        ->assertJsonPath('data.effective_input.title', 'Corrected title')
        ->assertJsonPath('data.failure_code', 'lookup_failed');

    expect($row->fresh()->title)->toBe('Corrected title')
        ->and($row->fresh()->inventory_number)->toBe('00103')
        ->and($row->fresh()->failure_code)->toBe(BookImportFailureEnum::LookupFailed);

    $request->patchJson($url, ['input_version' => 1, 'changes' => ['title' => 'Stale']])
        ->assertStatus(409);

    $request->postJson("/api/v1/book-imports/{$import->id}/retry", [
        'mode' => 'excel_direct',
        'all_matching' => true,
        'filters' => ['status' => 'failed', 'search' => 'Corrected'],
    ])->assertAccepted()->assertJsonPath('data.selected_rows', 1);

    expect($row->fresh()->status)->toBe(BookImportRowStatusEnum::Pending)
        ->and($row->fresh()->attempt_mode)->toBe(BookImportModeEnum::ExcelDirect)
        ->and($row->fresh()->attempts)->toBe(7)
        ->and($row->fresh()->completed_attempts)->toBe(4)
        ->and($import->fresh()->processed_rows)->toBe(0)
        ->and($import->fresh()->failed_rows)->toBe(0);
});

test('inventory number corrections are rejected when the uploaded workbook has no inventory column', function () {
    $library = Library::factory()->create();
    $actor = phaseFourAdmin($library);
    $import = phaseFourImport($library, [
        'status' => BookImportStatusEnum::CompletedWithErrors,
        'has_inventory_column' => false,
    ]);
    $row = phaseFourRow($import, ['isbn' => null, 'title' => 'Title'], [
        'status' => BookImportRowStatusEnum::Failed,
        'failure_code' => BookImportFailureEnum::MissingTitle,
    ]);

    $this->actingAs($actor)->withSession(['active_library_id' => $library->id])
        ->patchJson("/api/v1/book-imports/{$import->id}/rows/{$row->id}", [
            'input_version' => 1,
            'changes' => ['inventory_number' => '104'],
        ])->assertUnprocessable();
});

test('excel direct processing accepts an empty ISBN and never contacts metadata providers', function () {
    $library = Library::factory()->create();
    $import = phaseFourImport($library, ['has_inventory_column' => false]);
    $row = phaseFourRow($import, ['isbn' => null, 'title' => 'Directly imported title'], [
        'inventory_number' => null,
    ]);

    app(BookImportStartService::class)->start($import, [$row->id], BookImportModeEnum::ExcelDirect);
    app()->call([new ProcessBookImportJob($import->id, BookImportModeEnum::ExcelDirect), 'handle']);

    expect($row->fresh()->status)->toBe(BookImportRowStatusEnum::Imported)
        ->and($row->fresh()->bookCopy->isbn)->toBeNull()
        ->and(BookCopy::where('book_id', $row->fresh()->book_id)->count())->toBe(1)
        ->and($row->fresh()->bookCopy->order_number)->toBe('1');

    Http::assertNothingSent();
});
