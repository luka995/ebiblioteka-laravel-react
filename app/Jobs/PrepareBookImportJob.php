<?php

namespace App\Jobs;

use App\DTOs\BookImportRowDataDTO;
use App\DTOs\ExcelImportHeaderMapDTO;
use App\Enums\BookImportFailureEnum;
use App\Enums\BookImportRowStatusEnum;
use App\Enums\BookImportStatusEnum;
use App\Exceptions\BookImportFileException;
use App\Http\Middleware\SetLocale;
use App\Models\BookCopy;
use App\Models\BookImport;
use App\Services\BookImports\BookImportMapper;
use App\Services\ExcelImports\ExcelImportReader;
use App\Services\InventoryNumberService;
use Closure;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Localizable;
use InvalidArgumentException;
use OpenSpout\Common\Exception\OpenSpoutException;
use Throwable;

class PrepareBookImportJob implements ShouldQueue
{
    use Localizable;
    use Queueable;

    public int $tries;

    public int $timeout;

    private ?string $token = null;

    private string $locale;

    public function __construct(public int $bookImportId)
    {
        $this->tries = (int) config('book_imports.tries');
        $this->timeout = (int) config('book_imports.timeout');
        $this->locale = in_array(app()->getLocale(), SetLocale::LOCALES, true)
            ? app()->getLocale() : SetLocale::DEFAULT;
        $this->onQueue(config('book_imports.queue'));
    }

    public function handle(ExcelImportReader $reader, BookImportMapper $mapper, InventoryNumberService $numbers): void
    {
        $this->withLocale($this->locale, fn () => $this->prepare($reader, $mapper, $numbers));
    }

