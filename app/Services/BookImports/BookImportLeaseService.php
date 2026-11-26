<?php

namespace App\Services\BookImports;

use App\Enums\BookImportModeEnum;
use App\Enums\BookImportRowStatusEnum;
use App\Enums\BookImportStatusEnum;
use App\Exceptions\BookImportLeaseLostException;
use App\Models\BookImport;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BookImportLeaseService
{
    public function claim(int $id, BookImportModeEnum $mode): ?string
    {
        return DB::transaction(function () use ($id, $mode): ?string {
            $import = BookImport::lockForUpdate()->find($id);

            if ($import === null || $import->status !== BookImportStatusEnum::Processing
                || $import->lease_expires_at?->isFuture()) {
                Log::channel(config('book_imports.log_channel'))->info('book_import.processing_skipped', ['book_import_id' => $id]);

                return null;
            }

            if ($import->rows()->whereNotNull('processing_requested_at')
                ->whereIn('status', [BookImportRowStatusEnum::Pending, BookImportRowStatusEnum::Processing])
                ->where(fn ($query) => $query->where('attempt_mode', '!=', $mode)->orWhereNull('attempt_mode'))->exists()) {
                Log::channel(config('book_imports.log_channel'))->warning('book_import.mode_mismatch', ['book_import_id' => $id]);

                return null;
            }

            $token = (string) Str::uuid();
            $import->update([
                'processing_token' => $token,
                'lease_expires_at' => now()->addSeconds((int) config('book_imports.lease_seconds')),
                'failure_code' => null,
                'failure_message' => null,
                'finished_at' => null,
            ]);
            $import->rows()->whereNotNull('processing_requested_at')->where('attempt_mode', $mode)
                ->where('status', BookImportRowStatusEnum::Processing)
                ->update(['status' => BookImportRowStatusEnum::Pending]);

            return $token;
        });
    }

    /**
     * @template TResult
     *
     * @param  Closure(BookImport): TResult  $callback
     * @return TResult
     */
    public function run(int $id, string $token, Closure $callback): mixed
    {
        return DB::transaction(function () use ($id, $token, $callback) {
            $import = BookImport::lockForUpdate()->find($id);

            if ($import === null || $import->status !== BookImportStatusEnum::Processing
                || $import->processing_token !== $token || ! $import->lease_expires_at?->isFuture()) {
                throw new BookImportLeaseLostException('Book import processing lease is no longer owned.');
            }

            $result = $callback($import);

            if ($import->status === BookImportStatusEnum::Processing && $import->processing_token === $token) {
                $import->update(['lease_expires_at' => now()->addSeconds((int) config('book_imports.lease_seconds'))]);
            }

            return $result;
        });
    }
}
