<?php

namespace App\Jobs;

use App\Enums\BookImportFailureEnum;
use App\Enums\BookImportModeEnum;
use App\Enums\BookImportRowStatusEnum;
use App\Enums\BookImportStatusEnum;
use App\Exceptions\BookImportLeaseLostException;
use App\Http\Middleware\SetLocale;
use App\Models\BookImport;
use App\Services\BookImports\BookImportCounterService;
use App\Services\BookImports\BookImportLeaseService;
use App\Services\BookImports\BookImportRowProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Traits\Localizable;
use LogicException;
use Throwable;

class ProcessBookImportJob implements ShouldQueue
{
    use Localizable;
    use Queueable;

    public int $tries;

    public int $timeout;

    private string $locale;

    public function __construct(public int $bookImportId, public BookImportModeEnum $mode = BookImportModeEnum::IsbnCheck)
    {
        $this->tries = (int) config('book_imports.tries');
        $this->timeout = (int) config('book_imports.timeout');
        $this->locale = in_array(app()->getLocale(), SetLocale::LOCALES, true) ? app()->getLocale() : SetLocale::DEFAULT;
        $this->onQueue(config('book_imports.queue'));
    }

    public function handle(BookImportLeaseService $leases, BookImportRowProcessor $processor, BookImportCounterService $counters): void
    {
        if ($this->mode !== BookImportModeEnum::IsbnCheck) {
            throw new LogicException('Excel-direct processing is implemented in phase four.');
        }

        $this->withLocale($this->locale, function () use ($leases, $processor, $counters): void {
            $token = $leases->claim($this->bookImportId, $this->mode);

            if ($token === null) {
                return;
            }

            try {
                $started = microtime(true);
                $budget = $this->lookupBudget();

                if ($budget >= $this->timeout) {
                    throw new LogicException('The configured ISBN request budget must be shorter than the import job timeout.');
                }

                $ids = BookImport::findOrFail($this->bookImportId)->rows()
                    ->where('status', BookImportRowStatusEnum::Pending)
                    ->where('attempt_mode', $this->mode)->whereNotNull('processing_requested_at')
                    ->orderBy('row_number')->limit((int) config('book_imports.standard_batch_size'))->pluck('id');

                foreach ($ids as $id) {
                    if (microtime(true) - $started + $budget >= $this->timeout) {
                        break;
                    }

                    $processor->process($this->bookImportId, $id, $token);
                }

                $leases->run($this->bookImportId, $token, function (BookImport $import) use ($counters): void {
                    $counters->finishBatch($import);
                    $import->update(['processing_token' => null, 'lease_expires_at' => null]);
                    Log::channel(config('book_imports.log_channel'))->info('book_import.batch_processed', [
                        'book_import_id' => $import->id, 'processed_rows' => $import->processed_rows,
                        'imported_rows' => $import->imported_rows, 'failed_rows' => $import->failed_rows,
                        'status' => $import->status->value,
                    ]);

                    if ($import->status === BookImportStatusEnum::Processing) {
                        self::dispatch($import->id, $this->mode)->afterCommit();
                    }
                });
            } catch (BookImportLeaseLostException) {
                Log::channel(config('book_imports.log_channel'))->warning('book_import.processing_lease_lost', ['book_import_id' => $this->bookImportId]);
            } catch (Throwable $e) {
                Log::channel(config('book_imports.log_channel'))->error('book_import.processing_error', [
                    'book_import_id' => $this->bookImportId, 'exception' => $e::class,
                ]);

                try {
                    $leases->run($this->bookImportId, $token, function (BookImport $import) use ($counters): void {
                        if ($this->attempts() >= $this->tries) {
                            $this->markFailed($import, $counters);
                        }

                        $import->update(['processing_token' => null, 'lease_expires_at' => null]);
                    });
                } catch (BookImportLeaseLostException) {
                    Log::channel(config('book_imports.log_channel'))->warning('book_import.processing_failure_lease_lost', ['book_import_id' => $this->bookImportId]);
                }

                throw $e;
            }
        });
    }

    public function failed(?Throwable $exception): void
    {
        Log::channel(config('book_imports.log_channel'))->error('book_import.queue_failed', [
            'book_import_id' => $this->bookImportId, 'exception' => $exception === null ? null : $exception::class,
        ]);
        $this->withLocale($this->locale, fn () => DB::transaction(function (): void {
            $import = BookImport::lockForUpdate()->find($this->bookImportId);

            if ($import === null || $import->status !== BookImportStatusEnum::Processing) {
                return;
            }

            if ($import->lease_expires_at?->isFuture()) {
                Log::channel(config('book_imports.log_channel'))->warning('book_import.processing_failure_deferred', ['book_import_id' => $import->id]);

                return;
            }

            $this->markFailed($import, app(BookImportCounterService::class));
            $import->update(['processing_token' => null, 'lease_expires_at' => null]);
        }));
    }

    private function markFailed(BookImport $import, BookImportCounterService $counters): void
    {
        $import->rows()->whereNotNull('processing_requested_at')
            ->whereIn('status', [BookImportRowStatusEnum::Pending, BookImportRowStatusEnum::Processing])
            ->update([
                'status' => BookImportRowStatusEnum::Failed,
                'failure_code' => BookImportFailureEnum::JobFailed,
                'failure_message' => BookImportFailureEnum::JobFailed->getLabel(),
                'completed_attempts' => DB::raw("completed_attempts + CASE WHEN status = 'processing' THEN 1 ELSE 0 END"),
                'processing_requested_at' => null,
                'processed_at' => now(),
            ]);
        $counters->update($import);
        $import->update([
            'status' => BookImportStatusEnum::Failed,
            'failure_code' => BookImportFailureEnum::JobFailed,
            'failure_message' => BookImportFailureEnum::JobFailed->getLabel(),
            'finished_at' => now(),
        ]);
        Log::channel(config('book_imports.log_channel'))->error('book_import.processing_failed', ['book_import_id' => $import->id]);
    }

    private function lookupBudget(): float
    {
        return (max(1, (int) config('isbn.nbs.max_results', 3)) + 1)
            * (max(0, (int) config('isbn.http_retries', 2)) + 1)
            * ((int) config('isbn.timeout', 8) + max(0, (float) config('isbn.nbs.crawl_delay', 1))
                + max(0, (int) config('isbn.http_retry_delay_ms', 250)) / 1000)
            + 10;
    }
}
