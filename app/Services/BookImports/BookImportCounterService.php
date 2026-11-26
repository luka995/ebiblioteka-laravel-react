<?php

namespace App\Services\BookImports;

use App\Enums\BookImportRowStatusEnum;
use App\Enums\BookImportStatusEnum;
use App\Models\BookImport;
use Illuminate\Support\Facades\DB;

class BookImportCounterService
{
    public function rowStarted(BookImport $import): void
    {
        DB::table('book_imports')->where('id', $import->id)->update([
            'pending_rows' => DB::raw('GREATEST(pending_rows - 1, 0)'),
            'processing_rows' => DB::raw('processing_rows + 1'),
        ]);
    }

    public function rowImported(BookImport $import): void
    {
        DB::table('book_imports')->where('id', $import->id)->update([
            'processing_rows' => DB::raw('GREATEST(processing_rows - 1, 0)'),
            'imported_rows' => DB::raw('imported_rows + 1'),
            'processed_rows' => DB::raw('processed_rows + 1'),
        ]);
    }

    public function rowFailed(BookImport $import): void
    {
        DB::table('book_imports')->where('id', $import->id)->update([
            'processing_rows' => DB::raw('GREATEST(processing_rows - 1, 0)'),
            'import_failed_rows' => DB::raw('import_failed_rows + 1'),
            'failed_rows' => DB::raw('failed_rows + 1'),
            'processed_rows' => DB::raw('processed_rows + 1'),
        ]);
    }

    public function update(BookImport $import): void
    {
        $counts = $import->rows()->selectRaw(
            "COUNT(*) AS total,
            COUNT(*) FILTER (WHERE status = 'pending') AS pending,
            COUNT(*) FILTER (WHERE status = 'processing') AS processing,
            COUNT(*) FILTER (WHERE status = 'imported') AS imported,
            COUNT(*) FILTER (WHERE status = 'failed' AND failure_stage = 'preparation') AS preparation_failed,
            COUNT(*) FILTER (WHERE status = 'failed' AND failure_stage = 'processing') AS import_failed,
            COALESCE(SUM(completed_attempts), 0) AS attempts"
        )->first();

        $imported = (int) $counts->imported;
        $preparationFailed = (int) $counts->preparation_failed;
        $importFailed = (int) $counts->import_failed;

        $import->update([
            'total_rows' => (int) $counts->total,
            'pending_rows' => (int) $counts->pending,
            'processing_rows' => (int) $counts->processing,
            'preparation_failed_rows' => $preparationFailed,
            'import_failed_rows' => $importFailed,
            'imported_rows' => $imported,
            'failed_rows' => $preparationFailed + $importFailed,
            'processed_rows' => $imported + $importFailed,
            'completed_attempts' => (int) $counts->attempts,
        ]);
    }

    public function finishBatch(BookImport $import): void
    {
        $this->update($import);

        if ($import->rows()->whereNotNull('processing_requested_at')
            ->whereIn('status', [BookImportRowStatusEnum::Pending, BookImportRowStatusEnum::Processing])->exists()) {
            return;
        }

        $status = $import->rows()->where('status', BookImportRowStatusEnum::Pending)->exists()
            ? BookImportStatusEnum::Ready
            : ($import->failed_rows > 0 ? BookImportStatusEnum::CompletedWithErrors : BookImportStatusEnum::Completed);
        $import->update([
            'status' => $status,
            'finished_at' => $status === BookImportStatusEnum::Ready ? null : now(),
        ]);
    }
}
