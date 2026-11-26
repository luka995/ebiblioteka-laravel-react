<?php

use App\DTOs\ExcelImportHeaderMapDTO;
use App\DTOs\Queries\BookImportListQueryDTO;
use App\DTOs\Queries\SortingDTO;
use App\Enums\BookImportFailureEnum;
use App\Enums\BookImportRowStatusEnum;
use App\Enums\BookImportStatusEnum;
use App\Enums\LibraryRole;
use App\Jobs\PrepareBookImportJob;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookImport;
use App\Models\BookInventorySequence;
use App\Models\Library;
use App\Models\User;
use App\Services\BookImports\BookImportCounterService;
use App\Services\BookImports\BookImportMapper;
use App\Services\ExcelImports\ExcelCellNormalizer;
use App\Services\ExcelImports\ExcelImportMapper;
use App\Services\ExcelImports\ExcelImportReader;
use App\Services\InventoryNumberService;
use App\Services\Queries\BookImportQueryService;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Exception\OpenSpoutException;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;

function importXlsx(iterable $rows, array $secondSheet = [], string $name = 'books.xlsx'): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'book-import-');
    test()->beforeApplicationDestroyed(fn () => unlink($path));
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->getCurrentSheet()->setName('Uvoz');

    foreach ($rows as $values) {
        $writer->addRow(Row::fromValues($values));
    }

    if ($secondSheet !== []) {
        $writer->addNewSheetAndMakeItCurrent()->setName('Primeri');

        foreach ($secondSheet as $values) {
            $writer->addRow(Row::fromValues($values));
        }
    }

    $writer->close();

    return new UploadedFile($path, $name, null, null, true);
}

function importAdmin(Library $library): User
{
    $user = User::factory()->create();
    $user->libraries()->attach($library, ['role' => LibraryRole::LibraryAdmin->value]);

    return $user;
}

function stagedBookImport(UploadedFile $file, ?Library $library = null, array $attributes = []): BookImport
{
    $library ??= Library::factory()->create();
    $path = $file->store((string) $library->id, 'imports');

    return BookImport::create([
        'library_id' => $library->id,
        'original_filename' => $file->getClientOriginalName(),
        'file_path' => $path,
        'file_size' => $file->getSize(),
        ...$attributes,
    ]);
}

function prepareImport(BookImport $import): void
{
    app()->call([new PrepareBookImportJob($import->id), 'handle']);
}

beforeEach(function () {
    app()->setLocale('sr-Latn');
    Storage::fake('imports');
    Queue::fake();
    Http::preventStrayRequests();
    config(['book_imports.prepare_chunk_size' => 2]);
});

test('upload validates only the header, saves a private pending import and queues preparation', function () {
    $library = Library::factory()->create();
    $actor = importAdmin($library);
    $response = $this->actingAs($actor)->postJson('/api/v1/book-imports', [
        'file' => importXlsx([['Uputstvo: naslov knjige je obavezan'], [], ['ISBN', 'Naslov'], ['bad-isbn', 'Knjiga']]),
        'library_id' => Library::factory()->create()->id,
    ])->assertCreated()
        ->assertJsonPath('data.library_id', $library->id)
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.total_rows', 0)
        ->assertJsonPath('data.column_map.title', 1)
        ->assertJsonPath('data.has_inventory_column', false)
        ->assertJsonPath('data.can.view', true)
        ->assertJsonMissingPath('data.file_path')
        ->assertJsonMissingPath('data.processing_token');

    $import = BookImport::findOrFail($response->json('data.id'));
    Storage::disk('imports')->assertExists($import->file_path);
    expect($import->user_id)->toBe($actor->id)
        ->and($import->rows()->count())->toBe(0);
    Queue::assertPushed(PrepareBookImportJob::class, fn ($job) => $job->bookImportId === $import->id
        && $job->queue === 'imports' && $job->afterCommit === true);
    Http::assertNothingSent();
});

