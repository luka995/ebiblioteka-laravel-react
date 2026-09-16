<?php

namespace App\Jobs;

use App\Enums\InventoryBookStatus;
use App\Models\InventoryBook;
use App\Models\Library;
use App\Services\InventoryBookPdfService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Generise PDF inventarne knjige za jedan zahtev i cuva ga na privatni disk.
 *
 * Posao ide na zaseban `inventory` queue (vidi docker-compose `queue-inventory`
 * servis) da tesko generisanje ne blokira email queue. Loguje se u `inventory`
 * kanal da se iz worker logova vidi da li je biblioteka obradjena.
 */
class GenerateInventoryBookPdf implements ShouldQueue
{
    use Queueable;

    /** Bez automatskog retry-ja; neuspeh se vidi kroz status i log. */
    public int $tries = 1;

    public int $timeout = 600;

    public bool $failOnTimeout = true;

    public function __construct(public InventoryBook $inventoryBook)
    {
        $this->onQueue('inventory');
    }

    public function handle(InventoryBookPdfService $pdf): void
    {
        $book = $this->inventoryBook->fresh();

        if (! $book instanceof InventoryBook) {
            Log::channel('inventory')->warning('inventory_book.missing', [
                'id' => $this->inventoryBook->id,
            ]);

            return;
        }

        // Idempotencija: zavrsen posao se ne generise ponovo.
        if ($book->status === InventoryBookStatus::Completed) {
            return;
        }

        $startedAt = microtime(true);

        $book->update([
            'status' => InventoryBookStatus::Processing,
            'started_at' => now(),
            'failure_reason' => null,
        ]);

        Log::channel('inventory')->info('inventory_book.started', [
            'id' => $book->id,
            'library_id' => $book->library_id,
            'user_id' => $book->user_id,
        ]);

        try {
            $library = $book->library;

            if (! $library instanceof Library) {
                throw new RuntimeException("Biblioteka #{$book->library_id} ne postoji.");
            }

            $result = $pdf->render($library, $book->locale);

            $path = "{$book->library_id}/inventarna_knjiga_{$book->id}.pdf";
            $disk = Storage::disk('inventory');
            $disk->put($path, $result['contents']);

            $book->update([
                'status' => InventoryBookStatus::Completed,
                'file_path' => $path,
                'file_size' => $disk->size($path) ?: null,
                'rows_count' => $result['rows'],
                'finished_at' => now(),
                'failure_reason' => null,
            ]);

            Log::channel('inventory')->info('inventory_book.completed', [
                'id' => $book->id,
                'library_id' => $book->library_id,
                'rows' => $result['rows'],
                'bytes' => $book->file_size,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);
        } catch (Throwable $e) {
            $book->update([
                'status' => InventoryBookStatus::Failed,
                'finished_at' => now(),
                'failure_reason' => Str::limit($e->getMessage(), 1000),
            ]);

            Log::channel('inventory')->error('inventory_book.failed', [
                'id' => $book->id,
                'library_id' => $book->library_id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
