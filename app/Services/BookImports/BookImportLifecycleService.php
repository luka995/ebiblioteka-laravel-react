<?php

namespace App\Services\BookImports;

use App\Enums\BookImportStatusEnum;
use App\Models\BookImport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Otkazivanje i brisanje uvoza koji još nije proizveo nijednu kopiju.
 *
 * Uvezeni redovi i njihove knjige/kopije se ne brišu kroz import lifecycle;
 * takav uvoz ostaje kao audit trag i ne može se obrisati ovim putem.
 */
class BookImportLifecycleService
{
    public function cancel(BookImport $import): BookImport
    {
        return DB::transaction(function () use ($import): BookImport {
            $import = BookImport::lockForUpdate()->findOrFail($import->id);

            if (! in_array($import->status, [
                BookImportStatusEnum::Pending,
                BookImportStatusEnum::Ready,
                BookImportStatusEnum::Processing,
            ], true)) {
                abort(409, __('book_imports.errors.not_cancellable'));
            }

            $this->ensureNothingImported($import, 'cannot_cancel_imported');

            $import->update([
                'status' => BookImportStatusEnum::Cancelled,
                'processing_token' => null,
                'lease_expires_at' => null,
                'finished_at' => now(),
                'failure_code' => null,
                'failure_message' => null,
            ]);

            Log::channel(config('book_imports.log_channel'))->info('book_import.cancelled', [
                'book_import_id' => $import->id,
            ]);

            return $import->refresh();
        });
    }

    public function delete(BookImport $import): void
    {
        $path = null;

        DB::transaction(function () use ($import, &$path): void {
            $import = BookImport::lockForUpdate()->findOrFail($import->id);

            if ($import->status === BookImportStatusEnum::Processing) {
                abort(409, __('book_imports.errors.cancel_before_delete'));
            }

            $this->ensureNothingImported($import, 'cannot_delete_imported');

            $path = $import->file_path;
            $import->delete();

            Log::channel(config('book_imports.log_channel'))->info('book_import.deleted', [
                'book_import_id' => $import->id,
            ]);
        });

        if ($path !== null && ! Storage::disk(config('book_imports.disk'))->delete($path)) {
            Log::channel(config('book_imports.log_channel'))->warning('book_import.file_cleanup_failed', [
                'path' => $path,
            ]);
        }
    }

    private function ensureNothingImported(BookImport $import, string $error): void
    {
        if ($import->imported_rows > 0 || $import->rows()
            ->where(fn ($query) => $query->whereNotNull('book_id')->orWhereNotNull('book_copy_id'))
            ->exists()) {
            abort(409, __('book_imports.errors.'.$error));
        }
    }
}