test('the supplied template supports all columns and removal of the complete inventory column', function () {
    $service = app(ExcelImportReader::class);
    $mapper = app(BookImportMapper::class);
    $path = base_path('docs/sablon-uvoza-knjiga.xlsx');
    $header = $service->header($path, $mapper, 20);
    expect($header->rowNumber)->toBe(14)
        ->and($header->columnMap)->toHaveCount(23)
        ->and($header->hasColumn('inventory_number'))->toBeTrue();

    $options = new Options;
    $options->SHOULD_PRESERVE_EMPTY_ROWS = true;
    $reader = new Reader($options);
    $reader->open($path);

    try {
        $rows = (function () use ($reader) {
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    yield array_slice($row->toArray(), 1);
                }

                break;
            }
        })();
        $file = importXlsx($rows);
    } finally {
        $reader->close();
    }

    $withoutInventory = $service->header($file->getPathname(), $mapper, 20);
    expect($withoutInventory->rowNumber)->toBe(14)
        ->and($withoutInventory->columnMap)->toHaveCount(22)
        ->and($withoutInventory->hasColumn('inventory_number'))->toBeFalse();
});

test('invalid headers are rejected before creating records or storing files', function (array $headers) {
    $actor = importAdmin(Library::factory()->create());

    $this->actingAs($actor)->postJson('/api/v1/book-imports', [
        'file' => importXlsx([$headers, ['Knjiga']]),
    ])->assertUnprocessable()->assertJsonValidationErrors('file');

    expect(BookImport::count())->toBe(0)
        ->and(Storage::disk('imports')->allFiles())->toBe([]);
    Queue::assertNothingPushed();
})->with([
    'missing required column' => [['Autor', 'Cena']],
    'duplicate transliterated title' => [['Naslov', 'Наслов']],
    'duplicate inventory alias' => [['Title', 'Inv. br.', 'Inventarni broj']],
]);

test('header scan is limited to the first twenty physical rows of the first sheet', function () {
    $reader = app(ExcelImportReader::class);
    $file = importXlsx([...array_fill(0, 20, []), ['Naslov']], [['Naslov'], ['Primer']]);

    expect($reader->header($file->getPathname(), app(BookImportMapper::class), 20))->toBeNull();
    $this->actingAs(importAdmin(Library::factory()->create()))
        ->postJson('/api/v1/book-imports', ['file' => $file])
        ->assertUnprocessable()
        ->assertJsonPath('errors.file.0', BookImportFailureEnum::MissingColumns->getLabel());
});

test('the same reader accepts an unrelated mapper without book headers or book row DTOs', function () {
    $mapper = new class(app(ExcelCellNormalizer::class)) implements ExcelImportMapper
    {
        public function __construct(private readonly ExcelCellNormalizer $cells) {}

        public function mapHeader(array $headers, int $rowNumber, string $sheetName): ?ExcelImportHeaderMapDTO
        {
            if ($headers !== ['External reference', 'Label']) {
                return null;
            }

            return new ExcelImportHeaderMapDTO($sheetName, $rowNumber, $headers, ['reference' => 0, 'label' => 1]);
        }

        public function mapRow(array $cells, int $rowNumber, ExcelImportHeaderMapDTO $header): ?stdClass
        {
            $reference = $this->cells->text(($cells[$header->columnMap['reference']] ?? null)?->getValue());

            return $reference === null ? null : (object) [
                'reference' => $reference,
                'label' => $this->cells->text(($cells[$header->columnMap['label']] ?? null)?->getValue()),
                'row_number' => $rowNumber,
            ];
        }
    };
    $file = importXlsx([
        ['Uputstvo'], [], ['External reference', 'Label'], ['001', '  Prvi   zapis '], [], [1234567890123, 'Drugi'],
    ], [['External reference', 'Label'], ['999', 'Primer se ne uvozi']]);
    $reader = app(ExcelImportReader::class);
    config(['book_imports.header_scan_rows' => 1]);

    expect($reader->header($file->getPathname(), $mapper, 2))->toBeNull();
    $header = $reader->header($file->getPathname(), $mapper, 3);
    expect($header->rowNumber)->toBe(3)
        ->and($header->sheetName)->toBe('Uvoz');
    $rows = iterator_to_array($reader->rows($file->getPathname(), $header, $mapper));

    expect(array_keys($rows))->toBe([4, 6])
        ->and($rows[4])->toBeInstanceOf(stdClass::class)
        ->and((array) $rows[4])->toBe(['reference' => '001', 'label' => 'Prvi zapis', 'row_number' => 4])
        ->and($rows[6]->reference)->toBe('1234567890123');
});

