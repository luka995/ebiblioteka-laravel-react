<?php

use App\DTOs\IsbnLookupResultDTO;
use App\Enums\BookImportFailureEnum;
use App\Enums\BookImportModeEnum;
use App\Enums\BookImportRowStatusEnum;
use App\Enums\BookImportStatusEnum;
use App\Enums\IsbnLookupStatusEnum;
use App\Exceptions\BookImportLeaseLostException;
use App\Jobs\PrepareBookImportJob;
use App\Jobs\ProcessBookImportJob;
use App\Models\Author;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookImport;
use App\Models\BookImportRow;
use App\Models\BookInventorySequence;
use App\Models\Category;
use App\Models\Library;
use App\Services\BookImports\BookImportLeaseService;
use App\Services\BookImports\BookImportRowProcessor;
use App\Services\BookImports\BookImportStartService;
use App\Services\BookService;
use App\Services\InventoryNumberService;
use App\Services\Isbn\BookMetadata;
use App\Services\Isbn\IsbnMetadataService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

function processingImport(array $attributes = []): BookImport
{
    return BookImport::create([
        'library_id' => Library::factory()->create()->id,
        'original_filename' => 'books.xlsx', 'file_path' => 'not-read.xlsx', 'file_size' => 10,
        'status' => BookImportStatusEnum::Ready,
        'headers' => ['ISBN', 'Title'], 'column_map' => ['isbn' => 0, 'title' => 1],
        ...$attributes,
    ]);
}

function processingRow(BookImport $import, array $payload = [], array $attributes = []): BookImportRow
{
    $payload = ['isbn' => '9788681131213', 'title' => 'Excel title', 'authors' => ['Excel author'], ...$payload];

    return $import->rows()->create([
        'row_number' => 15 + $import->rows()->count(),
        'raw_payload' => $payload, 'isbn' => $payload['isbn'], 'title' => $payload['title'],
        'inventory_number' => $payload['inventory_number'] ?? null,
        ...$attributes,
    ]);
}

function processingMetadata(?BookMetadata $metadata = null): void
{
    $service = Mockery::mock(IsbnMetadataService::class);
    $service->shouldReceive('lookupForImport')->andReturn(new IsbnLookupResultDTO(
        IsbnLookupStatusEnum::Found,
        $metadata ?? new BookMetadata('nbs', '9788681131213', 'COBISS title', ['COBISS author']),
        ['provider' => 'nbs', 'status' => 'found'],
    ));
    app()->instance(IsbnMetadataService::class, $service);
}

function processSelected(BookImport $import, array $rows): void
{
    app(BookImportStartService::class)->start($import, array_map(fn ($row) => $row->id, $rows));
    app()->call([new ProcessBookImportJob($import->id), 'handle']);
}

beforeEach(function () {
    Queue::fake();
    Http::preventStrayRequests();
    app()->setLocale('sr-Latn');
    config(['isbn.nbs.crawl_delay' => 0, 'isbn.http_retry_delay_ms' => 0]);
});