    private function prepare(ExcelImportReader $reader, BookImportMapper $mapper, InventoryNumberService $numbers): void
    {
        if (! $this->claim()) {
            return;
        }

        try {
            $import = BookImport::findOrFail($this->bookImportId);
            $path = Storage::disk(config('book_imports.disk'))->path($import->file_path);
            $header = $reader->header($path, $mapper, (int) config('book_imports.header_scan_rows'))
                ?? throw new BookImportFileException(BookImportFailureEnum::MissingColumns);
            $chunk = [];
            $seen = [];
            $count = 0;

            foreach ($reader->rows($path, $header, $mapper) as $row) {
                if (++$count > config('book_imports.max_rows')) {
                    throw new BookImportFileException(BookImportFailureEnum::TooManyRows);
                }

                $chunk[] = $this->record($row, $header, $numbers, $seen);

                if (count($chunk) >= config('book_imports.prepare_chunk_size')) {
                    if (! $this->flush($chunk, $import->library_id)) {
                        return;
                    }

                    $chunk = [];
                }
            }

            if ($count === 0) {
                throw new BookImportFileException(BookImportFailureEnum::NoRows);
            }

            if ($chunk !== [] && ! $this->flush($chunk, $import->library_id)) {
                return;
            }

            $this->withLease(function (BookImport $import) use ($header): void {
                $import->update([
                    'status' => BookImportStatusEnum::Ready,
                    'sheet_name' => $header->sheetName,
                    'headers' => $header->headers,
                    'column_map' => $header->columnMap,
                    'has_inventory_column' => $header->hasColumn('inventory_number'),
                    'processing_token' => null,
                    'lease_expires_at' => null,
                ]);
                $this->log('info', 'book_import.prepared', ['total_rows' => $import->total_rows]);
            });
        } catch (BookImportFileException|OpenSpoutException $e) {
            $failure = $e instanceof BookImportFileException ? $e->failureCode : BookImportFailureEnum::FileUnreadable;
            $this->withLease(fn (BookImport $import) => $this->markFailed($import, $failure));
        } catch (Throwable $e) {
            $this->log('error', 'book_import.preparation_error', ['exception' => $e::class]);
            $this->withLease(function (BookImport $import): void {
                if ($this->attempts() >= $this->tries) {
                    $this->markFailed($import, BookImportFailureEnum::JobFailed);
                } else {
                    $import->update(['processing_token' => null, 'lease_expires_at' => null]);
                }
            });

            throw $e;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->log('error', 'book_import.preparation_exhausted', ['exception' => $exception !== null ? $exception::class : null]);
        $this->withLocale($this->locale, fn () => DB::transaction(function (): void {
            $import = BookImport::lockForUpdate()->find($this->bookImportId);

            if ($import === null || $import->status !== BookImportStatusEnum::Pending) {
                return;
            }

            // Laravel recreates the job for failed(); a live lease may belong to another execution.
            if ($import->lease_expires_at?->isFuture()) {
                $this->log('warning', 'book_import.failure_deferred_to_lease_recovery');

                return;
            }

            $this->markFailed($import, BookImportFailureEnum::JobFailed);
        }));
    }

    private function claim(): bool
    {
        return DB::transaction(function (): bool {
            $import = BookImport::lockForUpdate()->find($this->bookImportId);

            if ($import === null || $import->status !== BookImportStatusEnum::Pending) {
                $this->log('info', 'book_import.preparation_skipped');

                return false;
            }

            if ($import->processing_token !== null && $import->lease_expires_at?->isFuture()) {
                $this->log('info', 'book_import.lease_busy');

                return false;
            }

            $this->token = (string) Str::uuid();
            $import->rows()->delete();
            $import->update([
                'processing_token' => $this->token,
                'lease_expires_at' => now()->addSeconds((int) config('book_imports.lease_seconds')),
                'started_at' => $import->started_at ?? now(),
                'finished_at' => null,
                'failure_code' => null,
                'failure_message' => null,
                'total_rows' => 0,
                'processed_rows' => 0,
                'failed_rows' => 0,
            ]);
            $this->log('info', 'book_import.preparing');

            return true;
        });
    }

    private function withLease(Closure $callback): bool
    {
        return DB::transaction(function () use ($callback): bool {
            $import = BookImport::lockForUpdate()->find($this->bookImportId);

            if ($import === null || $import->status !== BookImportStatusEnum::Pending
                || $import->processing_token !== $this->token || ! $import->lease_expires_at?->isFuture()) {
                $this->log('warning', 'book_import.lease_lost');

                return false;
            }

            $callback($import);

            return true;
        });
    }

    /**
     * @param  array<string, int>  $seen
     * @return array<string, mixed>
     */
    private function record(
        BookImportRowDataDTO $row,
        ExcelImportHeaderMapDTO $header,
        InventoryNumberService $numbers,
        array &$seen,
    ): array {
        $failure = $row->failureCode;
        $field = $row->failureField;
        $inventory = $row->payload['inventory_number'] ?? null;

        if ($header->hasColumn('inventory_number')) {
            if ($inventory === null) {
                $failure ??= BookImportFailureEnum::MissingInventoryNumber;
            } else {
                try {
                    $normalized = $numbers->normalizeOrderNumber($inventory);

                    if (strlen($normalized) > 12) {
                        $failure ??= BookImportFailureEnum::InvalidInventoryNumber;
                    } elseif (isset($seen[$normalized])) {
                        $failure ??= BookImportFailureEnum::DuplicateInventoryNumber;
                    } else {
                        $seen[$normalized] = $row->rowNumber;
                    }
                } catch (InvalidArgumentException) {
                    $failure ??= BookImportFailureEnum::InvalidInventoryNumber;
                }
            }
        }

        $message = $failure?->getLabel();

        if ($failure === BookImportFailureEnum::InvalidFieldValue && $field !== null) {
            $message = __('book_imports.errors.invalid_field', [
                'field' => $header->headers[$header->columnMap[$field]],
            ]);
        }

        $now = now();

        return [
            'book_import_id' => $this->bookImportId,
            'row_number' => $row->rowNumber,
            'raw_payload' => json_encode($row->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'isbn' => $this->searchValue($row->payload['isbn'] ?? null, 32),
            'title' => $this->searchValue($row->payload['title'] ?? null, 255),
            'inventory_number' => $this->searchValue($inventory, 32),
            'status' => $failure === null ? BookImportRowStatusEnum::Pending->value : BookImportRowStatusEnum::Failed->value,
            'failure_code' => $failure?->value,
            'failure_message' => $message,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $chunk
     */
    private function flush(array $chunk, int $libraryId): bool
    {
        return $this->withLease(function (BookImport $import) use ($chunk, $libraryId): void {
            $candidates = [];

            foreach ($chunk as $record) {
                if ($record['failure_code'] === null && $record['inventory_number'] !== null) {
                    $candidates[] = (int) $record['inventory_number'];
                }
            }

            $existing = $candidates === [] ? [] : BookCopy::withTrashed()
                ->where('library_id', $libraryId)
                ->whereIn(DB::raw('CAST(order_number AS BIGINT)'), $candidates)
                ->pluck('order_number')->map(fn ($value) => (int) $value)->flip()->all();
            $failed = 0;

            foreach ($chunk as &$record) {
                if ($record['failure_code'] === null && $record['inventory_number'] !== null
                    && isset($existing[(int) $record['inventory_number']])) {
                    $record['status'] = BookImportRowStatusEnum::Failed->value;
                    $record['failure_code'] = BookImportFailureEnum::DuplicateInventoryNumber->value;
                    $record['failure_message'] = BookImportFailureEnum::DuplicateInventoryNumber->getLabel();
                }

                $failed += $record['status'] === BookImportRowStatusEnum::Failed->value ? 1 : 0;
            }
            unset($record);

            $import->rows()->insert($chunk);
            $import->update([
                'total_rows' => $import->total_rows + count($chunk),
                'processed_rows' => $import->processed_rows + $failed,
                'failed_rows' => $import->failed_rows + $failed,
                'lease_expires_at' => now()->addSeconds((int) config('book_imports.lease_seconds')),
            ]);
            $this->log('info', 'book_import.chunk_prepared', ['total_rows' => $import->total_rows]);
        });
    }

    private function markFailed(BookImport $import, BookImportFailureEnum $failure): void
    {
        $import->rows()->delete();
        $import->update([
            'status' => BookImportStatusEnum::Failed,
            'failure_code' => $failure,
            'failure_message' => $failure->getLabel(),
            'total_rows' => 0,
            'processed_rows' => 0,
            'failed_rows' => 0,
            'finished_at' => now(),
            'processing_token' => null,
            'lease_expires_at' => null,
        ]);
        $this->log('warning', 'book_import.preparation_failed', ['failure_code' => $failure->value]);
    }

    private function searchValue(?string $value, int $maxLength): ?string
    {
        return $value !== null && mb_strlen($value) <= $maxLength ? $value : null;
    }

    private function log(string $level, string $message, array $context = []): void
    {
        Log::channel(config('book_imports.log_channel'))->log($level, $message, [
            'book_import_id' => $this->bookImportId,
            ...$context,
        ]);
    }
}