test('generic reader propagates file and mapper errors without module specific wrapping', function () {
    $mapper = new class implements ExcelImportMapper
    {
        public function mapHeader(array $headers, int $rowNumber, string $sheetName): ?ExcelImportHeaderMapDTO
        {
            return new ExcelImportHeaderMapDTO($sheetName, $rowNumber, $headers, ['reference' => 0]);
        }

        public function mapRow(array $cells, int $rowNumber, ExcelImportHeaderMapDTO $header): ?object
        {
            throw new DomainException('Mapping rejected this row.');
        }
    };
    $reader = app(ExcelImportReader::class);
    $invalid = UploadedFile::fake()->createWithContent('invalid.xlsx', 'invalid workbook');
    expect(fn () => $reader->header($invalid->getPathname(), $mapper, 20))->toThrow(OpenSpoutException::class);

    $file = importXlsx([['Reference'], ['1']]);
    $header = $reader->header($file->getPathname(), $mapper, 20);
    expect(fn () => iterator_to_array($reader->rows($file->getPathname(), $header, $mapper)))
        ->toThrow(DomainException::class, 'Mapping rejected this row.');
});

test('upload rejects wrong extension, invalid contents, oversized files and array inputs', function () {
    $this->actingAs(importAdmin(Library::factory()->create()));

    foreach ([
        importXlsx([['Naslov']], name: 'books.csv'),
        importXlsx([['Naslov']], name: str_repeat('a', 256).'.xlsx'),
        UploadedFile::fake()->createWithContent('books.xlsx', 'not an xlsx archive'),
        [importXlsx([['Naslov']])],
    ] as $file) {
        $this->postJson('/api/v1/book-imports', ['file' => $file])
            ->assertUnprocessable()->assertJsonValidationErrors('file');
    }

    config(['book_imports.max_file_size_kb' => 1]);
    $this->postJson('/api/v1/book-imports', ['file' => importXlsx([['Naslov']])])
        ->assertUnprocessable()->assertJsonValidationErrors('file');
    expect(BookImport::count())->toBe(0)
        ->and(Storage::disk('imports')->allFiles())->toBe([]);
});

test('an active database import returns conflict and cleans only the rejected upload', function () {
    $library = Library::factory()->create();
    $this->actingAs(importAdmin($library));
    $this->postJson('/api/v1/book-imports', ['file' => importXlsx([['Naslov'], ['Prva']])])->assertCreated();
    $first = BookImport::sole();

    $this->postJson('/api/v1/book-imports', ['file' => importXlsx([['Naslov'], ['Druga']])])->assertConflict();

    expect(BookImport::count())->toBe(1)
        ->and(Storage::disk('imports')->allFiles())->toBe([$first->file_path]);
    Queue::assertPushed(PrepareBookImportJob::class, 1);
});

test('unrelated database constraints are not misreported as active import conflicts', function () {
    $actor = importAdmin(Library::factory()->create());
    BookImport::creating(function () {
        $previous = new PDOException('duplicate key value violates unique constraint "book_imports_pkey"', 23505);
        $previous->errorInfo = ['23505', 7, $previous->getMessage()];

        throw new QueryException('pgsql', 'insert into book_imports (original_filename) values (?)', [
            'book_imports_one_active_per_library.xlsx',
        ], $previous);
    });

    $this->actingAs($actor)->postJson('/api/v1/book-imports', [
        'file' => importXlsx([['Naslov']], name: 'book_imports_one_active_per_library.xlsx'),
    ])->assertUnprocessable();
    expect(BookImport::count())->toBe(0)
        ->and(Storage::disk('imports')->allFiles())->toBe([]);
    Queue::assertNothingPushed();
});