test('selected rows merge COBISS fields individually and snapshot actual persisted values', function () {
    processingMetadata(new BookMetadata(
        'nbs', '9788681131213', 'COBISS title', ['COBISS author'],
        publisher: 'COBISS publisher', publishPlace: ' ', pages: 0,
        coverUrl: 'javascript:invalid', category: 'unmapped material',
    ));
    $import = processingImport();
    $row = processingRow($import, [
        'publisher' => 'Excel publisher', 'publish_place' => 'Excel place', 'num_of_pages' => 42,
        'cover_url' => 'https://books.test/cover.jpg', 'category' => 'Root / Leaf',
        'price' => '0', 'notice' => 'Excel notice', 'origin' => 'Purchase', 'issue_number' => 'Second',
    ], ['input_overrides' => ['category' => 'Edited / Category', 'notice' => null]]);
    $untouched = processingRow($import);
    $raw = $row->fresh()->raw_payload;
    processSelected($import, [$row]);
    $row->refresh();
    $copy = $row->bookCopy;

    expect($row->status)->toBe(BookImportRowStatusEnum::Imported)
        ->and($row->raw_payload)->toBe($raw)
        ->and($row->book->name)->toBe('COBISS title')
        ->and($row->book->authors->pluck('name')->all())->toBe(['COBISS author'])
        ->and($copy->publisher)->toBe('COBISS publisher')
        ->and($copy->publish_place)->toBe('Excel place')
        ->and($copy->num_of_pages)->toBe(0)
        ->and($copy->notice)->toBeNull()
        ->and($copy->price)->toBe('0.00')
        ->and($copy->issue_number)->toBe('Second')
        ->and($copy->order_number)->toBe('1')
        ->and($copy->barcode)->toBe(app(InventoryNumberService::class)->barcodeFor('1'))
        ->and($row->resolved_payload['category'])->toBe('Edited / Category')
        ->and($row->resolved_payload['date_add'])->toBe(today()->toDateString())
        ->and($row->field_sources)->toMatchArray([
            'title' => 'cobiss', 'publish_place' => 'excel', 'num_of_pages' => 'cobiss',
            'cover_url' => 'excel', 'category' => 'preview_edit', 'notice' => 'preview_edit',
            'inventory_number' => 'generated', 'barcode' => 'generated', 'date_add' => 'generated',
        ])
        ->and($row->lookup_diagnostics['invalid_fields'])->toBe(['cover_url'])
        ->and($row->attempts)->toBe(1)->and($row->completed_attempts)->toBe(1)
        ->and($untouched->fresh()->attempts)->toBe(0)
        ->and($untouched->fresh()->status)->toBe(BookImportRowStatusEnum::Pending)
        ->and($import->fresh()->status)->toBe(BookImportStatusEnum::Ready)
        ->and($import->fresh()->processed_rows)->toBe(1);
});

test('local ISBN reuses only an active same-library book without inheriting copy metadata', function () {
    $import = processingImport();
    $book = Book::factory()->create([
        'library_id' => $import->library_id, 'name' => 'Existing title',
        'description' => null, 'cover_url' => null, 'category_primary_id' => null, 'category_secondary_id' => null,
    ]);
    $old = BookCopy::factory()->create([
        'library_id' => $import->library_id, 'book_id' => $book->id,
        'isbn' => '8681131214', 'order_number' => '50', 'publisher' => 'Old publisher',
    ]);
    $row = processingRow($import, [
        'publisher' => 'Excel publisher', 'description' => 'Excel description',
        'cover_url' => 'https://books.test/excel-cover.jpg',
        'category' => 'Excel / Primary', 'category_secondary' => 'Excel / Secondary',
    ]);
    processSelected($import, [$row]);

    expect($row->fresh()->book_id)->toBe($book->id)
        ->and($row->fresh()->bookCopy->publisher)->toBe('Excel publisher')
        ->and($row->fresh()->bookCopy->order_number)->toBe('51')
        ->and($row->fresh()->resolved_payload['title'])->toBe('Existing title')
        ->and($row->fresh()->field_sources['title'])->toBe('existing_book')
        ->and($row->fresh()->field_sources['authors'])->toBe('excel')
        ->and($row->fresh()->field_sources['description'])->toBe('excel')
        ->and($row->fresh()->field_sources['cover_url'])->toBe('excel')
        ->and($row->fresh()->field_sources['category'])->toBe('excel')
        ->and($row->fresh()->field_sources['category_secondary'])->toBe('excel')
        ->and($book->fresh()->description)->toBe('Excel description')
        ->and($book->fresh()->cover_url)->toBe('https://books.test/excel-cover.jpg')
        ->and($book->fresh()->authors->pluck('name')->all())->toBe(['Excel author'])
        ->and($book->fresh()->categoryPrimary->fullName())->toBe('Excel / Primary')
        ->and($book->fresh()->categorySecondary->fullName())->toBe('Excel / Secondary')
        ->and($old->fresh()->publisher)->toBe('Old publisher');
    Http::assertNothingSent();
});

