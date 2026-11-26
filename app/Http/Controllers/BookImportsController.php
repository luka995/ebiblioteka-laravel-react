<?php

namespace App\Http\Controllers;

use App\DTOs\Queries\BookImportListQueryDTO;
use App\DTOs\Queries\BookImportRowListQueryDTO;
use App\DTOs\Queries\SortingDTO;
use App\Enums\BookImportFailureEnum;
use App\Enums\BookImportModeEnum;
use App\Enums\BookImportRowStatusEnum;
use App\Exceptions\BookImportFileException;
use App\Http\Requests\RetryBookImportRequest;
use App\Http\Requests\StartBookImportRequest;
use App\Http\Requests\StoreBookImportRequest;
use App\Http\Requests\UpdateBookImportRowRequest;
use App\Http\Resources\BookImportResource;
use App\Http\Resources\BookImportRowResource;
use App\Jobs\PrepareBookImportJob;
use App\Models\BookImport;
use App\Models\BookImportRow;
use App\Services\ActiveLibraryService;
use App\Services\AuthorizationService;
use App\Services\BookImports\BookImportMapper;
use App\Services\BookImports\BookImportRetryService;
use App\Services\BookImports\BookImportRowEditService;
use App\Services\BookImports\BookImportStartService;
use App\Services\DatabaseExceptionService;
use App\Services\ExcelImports\ExcelImportReader;
use App\Services\Queries\BookImportQueryService;
use App\Services\Queries\BookImportRowQueryService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use OpenSpout\Common\Exception\OpenSpoutException;
use RuntimeException;
use Throwable;

