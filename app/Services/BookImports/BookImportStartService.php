<?php

namespace App\Services\BookImports;

use App\Enums\BookImportModeEnum;
use App\Enums\BookImportRowStatusEnum;
use App\Enums\BookImportStatusEnum;
use App\Jobs\ProcessBookImportJob;
use App\Models\BookImport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class BookImportStartService
{
    /**
     * @param  list<int>  $rowIds
     */
    public function start(BookImport $import, array $rowIds): int
    {
        Validator::make(['row_ids' => $rowIds], [
            'row_ids' => ['required', 'array', 'list', 'min:1', 'max:'.config('book_imports.max_rows')],
            'row_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ])->validate();

        return DB::transaction(function () use ($import, $rowIds): int {
            $import = BookImport::lockForUpdate()->findOrFail($import->id);

            if ($import->status !== BookImportStatusEnum::Ready) {
                throw ValidationException::withMessages(['import' => __('book_imports.errors.not_ready')]);
            }

            $rows = $import->rows()->whereIn('id', $rowIds)->where('status', BookImportRowStatusEnum::Pending);

            if ($rows->count() !== count($rowIds)) {
                throw ValidationException::withMessages(['row_ids' => __('book_imports.errors.invalid_selection')]);
            }

            $rows->update([
                'processing_requested_at' => now(),
                'attempt_mode' => BookImportModeEnum::IsbnCheck,
            ]);
            $import->update([
                'status' => BookImportStatusEnum::Processing,
                'finished_at' => null,
                'failure_code' => null,
                'failure_message' => null,
            ]);
            ProcessBookImportJob::dispatch($import->id)->afterCommit();

            return count($rowIds);
        });
    }
}