test('duplicate book is enriched from valid COBISS values only where stored fields are blank', function () {
    processingMetadata(new BookMetadata(
        'nbs', '9788681131213', 'COBISS title', ['COBISS author'],
        description: 'COBISS description', coverUrl: 'https://cobiss.test/cover.jpg',
    ));
    $import = processingImport();
    $book = app(BookService::class)->create([
        'library_id' => $import->library_id,
        'name' => 'COBISS title',
        'authors' => ['COBISS author'],
        'description' => null,
        'cover_url' => null,
    ]);
    $row = processingRow($import, [
        'description' => 'Excel description',
        'cover_url' => 'https://books.test/excel-cover.jpg',
    ]);

    processSelected($import, [$row]);

    expect($row->fresh()->book_id)->toBe($book->id)
        ->and($book->fresh()->description)->toBe('COBISS description')
        ->and($book->fresh()->cover_url)->toBe('https://cobiss.test/cover.jpg')
        ->and($book->fresh()->name)->toBe('COBISS title')
        ->and($book->fresh()->authors->pluck('name')->all())->toBe(['COBISS author'])
        ->and($row->fresh()->field_sources['description'])->toBe('cobiss')
        ->and($row->fresh()->field_sources['cover_url'])->toBe('cobiss')
        ->and($row->fresh()->field_sources['title'])->toBe('existing_book')
        ->and($row->fresh()->field_sources['authors'])->toBe('existing_book');
});

test('foreign or archived ISBN matches never bypass COBISS', function () {
    processingMetadata();
    $import = processingImport();
    $foreign = BookCopy::factory()->create(['isbn' => '9788681131213']);
    $book = Book::factory()->create(['library_id' => $import->library_id, 'name' => 'Archived title']);
    $copy = BookCopy::factory()->create([
        'library_id' => $import->library_id, 'book_id' => $book->id, 'isbn' => '9788681131213',
    ]);
    $copy->delete();
    $row = processingRow($import);
    processSelected($import, [$row]);

    expect($row->fresh()->book_id)->not->toBeIn([$foreign->book_id, $book->id])
        ->and($row->fresh()->book->name)->toBe('COBISS title');
});

test('historical manual numbers allow gaps and disorder and the next auto number is monotonic', function () {
    processingMetadata();
    $import = processingImport(['has_inventory_column' => true]);
    $first = processingRow($import, ['inventory_number' => '00500']);
    $second = processingRow($import, ['inventory_number' => '3']);
    processSelected($import, [$first, $second]);

    expect($first->fresh()->bookCopy->order_number)->toBe('500')
        ->and($second->fresh()->bookCopy->order_number)->toBe('3')
        ->and(BookInventorySequence::where('library_id', $import->library_id)->value('last_number'))->toBe(500);
    $next = processingImport(['library_id' => $import->library_id]);
    $row = processingRow($next);
    processSelected($next, [$row]);
    expect($row->fresh()->bookCopy->order_number)->toBe('501');
});

test('archived copies and a higher saved sequence are both respected', function (int $sequence, string $expected) {
    processingMetadata();
    $import = processingImport();
    $book = Book::factory()->create(['library_id' => $import->library_id]);
    $copy = BookCopy::factory()->create(['library_id' => $import->library_id, 'book_id' => $book->id, 'order_number' => '700']);
    $copy->delete();
    BookInventorySequence::where('library_id', $import->library_id)->update(['last_number' => $sequence]);
    $row = processingRow($import);
    processSelected($import, [$row]);
    expect($row->fresh()->bookCopy->order_number)->toBe($expected);
})->with([[2, '701'], [900, '901']]);