class BookImportsController extends Controller
{
    public function index(
        Request $request,
        ActiveLibraryService $activeLibrary,
        AuthorizationService $auth,
        BookImportQueryService $queryService,
    ): AnonymousResourceCollection {
        $library = $activeLibrary->requireActiveLibrary($request->user());
        $data = Validator::make($request->query(), [
            'sort' => ['nullable', 'string', 'max:100'],
            'direction' => ['nullable', 'string', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:200'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ])->validate();
        $dto = new BookImportListQueryDTO(
            new SortingDTO($data['sort'] ?? null, $data['direction'] ?? null),
            (int) ($data['per_page'] ?? 25),
        );

        return BookImportResource::collection(
            $queryService->build($dto, $library)->paginate($dto->perPage)->withQueryString()
        )->additional(['permissions' => $auth->collectionPermissions($request->user(), BookImport::class)]);
    }

    public function store(
        StoreBookImportRequest $request,
        ActiveLibraryService $activeLibrary,
        ExcelImportReader $reader,
        BookImportMapper $mapper,
        DatabaseExceptionService $databaseErrors,
    ): JsonResponse {
        $library = $activeLibrary->requireActiveLibrary($request->user());
        $file = $request->file('file');

        try {
            $header = $reader->header($file->getPathname(), $mapper, (int) config('book_imports.header_scan_rows'))
                ?? throw new BookImportFileException(BookImportFailureEnum::MissingColumns);
        } catch (BookImportFileException|OpenSpoutException $e) {
            throw ValidationException::withMessages([
                'file' => $e instanceof BookImportFileException ? $e->getMessage() : BookImportFailureEnum::FileUnreadable->getLabel(),
            ]);
        }

        $disk = Storage::disk(config('book_imports.disk'));
        $path = $file->store((string) $library->id, config('book_imports.disk'));

        if ($path === false) {
            throw new RuntimeException('Unable to store the book import file.');
        }

        try {
            $import = DB::transaction(fn () => BookImport::create([
                'library_id' => $library->id,
                'user_id' => $request->user()->id,
                'original_filename' => $file->getClientOriginalName(),
                'file_path' => $path,
                'file_size' => $file->getSize(),
                'sheet_name' => $header->sheetName,
                'headers' => $header->headers,
                'column_map' => $header->columnMap,
                'has_inventory_column' => $header->hasColumn('inventory_number'),
            ]));
        } catch (Throwable $e) {
            if (! $disk->delete($path)) {
                Log::channel(config('book_imports.log_channel'))->error('book_import.upload_cleanup_failed', ['path' => $path]);
            }

            if ($e instanceof QueryException && $databaseErrors->isUniqueViolation($e)
                && str_contains($e->errorInfo[2] ?? '', 'unique constraint "book_imports_one_active_per_library"')) {
                abort(409, __('book_imports.errors.active_import'));
            }

            throw $e;
        }

        PrepareBookImportJob::dispatch($import->id)->afterCommit();

        return (new BookImportResource($import->refresh()->load(['library', 'user'])))
            ->response()->setStatusCode(201);
    }

    public function show(
        Request $request,
        BookImport $bookImport,
        ActiveLibraryService $activeLibrary,
    ): BookImportResource {
        $library = $activeLibrary->requireActiveLibrary($request->user());
        abort_unless($bookImport->library_id === $library->id, 404);

        return new BookImportResource($bookImport->load(['library', 'user']));
    }

    public function rows(
        Request $request,
        BookImport $bookImport,
        ActiveLibraryService $activeLibrary,
        BookImportRowQueryService $queryService,
    ): AnonymousResourceCollection {
        $this->ensureActiveLibraryScope($request, $bookImport, $activeLibrary);
        $data = Validator::make($request->query(), [
            'status' => ['nullable', Rule::enum(BookImportRowStatusEnum::class)],
            'failure_code' => ['nullable', Rule::enum(BookImportFailureEnum::class)],
            'attempt_mode' => ['nullable', Rule::enum(BookImportModeEnum::class)],
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:200'],
            'sort' => ['nullable', 'string', 'max:100'],
            'direction' => ['nullable', 'string', 'max:100'],
        ])->validate();

        $dto = new BookImportRowListQueryDTO(
            new SortingDTO($data['sort'] ?? null, $data['direction'] ?? null),
            isset($data['status']) ? BookImportRowStatusEnum::from($data['status']) : null,
            isset($data['failure_code']) ? BookImportFailureEnum::from($data['failure_code']) : null,
            isset($data['attempt_mode']) ? BookImportModeEnum::from($data['attempt_mode']) : null,
            $data['search'] ?? null,
            (int) ($data['per_page'] ?? 200),
        );
        $query = $queryService->build($dto, $bookImport);
        $counts = $queryService->matchingCounts($query);

        return BookImportRowResource::collection(
            $query->paginate($dto->perPage)->withQueryString()
        )->additional(['meta' => $counts]);
    }

    public function start(
        StartBookImportRequest $request,
        BookImport $bookImport,
        ActiveLibraryService $activeLibrary,
        BookImportStartService $starter,
    ): JsonResponse {
        $this->ensureActiveLibraryScope($request, $bookImport, $activeLibrary);
        $data = $request->validated();
        $mode = BookImportModeEnum::from($data['mode']);
        $selected = isset($data['all_matching'])
            ? $starter->startMatching($bookImport, $mode, $this->rowFilters($data['filters']))
            : $starter->start($bookImport, $data['row_ids'], $mode);

        return response()->json(['data' => ['id' => $bookImport->id, 'selected_rows' => $selected]], 202);
    }

    public function retry(
        RetryBookImportRequest $request,
        BookImport $bookImport,
        ActiveLibraryService $activeLibrary,
        BookImportRetryService $retry,
    ): JsonResponse {
        $this->ensureActiveLibraryScope($request, $bookImport, $activeLibrary);
        $data = $request->validated();
        $mode = BookImportModeEnum::from($data['mode']);
        $selected = isset($data['all_matching'])
            ? $retry->retryMatching($bookImport, $mode, $this->rowFilters($data['filters']))
            : $retry->retry($bookImport, $mode, $data['row_ids']);

        return response()->json(['data' => ['id' => $bookImport->id, 'selected_rows' => $selected]], 202);
    }

    public function updateRow(
        UpdateBookImportRowRequest $request,
        BookImport $bookImport,
        BookImportRow $row,
        ActiveLibraryService $activeLibrary,
        BookImportRowEditService $editor,
    ): BookImportRowResource {
        $this->ensureActiveLibraryScope($request, $bookImport, $activeLibrary);
        abort_unless($row->book_import_id === $bookImport->id, 404);
        $data = $request->validated();

        return new BookImportRowResource(
            $editor->update($bookImport, $row->id, (int) $data['input_version'], $data['changes'])
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function rowFilters(array $filters): BookImportRowListQueryDTO
    {
        return new BookImportRowListQueryDTO(
            new SortingDTO,
            isset($filters['status']) ? BookImportRowStatusEnum::from($filters['status']) : null,
            isset($filters['failure_code']) ? BookImportFailureEnum::from($filters['failure_code']) : null,
            isset($filters['attempt_mode']) ? BookImportModeEnum::from($filters['attempt_mode']) : null,
            $filters['search'] ?? null,
            200,
        );
    }

    private function ensureActiveLibraryScope(
        Request $request,
        BookImport $bookImport,
        ActiveLibraryService $activeLibrary,
    ): void {
        $library = $activeLibrary->requireActiveLibrary($request->user());
        abort_unless($bookImport->library_id === $library->id, 404);
    }
}
