<?php

namespace App\Jobs;

use App\Enums\BarcodePrintJobStatus;
use App\Models\BarcodePrintJob;
use App\Models\Library;
use App\Services\BarcodePrintService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Generise PDF sa bar-kodovima svih jedinica biblioteke i cuva ga na privatni
 * disk.
 *
 * Posao ide na zaseban `print` queue (vidi docker-compose `queue-print`
 * servis) da tesko generisanje ne blokira email queue. Loguje se u `barcode`
 * kanal da se iz worker logova vidi tok obrade.
 */
class GenerateBarcodePrintPdf implements ShouldQueue
{
    use Queueable;

    /** Bez automatskog retry-ja; neuspeh se vidi kroz status i log. */
    public int $tries = 1;

    public int $timeout = 600;

    public bool $failOnTimeout = true;

    public function __construct(public BarcodePrintJob $barcodePrintJob)
    {
        $this->onQueue('print');
    }

    public function handle(BarcodePrintService $print): void
    {
        $job = $this->barcodePrintJob->fresh();

        if (! $job instanceof BarcodePrintJob) {
            Log::channel('barcode')->warning('barcode_print.missing', [
                'id' => $this->barcodePrintJob->id,
            ]);

            return;
        }

        // Idempotencija: zavrsen posao se ne generise ponovo.
        if ($job->status === BarcodePrintJobStatus::Completed) {
            return;
        }

        $startedAt = microtime(true);

        $job->update([
            'status' => BarcodePrintJobStatus::Processing,
            'started_at' => now(),
            'failure_reason' => null,
        ]);

        Log::channel('barcode')->info('barcode_print.started', [
            'id' => $job->id,
            'library_id' => $job->library_id,
            'user_id' => $job->user_id,
            'format' => $job->format?->value,
        ]);

        try {
            $library = $job->library;

            if (! $library instanceof Library) {
                throw new RuntimeException("Biblioteka #{$job->library_id} ne postoji.");
            }

            $result = $print->render($job);

            $path = "{$job->library_id}/barkodovi_{$job->id}.pdf";
            $disk = Storage::disk('barcode');
            $disk->put($path, $result['contents']);

            $job->update([
                'status' => BarcodePrintJobStatus::Completed,
                'file_path' => $path,
                'file_size' => $disk->size($path) ?: null,
                'items_count' => $result['items'],
                'invalid_count' => $result['invalid'],
                'finished_at' => now(),
                'failure_reason' => null,
            ]);

            Log::channel('barcode')->info('barcode_print.completed', [
                'id' => $job->id,
                'library_id' => $job->library_id,
                'items' => $result['items'],
                'invalid' => $result['invalid'],
                'bytes' => $job->file_size,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);
        } catch (Throwable $e) {
            $job->update([
                'status' => BarcodePrintJobStatus::Failed,
                'finished_at' => now(),
                'failure_reason' => Str::limit($e->getMessage(), 1000),
            ]);

            Log::channel('barcode')->error('barcode_print.failed', [
                'id' => $job->id,
                'library_id' => $job->library_id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