test('missing invalid or duplicate manual inventory fails without consuming a number or calling COBISS', function (?string $value, BookImportFailureEnum $failure) {
    $import = processingImport(['has_inventory_column' => true]);
    $book = Book::factory()->create(['library_id' => $import->library_id]);
    $copy = BookCopy::factory()->create(['library_id' => $import->library_id, 'book_id' => $book->id, 'order_number' => '9']);
    $copy->delete();
    $row = processingRow($import, ['inventory_number' => $value]);
    processSelected($import, [$row]);
    expect($row->fresh()->failure_code)->toBe($failure)
        ->and($row->fresh()->completed_attempts)->toBe(1)
        ->and($import->fresh()->status)->toBe(BookImportStatusEnum::CompletedWithErrors)
        ->and(BookInventorySequence::where('library_id', $import->library_id)->value('last_number'))->toBe(0)
        ->and(BookCopy::withTrashed()->count())->toBe(1);
    Http::assertNothingSent();
})->with([
    [null, BookImportFailureEnum::MissingInventoryNumber],
    ['A1', BookImportFailureEnum::InvalidInventoryNumber],
    ['1000000000000', BookImportFailureEnum::InvalidInventoryNumber],
    ['009', BookImportFailureEnum::DuplicateInventoryNumber],
]);

test('copy failure rolls back new book authors categories and sequence without poisoning the next row cache', function () {
    processingMetadata();
    $import = processingImport();
    $existing = Book::factory()->create(['library_id' => $import->library_id]);
    BookCopy::factory()->create([
        'library_id' => $import->library_id, 'book_id' => $existing->id, 'order_number' => '700', 'seq_number' => 12,
    ])->delete();
    $failed = processingRow($import, ['seq_number' => 12, 'category' => 'New / Category']);
    $good = processingRow($import, ['category' => 'New / Category']);
    app(BookImportStartService::class)->start($import, [$failed->id, $good->id]);
    $token = app(BookImportLeaseService::class)->claim($import->id, BookImportModeEnum::IsbnCheck);
    $processor = app(BookImportRowProcessor::class);
    $processor->process($import->id, $failed->id, $token);

    expect($failed->fresh()->failure_code)->toBe(BookImportFailureEnum::DuplicateSeqNumber)
        ->and(Book::count())->toBe(1)->and(Author::count())->toBe(0)->and(Category::count())->toBe(0)
        ->and(BookInventorySequence::where('library_id', $import->library_id)->value('last_number'))->toBe(0);
    $processor->process($import->id, $good->id, $token);
    expect($good->fresh()->status)->toBe(BookImportRowStatusEnum::Imported)
        ->and($good->fresh()->bookCopy->order_number)->toBe('701')
        ->and(Author::count())->toBe(1)->and(Category::count())->toBe(2);
});

test('exhausted twelve-digit inventory fails and rolls back all new entities', function () {
    processingMetadata();
    $import = processingImport();
    BookInventorySequence::where('library_id', $import->library_id)->update(['last_number' => 999999999999]);
    $row = processingRow($import);
    processSelected($import, [$row]);
    expect($row->fresh()->failure_code)->toBe(BookImportFailureEnum::InventoryCollision)
        ->and(Book::count())->toBe(0)->and(BookCopy::count())->toBe(0)->and(Author::count())->toBe(0)
        ->and(BookInventorySequence::where('library_id', $import->library_id)->value('last_number'))->toBe(999999999999);
});

test('duplicate jobs and imported rows never allocate a second copy', function () {
    processingMetadata();
    $import = processingImport();
    $row = processingRow($import);
    processSelected($import, [$row]);
    app()->call([new ProcessBookImportJob($import->id), 'handle']);
    expect(BookCopy::count())->toBe(1)->and($row->fresh()->attempts)->toBe(1)
        ->and($import->fresh()->completed_attempts)->toBe(1)
        ->and($import->fresh()->status)->toBe(BookImportStatusEnum::Completed);
});