test('guests and members cannot upload or inspect imports', function () {
    $library = Library::factory()->create();
    $import = stagedBookImport(importXlsx([['Naslov']]), $library);
    $this->getJson('/api/v1/book-imports')->assertUnauthorized();
    $this->getJson('/api/v1/book-imports/'.$import->id)->assertUnauthorized();
    $this->postJson('/api/v1/book-imports')->assertUnauthorized();

    $member = User::factory()->create();
    $member->libraries()->attach($library, ['role' => LibraryRole::User->value]);
    $this->actingAs($member)->getJson('/api/v1/book-imports')->assertForbidden();
    $this->getJson('/api/v1/book-imports/'.$import->id)->assertForbidden();
    $this->postJson('/api/v1/book-imports')->assertForbidden();
});

test('import API requires active library and scopes history and detail to that library', function () {
    $library = Library::factory()->create();
    $other = Library::factory()->create();
    $import = stagedBookImport(importXlsx([['Naslov']]), $library);
    $foreign = stagedBookImport(importXlsx([['Naslov']]), $other);
    $actor = importAdmin($library);
    $actor->libraries()->attach($other, ['role' => LibraryRole::LibraryAdmin->value]);
    $this->actingAs($actor)->getJson('/api/v1/book-imports')->assertUnprocessable();
    $this->postJson('/api/v1/book-imports', ['file' => importXlsx([['Naslov']])])->assertUnprocessable();
    $this->withSession(['active_library_id' => $library->id])
        ->getJson('/api/v1/book-imports')->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $import->id);
    $this->getJson('/api/v1/book-imports/'.$import->id)->assertOk();
    $this->getJson('/api/v1/book-imports/'.$foreign->id)->assertNotFound();

    $this->actingAs(User::factory()->superAdmin()->create())
        ->withSession(['active_library_id' => null])->getJson('/api/v1/book-imports')->assertUnprocessable();
});

test('import query DTO applies library scope, sorting and paginated API uses per page', function () {
    $library = Library::factory()->create();
    $file = importXlsx([['Naslov']]);
    $imports = collect([1, 9, 3])->map(fn ($total) => stagedBookImport($file, $library, [
        'status' => BookImportStatusEnum::Completed,
        'total_rows' => $total,
        'created_at' => now()->startOfDay(),
    ]));
    stagedBookImport($file, attributes: ['total_rows' => 99]);
    $dto = new BookImportListQueryDTO(new SortingDTO('total_rows', 'desc'), 2);
    $query = app(BookImportQueryService::class)->build($dto, $library);
    expect($query->pluck('total_rows')->all())->toBe([9, 3, 1]);
    $this->actingAs(importAdmin($library))
        ->getJson('/api/v1/book-imports?sort=total_rows&direction=desc&per_page=2')
        ->assertOk()->assertJsonPath('meta.per_page', 2)->assertJsonPath('meta.total', 3)
        ->assertJsonCount(2, 'data')->assertJsonPath('data.0.total_rows', 9);

    foreach (['', 'not_allowed'] as $sort) {
        $default = new BookImportListQueryDTO(new SortingDTO($sort, 'invalid'));
        expect(app(BookImportQueryService::class)->build($default, $library)->pluck('id')->all())
            ->toBe($imports->reverse()->pluck('id')->all());
    }

    $this->getJson('/api/v1/book-imports?per_page=201')->assertUnprocessable();
    $this->getJson('/api/v1/book-imports?sort[]=title')->assertUnprocessable();
});

