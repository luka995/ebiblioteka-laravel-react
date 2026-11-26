<?php

namespace App\Console\Commands;

use App\Enums\BookImportModeEnum;
use App\Enums\BookImportRowStatusEnum;
use App\Enums\BookImportStatusEnum;
use App\Jobs\PrepareBookImportJob;
use App\Jobs\ProcessBookImportJob;
use App\Models\BookImport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecoverBookImports extends Command
{
    protected $signature = 'book-imports:recover';

    protected $description = 'Resume interrupted Excel import preparation and processing after their database lease expires';

    public function handle(): int
    {
        $count = 0;
        $cutoff = now()->subSeconds((int) config('book_imports.recovery_after_seconds'));
        BookImport::whereIn('status', [BookImportStatusEnum::Pending, BookImportStatusEnum::Processing])
            ->where(fn ($query) => $query->where('lease_expires_at', '<=', now())
                ->orWhere(fn ($query) => $query->whereNull('lease_expires_at')->where('updated_at', '<=', $cutoff)))
            ->select('id')->chunkById(100, function ($imports) use ($cutoff, &$count): void {
                foreach ($imports as $candidate) {
                    $count += DB::transaction(function () use ($candidate, $cutoff): int {
                        $import = BookImport::lockForUpdate()->find($candidate->id);

                        if ($import === null || ! in_array($import->status, [BookImportStatusEnum::Pending, BookImportStatusEnum::Processing], true)
                            || $import->lease_expires_at?->isFuture()
                            || ($import->lease_expires_at === null && $import->updated_at->isAfter($cutoff))) {
                            return 0;
                        }

                        $modes = $import->rows()->whereNotNull('processing_requested_at')
                            ->whereIn('status', [BookImportRowStatusEnum::Pending, BookImportRowStatusEnum::Processing])
                            ->distinct()->pluck('attempt_mode');

                        if ($modes->count() > 1 || ($modes->isNotEmpty() && $modes->first() === null)) {
                            Log::channel(config('book_imports.log_channel'))->error('book_import.invalid_recovery_modes', ['book_import_id' => $import->id]);
                            $this->error("Import {$import->id} has inconsistent selected row modes.");

                            return 0;
                        }

                        $mode = $modes->first() ?? BookImportModeEnum::IsbnCheck;
                        $import->forceFill(['processing_token' => null, 'lease_expires_at' => null, 'updated_at' => now()])->save();

                        if ($import->status === BookImportStatusEnum::Pending) {
                            PrepareBookImportJob::dispatch($import->id)->afterCommit();
                        } else {
                            ProcessBookImportJob::dispatch($import->id, $mode)->afterCommit();
                        }

                        Log::channel(config('book_imports.log_channel'))->info('book_import.recovered', ['book_import_id' => $import->id]);

                        return 1;
                    });
                }
            });
        $this->info("Recovered {$count} import(s).");

        return self::SUCCESS;
    }
}