test('lease takeover discards stale external metadata and retries interrupted work with honest counters', function () {
    $import = processingImport();
    $row = processingRow($import);
    app(BookImportStartService::class)->start($import, [$row->id]);
    $leases = app(BookImportLeaseService::class);
    $oldToken = $leases->claim($import->id, BookImportModeEnum::IsbnCheck);
    $newToken = null;
    $service = Mockery::mock(IsbnMetadataService::class);
    $service->shouldReceive('lookupForImport')->once()->andReturnUsing(function () use ($import, $leases, &$newToken) {
        $import->update(['lease_expires_at' => now()->subSecond()]);
        $newToken = $leases->claim($import->id, BookImportModeEnum::IsbnCheck);

        return new IsbnLookupResultDTO(IsbnLookupStatusEnum::Found, new BookMetadata('nbs', title: 'Stale title'));
    });
    app()->instance(IsbnMetadataService::class, $service);
    expect(fn () => app(BookImportRowProcessor::class)->process($import->id, $row->id, $oldToken))
        ->toThrow(BookImportLeaseLostException::class);
    expect(BookCopy::count())->toBe(0)->and($row->fresh()->status)->toBe(BookImportRowStatusEnum::Pending)
        ->and($row->fresh()->attempts)->toBe(1)->and($row->fresh()->completed_attempts)->toBe(0);
    processingMetadata();
    app(BookImportRowProcessor::class)->process($import->id, $row->id, $newToken);
    expect($row->fresh()->status)->toBe(BookImportRowStatusEnum::Imported)
        ->and($row->fresh()->attempts)->toBe(2)->and($row->fresh()->completed_attempts)->toBe(1);
});

test('processing self-dispatches bounded batches instead of one job per row', function () {
    processingMetadata();
    config(['book_imports.standard_batch_size' => 2]);
    $import = processingImport();
    $rows = array_map(fn () => processingRow($import), range(1, 5));
    app(BookImportStartService::class)->start($import, array_map(fn ($row) => $row->id, $rows));
    app()->call([new ProcessBookImportJob($import->id), 'handle']);
    expect(BookCopy::count())->toBe(2)->and($import->fresh()->status)->toBe(BookImportStatusEnum::Processing)
        ->and($import->fresh()->processing_token)->toBeNull();
    app()->call([new ProcessBookImportJob($import->id), 'handle']);
    app()->call([new ProcessBookImportJob($import->id), 'handle']);
    expect(BookCopy::count())->toBe(5)->and($import->fresh()->processed_rows)->toBe(5)
        ->and($import->fresh()->status)->toBe(BookImportStatusEnum::Completed);
    Queue::assertPushed(ProcessBookImportJob::class, 3);
});

test('start rejects empty foreign failed duplicate and non-ready selections atomically', function () {
    $import = processingImport();
    $row = processingRow($import);
    $failed = processingRow($import, [], ['status' => BookImportRowStatusEnum::Failed]);
    $foreign = processingRow(processingImport());
    $start = app(BookImportStartService::class);

    foreach ([[], [$foreign->id], [$failed->id], [$row->id, $row->id]] as $ids) {
        expect(fn () => $start->start($import, $ids))->toThrow(ValidationException::class);
    }

    expect($row->fresh()->processing_requested_at)->toBeNull();
    Queue::assertNothingPushed();
    $start->start($import, [$row->id]);
    expect(fn () => $start->start($import, [$row->id]))->toThrow(ValidationException::class);
});

