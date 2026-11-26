<?php

namespace App\Services\BookImports;

use App\Enums\BookImportRowStatusEnum;
use App\Enums\BookImportStatusEnum;
use App\Models\BookImport;

class BookImportCounterService
{
    public function update(BookImport $import): void
    {
        $counts = $import->rows()->selectRaw(
            "COUNT(*) AS total, COUNT(*) FILTER (WHERE status = 'imported') AS imported,
            COUNT(*) FILTER (WHERE status = 'failed') AS failed, COALESCE(SUM(completed_attempts), 0) AS attempts"
        )->first();
        $import->update([
            'total_rows' => (int) $counts->total,
            'imported_rows' => (int) $counts->imported,
            'failed_rows' => (int) $counts->failed,
            'processed_rows' => (int) $counts->imported + (int) $counts->failed,
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