test('preparation streams rows, preserves physical row numbers and never creates library stock', function () {
    $library = Library::factory()->create();
    $sequence = $library->inventorySequence;
    $sequence->update(['last_number' => 17]);
    $import = stagedBookImport(importXlsx([
        ['Naslov je obavezan; ostale kolone su opcione.'],
        [],
        ['ISBN', 'Наслов', 'Autori', 'Cena', 'Redni broj', 'Broj strana', 'Nepoznata kolona'],
        [9788610000000, "  Na\u{00a0}Drini   ćuprija ", 'Andrić, Ivo; Drugi autor | Treći autor', '1.234,50', -2, 100, 'Ignoriši'],
        [],
        [null, 'Bez ISBN-a', null, 'nepoznata cena', null, 0],
        [null, null, null, null, null, null, 'Samo nepoznata kolona'],
        ['neispravan ISBN', 'Treća knjiga', null, '2,5', '00012', '00005'],
    ], [['Naslov'], ['Ne uvozi primer']]), $library);

    prepareImport($import);
    $import->refresh();
    $rows = $import->rows()->orderBy('row_number')->get();

    expect($import->status)->toBe(BookImportStatusEnum::Ready)
        ->and($import->total_rows)->toBe(3)
        ->and($import->processed_rows)->toBe(0)
        ->and($import->completed_attempts)->toBe(0)
        ->and($import->processing_token)->toBeNull()
        ->and($import->lease_expires_at)->toBeNull()
        ->and($import->has_inventory_column)->toBeFalse()
        ->and($rows->pluck('row_number')->all())->toBe([4, 6, 8])
        ->and($rows[0]->raw_payload)->toEqual([
            'isbn' => '9788610000000',
            'title' => 'Na Drini ćuprija',
            'authors' => ['Andrić, Ivo', 'Drugi autor', 'Treći autor'],
            'price' => '1234.50',
            'seq_number' => -2,
            'num_of_pages' => 100,
        ])
        ->and($rows[1]->raw_payload['price'])->toBe('0.00')
        ->and($rows[2]->status)->toBe(BookImportRowStatusEnum::Pending)
        ->and($rows[2]->raw_payload['seq_number'])->toBe(12)
        ->and($rows[2]->raw_payload['num_of_pages'])->toBe(5)
        ->and($rows->sum('attempts'))->toBe(0)
        ->and($rows->sum('completed_attempts'))->toBe(0)
        ->and($sequence->fresh()->last_number)->toBe(17)
        ->and(Book::count())->toBe(0)
        ->and(BookCopy::withTrashed()->count())->toBe(0)
        ->and($rows->pluck('resolved_payload')->filter())->toBeEmpty();
    Queue::assertNothingPushed();
    Http::assertNothingSent();

    $ids = $rows->pluck('id')->all();
    prepareImport($import);
    expect($import->rows()->orderBy('row_number')->pluck('id')->all())->toBe($ids);
});

test('supplied inventory is preserved and blank, invalid and normalized duplicate numbers fail per row', function () {
    $import = stagedBookImport(importXlsx([
        ['Inventarni broj', 'Naslov'],
        ['00500', 'Prva'],
        [null, 'Prazan broj'],
        ['ABC', 'Neispravan'],
        ['500', 'Isti normalizovani broj'],
        ['3', 'Brojevi ne moraju biti rastući'],
        ['1000000000000', 'Preveliki broj'],
    ]));
    $sequences = BookInventorySequence::pluck('last_number', 'library_id')->all();
    prepareImport($import);
    $rows = $import->rows()->orderBy('row_number')->get();

    expect($import->fresh()->status)->toBe(BookImportStatusEnum::Ready)
        ->and($import->fresh()->has_inventory_column)->toBeTrue()
        ->and($rows->pluck('failure_code')->all())->toBe([
            null,
            BookImportFailureEnum::MissingInventoryNumber,
            BookImportFailureEnum::InvalidInventoryNumber,
            BookImportFailureEnum::DuplicateInventoryNumber,
            null,
            BookImportFailureEnum::InvalidInventoryNumber,
        ])
        ->and($rows[0]->inventory_number)->toBe('00500')
        ->and($rows[0]->raw_payload['inventory_number'])->toBe('00500')
        ->and($import->fresh()->failed_rows)->toBe(4)
        ->and($import->fresh()->preparation_failed_rows)->toBe(4)
        ->and($import->fresh()->pending_rows)->toBe(2)
        ->and($import->fresh()->processed_rows)->toBe(0)
        ->and($import->fresh()->completed_attempts)->toBe(0)
        ->and(BookInventorySequence::pluck('last_number', 'library_id')->all())->toBe($sequences)
        ->and(BookCopy::withTrashed()->count())->toBe(0);
});