test('failed callback never overwrites a live owner and does not invent attempts for unstarted rows', function () {
    $import = processingImport();
    $unstarted = processingRow($import);
    $started = processingRow($import);
    app(BookImportStartService::class)->start($import, [$unstarted->id, $started->id]);
    app(BookImportLeaseService::class)->claim($import->id, BookImportModeEnum::IsbnCheck);
    $started->update(['status' => BookImportRowStatusEnum::Processing, 'attempts' => 1]);
    (new ProcessBookImportJob($import->id))->failed(new RuntimeException('worker failed'));
    expect($import->fresh()->status)->toBe(BookImportStatusEnum::Processing);
    $import->update(['lease_expires_at' => now()->subSecond()]);
    (new ProcessBookImportJob($import->id))->failed(new RuntimeException('worker failed'));
    expect($import->fresh()->status)->toBe(BookImportStatusEnum::Failed)
        ->and($import->fresh()->failed_rows)->toBe(2)
        ->and($import->fresh()->completed_attempts)->toBe(1)
        ->and($unstarted->fresh()->attempts)->toBe(0)->and($unstarted->fresh()->completed_attempts)->toBe(0)
        ->and($started->fresh()->completed_attempts)->toBe(1);
});

test('recovery resumes stale pending and processing imports only once and ignores ready and live leases', function () {
    $stale = processingImport(['status' => BookImportStatusEnum::Pending, 'updated_at' => now()->subHour()]);
    $processing = processingImport(['status' => BookImportStatusEnum::Processing, 'lease_expires_at' => now()->subSecond()]);
    $row = processingRow($processing, [], [
        'status' => BookImportRowStatusEnum::Processing, 'attempts' => 1,
        'attempt_mode' => BookImportModeEnum::IsbnCheck, 'processing_requested_at' => now()->subHour(),
    ]);
    $ready = processingImport(['status' => BookImportStatusEnum::Ready]);
    DB::table('book_imports')->whereIn('id', [$stale->id, $ready->id])->update(['updated_at' => now()->subHour()]);
    processingImport(['status' => BookImportStatusEnum::Processing, 'lease_expires_at' => now()->addMinute()]);
    $recent = processingImport(['status' => BookImportStatusEnum::Processing]);
    DB::table('book_imports')->where('id', $recent->id)->update(['created_at' => now()->subDay()]);
    $this->artisan('book-imports:recover')->expectsOutput('Recovered 2 import(s).')->assertSuccessful();
    $this->artisan('book-imports:recover')->expectsOutput('Recovered 0 import(s).')->assertSuccessful();
    Queue::assertPushed(PrepareBookImportJob::class, 1);
    Queue::assertPushed(ProcessBookImportJob::class, 1);
    expect($row->fresh()->status)->toBe(BookImportRowStatusEnum::Processing)
        ->and($processing->fresh()->processing_token)->toBeNull();
});

test('ISBN missing invalid not found and lookup failure never silently import Excel data', function (string $isbn, ?IsbnLookupStatusEnum $lookup, BookImportFailureEnum $failure) {
    if ($lookup !== null) {
        $service = Mockery::mock(IsbnMetadataService::class);
        $service->shouldReceive('lookupForImport')->once()->andReturn(new IsbnLookupResultDTO(
            $lookup, null, ['provider' => 'nbs', 'status' => $lookup->value, 'reason' => 'test'],
        ));
        app()->instance(IsbnMetadataService::class, $service);
    }

    $import = processingImport();
    $row = processingRow($import, ['isbn' => $isbn]);
    processSelected($import, [$row]);
    expect($row->fresh()->failure_code)->toBe($failure)
        ->and($row->fresh()->status)->toBe(BookImportRowStatusEnum::Failed)
        ->and(Book::count())->toBe(0)->and(BookCopy::count())->toBe(0)
        ->and($import->fresh()->failed_rows)->toBe(1)
        ->and($import->fresh()->completed_attempts)->toBe(1);
    Http::assertNothingSent();
})->with([
    ['', null, BookImportFailureEnum::MissingIsbn],
    ['123', null, BookImportFailureEnum::InvalidIsbn],
    ['9788681131213', IsbnLookupStatusEnum::NotFound, BookImportFailureEnum::IsbnNotFound],
    ['9788681131213', IsbnLookupStatusEnum::Failed, BookImportFailureEnum::LookupFailed],
]);

