<?php

namespace App\Http\Controllers;

use App\DTOs\Queries\BookImportListQueryDTO;
use App\DTOs\Queries\SortingDTO;
use App\Enums\BookImportFailureEnum;
use App\Exceptions\BookImportFileException;
use App\Http\Requests\StoreBookImportRequest;
use App\Http\Resources\BookImportResource;
use App\Jobs\PrepareBookImportJob;
use App\Models\BookImport;
use App\Services\ActiveLibraryService;
use App\Services\AuthorizationService;
use App\Services\BookImports\BookImportMapper;
use App\Services\DatabaseExceptionService;
use App\Services\ExcelImports\ExcelImportReader;
use App\Services\Queries\BookImportQueryService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
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
}