test('existing and archived inventory numbers are checked within the library without modifying copies', function () {
    $library = Library::factory()->create();
    $book = Book::factory()->for($library)->create();
    $copy = BookCopy::factory()->for($library)->for($book)->create(['order_number' => '123']);
    $copy->delete();
    BookCopy::factory()->create(['order_number' => '789']);
    $import = stagedBookImport(importXlsx([
        ['Inv. br.', 'Naslov'], ['00123', 'Postoji u arhivi'], ['789', 'Druga biblioteka'],
    ]), $library);
    $sequences = BookInventorySequence::pluck('last_number', 'library_id')->all();

    prepareImport($import);
    $rows = $import->rows()->orderBy('row_number')->get();
    expect($rows[0]->failure_code)->toBe(BookImportFailureEnum::DuplicateInventoryNumber)
        ->and($rows[1]->status)->toBe(BookImportRowStatusEnum::Pending)
        ->and(BookCopy::withTrashed()->count())->toBe(2)
        ->and($copy->fresh()->trashed())->toBeTrue()
        ->and(BookInventorySequence::pluck('last_number', 'library_id')->all())->toBe($sequences);
});

test('invalid optional values are retained as failed preview rows and formulas are not evaluated', function () {
    $longTitle = str_repeat('a', 256);
    $import = stagedBookImport(importXlsx([
        ['Naslov', 'Broj strana', 'Redni broj', 'URL korice'],
        ['Formula', '=1+2'],
        [true],
        [$longTitle],
        ['Negativan broj strana', -1],
        ['Preveliki redni broj', null, '2147483648'],
        ['FTP korica', null, null, 'ftp://example.com/image.jpg'],
    ]));
    prepareImport($import);
    $rows = $import->rows()->orderBy('row_number')->get();

    expect($rows)->toHaveCount(6)
        ->and($rows->pluck('failure_code')->unique()->all())->toBe([BookImportFailureEnum::InvalidFieldValue])
        ->and($rows[0]->raw_payload['num_of_pages'])->toBe('=1+2')
        ->and($rows[0]->failure_message)->toContain('Broj strana')
        ->and($rows[2]->title)->toBeNull()
        ->and($rows[2]->raw_payload['title'])->toBe($longTitle)
        ->and($import->fresh()->failed_rows)->toBe(6);
});

test('empty first worksheet fails even when examples contain book data', function () {
    $import = stagedBookImport(importXlsx([['Naslov'], [], ['  ']], [['Naslov'], ['Primer']]));
    prepareImport($import);

    expect($import->fresh()->status)->toBe(BookImportStatusEnum::Failed)
        ->and($import->fresh()->failure_code)->toBe(BookImportFailureEnum::NoRows)
        ->and($import->rows()->count())->toBe(0);
});

test('exceeding the row limit removes partial staging chunks and releases the active library', function () {
    config(['book_imports.max_rows' => 3]);
    $import = stagedBookImport(importXlsx([['Naslov'], ['1'], ['2'], ['3'], ['4']]));
    prepareImport($import);

    expect($import->fresh()->status)->toBe(BookImportStatusEnum::Failed)
        ->and($import->fresh()->failure_code)->toBe(BookImportFailureEnum::TooManyRows)
        ->and($import->fresh()->total_rows)->toBe(0)
        ->and($import->fresh()->processing_token)->toBeNull()
        ->and($import->rows()->count())->toBe(0);
    expect(stagedBookImport(importXlsx([['Naslov']]), $import->library)->exists)->toBeTrue();
});