test('blank and invalid COBISS fields retain Excel values including the complete author list', function () {
    processingMetadata(new BookMetadata('nbs', title: str_repeat('T', 256), authors: ['  '], pages: -1));
    $import = processingImport();
    $row = processingRow($import, ['num_of_pages' => 42]);
    processSelected($import, [$row]);
    expect($row->fresh()->book->name)->toBe('Excel title')
        ->and($row->fresh()->book->authors->pluck('name')->all())->toBe(['Excel author'])
        ->and($row->fresh()->bookCopy->num_of_pages)->toBe(42)
        ->and($row->fresh()->lookup_diagnostics['invalid_fields'])->toBe(['title', 'num_of_pages']);
});

test('actual queued ISBN processing performs HTTP outside the row transactions and restores locale', function () {
    config([
        'isbn.nbs.enabled' => true,
        'isbn.nbs.search_url' => 'https://cobiss.test/search',
        'isbn.nbs.base_url' => 'https://cobiss.test/',
    ]);
    $baseline = DB::transactionLevel();
    $levels = [];
    Http::fake(function ($request) use (&$levels) {
        $levels[] = DB::transactionLevel();

        return str_contains($request->url(), '/search')
            ? Http::response('<div data-hits="1"><table><tr class="biblioentry" data-href="bib/1"><td><a class="title">Real lookup</a></td></tr></table></div>')
            : Http::response(['isbnCard' => ['value' => '9788681131213'], 'titleCard' => ['value' => 'Real lookup']]);
    });
    $import = processingImport();
    $row = processingRow($import);
    app(BookImportStartService::class)->start($import, [$row->id]);
    $job = new ProcessBookImportJob($import->id);
    app()->setLocale('en');
    Queue::fakeExcept([ProcessBookImportJob::class]);
    dispatch($job->onConnection('sync'));
    expect($levels)->toBe([$baseline, $baseline]);
    expect($row->fresh()->status)->toBe(BookImportRowStatusEnum::Imported);
    expect($row->fresh()->book->name)->toBe('Real lookup')->and(app()->getLocale())->toBe('en');
    Http::assertSentCount(2);
});

test('unexpected row exceptions are explicit failures and cannot spend inventory numbers', function () {
    processingMetadata();
    $import = processingImport();
    $row = processingRow($import);
    BookCopy::creating(function () {
        throw new RuntimeException('simulated copy write error');
    });

    try {
        processSelected($import, [$row]);
    } finally {
        BookCopy::flushEventListeners();
    }

    expect($row->fresh()->failure_code)->toBe(BookImportFailureEnum::UnexpectedError)
        ->and(Book::count())->toBe(0)->and(Author::count())->toBe(0)
        ->and(BookInventorySequence::where('library_id', $import->library_id)->value('last_number'))->toBe(0);
});

test('job-level exceptions release the lease for retry or fail only outstanding selected rows on exhaustion', function (int $tries) {
    processingMetadata();
    config(['book_imports.tries' => $tries]);
    $import = processingImport();
    $selected = processingRow($import);
    $unselected = processingRow($import);
    app(BookImportStartService::class)->start($import, [$selected->id]);
    $processor = Mockery::mock(BookImportRowProcessor::class);
    $processor->shouldReceive('process')->once()->andThrow(new RuntimeException('worker error'));
    app()->instance(BookImportRowProcessor::class, $processor);
    expect(fn () => app()->call([new ProcessBookImportJob($import->id), 'handle']))->toThrow(RuntimeException::class, 'worker error');
    expect($import->fresh()->processing_token)->toBeNull()
        ->and($selected->fresh()->attempts)->toBe(0)
        ->and($selected->fresh()->completed_attempts)->toBe(0)
        ->and($unselected->fresh()->status)->toBe(BookImportRowStatusEnum::Pending);

    if ($tries === 1) {
        expect($import->fresh()->status)->toBe(BookImportStatusEnum::Failed)
            ->and($selected->fresh()->failure_code)->toBe(BookImportFailureEnum::JobFailed);
    } else {
        expect($import->fresh()->status)->toBe(BookImportStatusEnum::Processing)
            ->and($selected->fresh()->status)->toBe(BookImportRowStatusEnum::Pending);
        app()->forgetInstance(BookImportRowProcessor::class);
        app()->call([new ProcessBookImportJob($import->id), 'handle']);
        expect($selected->fresh()->status)->toBe(BookImportRowStatusEnum::Imported);
    }
})->with([1, 3]);

