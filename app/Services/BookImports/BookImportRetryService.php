<?php

namespace App\Services\BookImports;

use App\DTOs\Queries\BookImportRowListQueryDTO;
use App\Enums\BookImportModeEnum;
use App\Enums\BookImportRowStatusEnum;
use App\Enums\BookImportStatusEnum;
use App\Jobs\ProcessBookImportJob;
use App\Models\BookImport;
use App\Services\DatabaseExceptionService;
use App\Services\Queries\BookImportRowQueryService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class BookImportRetryService
{
    public function __construct(
        private readonly BookImportRowQueryService $rows,
        private readonly BookImportCounterService $counters,
        private readonly DatabaseExceptionService $databaseErrors,
    ) {}

    /**
     * @param  list<int>  $rowIds
     */
    public function retry(BookImport $import, BookImportModeEnum $mode, array $rowIds): int
    {
        Validator::make(['row_ids' => $rowIds], [
            'row_ids' => ['required', 'array', 'list', 'min:1', 'max:'.config('book_imports.max_rows')],
            'row_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ])->validate();

        return $this->retrySelected($import, $mode, function (BookImport $lockedImport) use ($rowIds): array {
            $selected = $lockedImport->rows()->whereIn('id', $rowIds)
                ->where('status', BookImportRowStatusEnum::Failed)
                ->lockForUpdate()->pluck('id')->map(static fn ($id): int => (int) $id)->all();

            if (count($selected) !== count($rowIds)) {
                throw ValidationException::withMessages(['row_ids' => __('book_imports.errors.invalid_retry_selection')]);
            }

            return $selected;
        });
    }

    public function retryMatching(
        BookImport $import,
        BookImportModeEnum $mode,
        BookImportRowListQueryDTO $filters,
    ): int {
        return $this->retrySelected(
            $import,
            $mode,
            fn (BookImport $lockedImport): array => $this->rows->matchingIds(
                $filters,
                $lockedImport,
                BookImportRowStatusEnum::Failed->value,
            ),
        );
    }

    /**
     * @param  \Closure(BookImport): list<int>  $select
     */
    private function retrySelected(BookImport $import, BookImportModeEnum $mode, \Closure $select): int
    {
        try {
            return DB::transaction(function () use ($import, $mode, $select): int {
                $import = BookImport::lockForUpdate()->findOrFail($import->id);

                if (! in_array($import->status, [
                    BookImportStatusEnum::Ready,
                    BookImportStatusEnum::Completed,
                    BookImportStatusEnum::CompletedWithErrors,
                    BookImportStatusEnum::Failed,
                ], true)) {
                    abort(409, __('book_imports.errors.import_busy'));
                }

                $rowIds = $select($import);

                if ($rowIds === []) {
                    throw ValidationException::withMessages(['row_ids' => __('book_imports.errors.invalid_retry_selection')]);
                }

                $import->rows()->whereIn('id', $rowIds)->update([
                    'status' => BookImportRowStatusEnum::Pending,
                    'failure_stage' => null,
                    'failure_code' => null,
                    'failure_message' => null,
                    'lookup_diagnostics' => null,
                    'resolved_payload' => null,
                    'field_sources' => null,
                    'attempt_mode' => $mode,
                    'processing_requested_at' => now(),
                    'processed_at' => null,
                    'book_id' => null,
                    'book_copy_id' => null,
                ]);

                $import->update([
                    'status' => BookImportStatusEnum::Processing,
                    'finished_at' => null,
                    'failure_code' => null,
                    'failure_message' => null,
                    'processing_token' => null,
                    'lease_expires_at' => null,
                ]);
                $this->counters->update($import);
                ProcessBookImportJob::dispatch($import->id, $mode)->afterCommit();

                return count($rowIds);
            });
        } catch (QueryException $exception) {
            if ($this->databaseErrors->isUniqueViolation($exception)
                && str_contains($exception->errorInfo[2] ?? '', 'book_imports_one_active_per_library')) {
                abort(409, __('book_imports.errors.import_busy'));
            }

            throw $exception;
        }
    }
}