test('unreadable stored file becomes a localized failed import', function () {
    $import = stagedBookImport(importXlsx([['Naslov'], ['Prva']]));
    Storage::disk('imports')->delete($import->file_path);
    prepareImport($import);

    expect($import->fresh()->failure_code)->toBe(BookImportFailureEnum::FileUnreadable)
        ->and($import->fresh()->failure_message)->toBe(BookImportFailureEnum::FileUnreadable->getLabel())
        ->and($import->fresh()->status)->toBe(BookImportStatusEnum::Failed);
});

test('malformed workbook XML is rejected as a file validation error', function () {
    $file = importXlsx([['Naslov'], ['Knjiga']]);
    $zip = new ZipArchive;
    expect($zip->open($file->getPathname()))->toBeTrue();
    $zip->addFromString('xl/workbook.xml', '<workbook><broken>');
    $zip->close();

    $this->actingAs(importAdmin(Library::factory()->create()))
        ->postJson('/api/v1/book-imports', ['file' => $file])
        ->assertUnprocessable()->assertJsonValidationErrors('file');
    expect(BookImport::count())->toBe(0);
});

test('queued preparation retains upload locale and restores the worker locale', function (string $locale, string $expectedLocale) {
    $import = stagedBookImport(importXlsx([['Naslov']]));
    app()->setLocale($locale);
    $job = unserialize(serialize(new PrepareBookImportJob($import->id)));
    app()->setLocale('sr');
    app()->call([$job, 'handle']);

    expect($import->fresh()->failure_message)
        ->toBe(__('book_imports.failures.no_rows', [], $expectedLocale))
        ->and(app()->getLocale())->toBe('sr');
})->with([
    'supported upload locale' => ['en', 'en'],
    'unsupported CLI locale uses application UI default' => ['sr', 'sr-Cyrl'],
]);

test('preparation also runs through the actual queue dispatcher', function () {
    Queue::fakeExcept([PrepareBookImportJob::class]);
    $import = stagedBookImport(importXlsx([['Naslov'], ['Knjiga']]));

    PrepareBookImportJob::dispatch($import->id)->onConnection('sync');

    expect($import->fresh()->status)->toBe(BookImportStatusEnum::Ready)
        ->and($import->rows()->sole()->raw_payload['title'])->toBe('Knjiga');
});

test('duplicate execution and failed callback cannot overwrite a live preparation lease', function () {
    $token = (string) Str::uuid();
    $import = stagedBookImport(importXlsx([['Naslov'], ['Nova']]), attributes: [
        'processing_token' => $token,
        'lease_expires_at' => now()->addMinutes(5),
        'total_rows' => 1,
    ]);
    $old = $import->rows()->create(['row_number' => 2, 'raw_payload' => ['title' => 'Postojeća']]);
    prepareImport($import);
    (new PrepareBookImportJob($import->id))->failed(new RuntimeException('worker failed'));

    expect($import->fresh()->processing_token)->toBe($token)
        ->and($import->fresh()->status)->toBe(BookImportStatusEnum::Pending)
        ->and($import->rows()->sole()->id)->toBe($old->id);

    $import->update(['lease_expires_at' => now()->subSecond()]);
    prepareImport($import);
    expect($import->fresh()->status)->toBe(BookImportStatusEnum::Ready)
        ->and($import->rows()->sole()->id)->not->toBe($old->id)
        ->and($import->rows()->sole()->raw_payload['title'])->toBe('Nova')
        ->and($import->fresh()->total_rows)->toBe(1);
});