test('ten thousand rows finish in bounded batches and reuse a confirmed COBISS not-found result', function () {
    config([
        'isbn.nbs.enabled' => true,
        'isbn.nbs.search_url' => 'https://bulk-cobiss.test/search',
        'isbn.nbs.base_url' => 'https://bulk-cobiss.test/',
    ]);
    Http::fake(['bulk-cobiss.test/*' => Http::response('<div data-hits="0"></div>')]);
    DB::connection()->disableQueryLog();
    $import = processingImport();

    for ($chunk = 0; $chunk < 20; $chunk++) {
        $rows = [];

        for ($offset = 0; $offset < 500; $offset++) {
            $rows[] = [
                'book_import_id' => $import->id,
                'row_number' => 15 + $chunk * 500 + $offset,
                'raw_payload' => json_encode(['isbn' => '9788681131213', 'title' => 'Excel title']),
                'isbn' => '9788681131213', 'title' => 'Excel title',
                'created_at' => now(), 'updated_at' => now(),
            ];
        }

        DB::table('book_import_rows')->insert($rows);
    }

    app(BookImportStartService::class)->start($import, $import->rows()->pluck('id')->all());
    $place = $import->library->place;
    // Real workers commit between jobs; one outer test transaction accumulates thousands of savepoints.
    DB::commit();

    try {
        memory_reset_peak_usage();
        $baseline = memory_get_usage(true);

        for ($batch = 0; $batch < 500; $batch++) {
            app()->call([new ProcessBookImportJob($import->id), 'handle']);
        }

        expect($import->fresh()->status)->toBe(BookImportStatusEnum::CompletedWithErrors)
            ->and($import->fresh()->processed_rows)->toBe(10000)
            ->and($import->fresh()->failed_rows)->toBe(10000)
            ->and($import->fresh()->completed_attempts)->toBe(10000)
            ->and($import->rows()->where('failure_code', BookImportFailureEnum::IsbnNotFound)->count())->toBe(10000)
            ->and($import->rows()->where('attempts', 1)->where('completed_attempts', 1)->count())->toBe(10000)
            ->and(memory_get_peak_usage(true) - $baseline)->toBeLessThan(64 * 1024 * 1024)
            ->and(BookCopy::count())->toBe(0);
        Queue::assertPushed(ProcessBookImportJob::class, 500);
        Http::assertSentCount(1);
    } finally {
        DB::table('libraries')->where('id', $import->library_id)->delete();
        DB::table('places')->where('id', $place->id)->delete();
        DB::table('regions')->where('id', $place->region_id)->delete();
        DB::beginTransaction();
    }
});

test('a database barcode collision is classified precisely and rolls back inventory allocation', function () {
    processingMetadata();
    $import = processingImport();
    $book = Book::factory()->create(['library_id' => $import->library_id]);
    $copy = BookCopy::factory()->create([
        'library_id' => $import->library_id, 'book_id' => $book->id, 'order_number' => '9',
    ]);
    $row = processingRow($import);
    BookCopy::creating(function (BookCopy $newCopy) use ($copy) {
        $newCopy->barcode = $copy->barcode;
    });

    try {
        processSelected($import, [$row]);
    } finally {
        BookCopy::flushEventListeners();
    }

    expect($row->fresh()->failure_code)->toBe(BookImportFailureEnum::InventoryCollision)
        ->and(BookCopy::count())->toBe(1)->and(Book::count())->toBe(1)->and(Author::count())->toBe(0)
        ->and(BookInventorySequence::where('library_id', $import->library_id)->value('last_number'))->toBe(0);
});