test('a stale worker cannot insert another chunk or finalize after losing its token', function () {
    $import = stagedBookImport(importXlsx([['Naslov'], ['1'], ['2'], ['3'], ['4']]));
    $real = app(ExcelImportReader::class);
    $mapper = app(BookImportMapper::class);
    $replacement = (string) Str::uuid();
    $reader = Mockery::mock(ExcelImportReader::class);
    $reader->shouldReceive('header')->once()->andReturn($real->header(Storage::disk('imports')->path($import->file_path), $mapper, 20));
    $reader->shouldReceive('rows')->once()->andReturnUsing(function ($path, $header, $mapper) use ($real, $import, $replacement) {
        $count = 0;

        foreach ($real->rows($path, $header, $mapper) as $number => $row) {
            yield $number => $row;

            if (++$count === 2) {
                $import->update(['processing_token' => $replacement, 'lease_expires_at' => now()->addMinutes(5)]);
            }
        }
    });

    (new PrepareBookImportJob($import->id))->handle(
        $reader,
        $mapper,
        app(InventoryNumberService::class),
        app(BookImportCounterService::class),
    );
    expect($import->fresh()->status)->toBe(BookImportStatusEnum::Pending)
        ->and($import->fresh()->processing_token)->toBe($replacement)
        ->and($import->rows()->count())->toBe(2)
        ->and($import->fresh()->total_rows)->toBe(2);
});

test('unexpected preparation exceptions are rethrown, retriable and terminally reported on the final attempt', function () {
    $import = stagedBookImport(importXlsx([['Naslov'], ['1']]));
    $reader = Mockery::mock(ExcelImportReader::class);
    $mapper = app(BookImportMapper::class);
    $reader->shouldReceive('header')->twice()->andThrow(new RuntimeException('unexpected failure'));
    $numbers = app(InventoryNumberService::class);
    $counters = app(BookImportCounterService::class);
    expect(fn () => (new PrepareBookImportJob($import->id))->handle($reader, $mapper, $numbers, $counters))
        ->toThrow(RuntimeException::class, 'unexpected failure');
    expect($import->fresh()->status)->toBe(BookImportStatusEnum::Pending)
        ->and($import->fresh()->processing_token)->toBeNull();

    $job = new PrepareBookImportJob($import->id);
    $job->tries = 1;
    expect(fn () => $job->handle($reader, $mapper, $numbers, $counters))->toThrow(RuntimeException::class, 'unexpected failure');
    expect($import->fresh()->status)->toBe(BookImportStatusEnum::Failed)
        ->and($import->fresh()->failure_code)->toBe(BookImportFailureEnum::JobFailed);
});

test('ten thousand rows are prepared in bounded chunks without generating inventory or metadata requests', function () {
    config(['book_imports.prepare_chunk_size' => 500]);
    $file = importXlsx((function () {
        yield ['ISBN', 'Naslov'];

        for ($i = 1; $i <= 10000; $i++) {
            yield [null, 'Knjiga '.$i];
        }
    })());
    $library = Library::factory()->create();
    $response = $this->actingAs(importAdmin($library))
        ->postJson('/api/v1/book-imports', ['file' => $file])
        ->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.total_rows', 0);
    $import = BookImport::findOrFail($response->json('data.id'));
    expect($import->rows()->count())->toBe(0);
    $sequences = BookInventorySequence::pluck('last_number', 'library_id')->all();
    $inserts = 0;
    DB::listen(function ($query) use (&$inserts) {
        if (str_starts_with($query->sql, 'insert into "book_import_rows"')) {
            $inserts++;
        }
    });
    memory_reset_peak_usage();
    $before = memory_get_usage(true);

    prepareImport($import);

    $growth = memory_get_peak_usage(true) - $before;
    expect($import->fresh()->status)->toBe(BookImportStatusEnum::Ready)
        ->and($import->fresh()->total_rows)->toBe(10000)
        ->and($import->rows()->count())->toBe(10000)
        ->and($import->rows()->where('status', 'pending')->count())->toBe(10000)
        ->and($inserts)->toBe(20)
        ->and($growth)->toBeLessThan(64 * 1024 * 1024)
        ->and(BookInventorySequence::pluck('last_number', 'library_id')->all())->toBe($sequences)
        ->and(BookCopy::withTrashed()->count())->toBe(0);
    Http::assertNothingSent();
});
