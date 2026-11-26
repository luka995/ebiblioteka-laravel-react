<?php

namespace App\Services\BookImports;

use App\DTOs\BookImportResolutionDTO;
use App\Enums\BookImportFailureEnum;
use App\Enums\BookImportFailureStageEnum;
use App\Enums\BookImportRowStatusEnum;
use App\Exceptions\BookImportLeaseLostException;
use App\Exceptions\BookImportRowFailureException;
use App\Exceptions\InventoryNumberCollisionException;
use App\Models\BookImport;
use App\Models\BookImportRow;
use App\Services\DatabaseExceptionService;
use App\Services\InventoryNumberService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class BookImportRowProcessor
{
    public function __construct(
        private readonly BookImportLeaseService $leases,
        private readonly BookImportBookResolver $resolver,
        private readonly BookImportCopyService $copies,
        private readonly InventoryNumberService $numbers,
        private readonly DatabaseExceptionService $databaseErrors,
        private readonly BookImportCounterService $counters,
    ) {}

    public function process(int $importId, int $rowId, string $token): void
    {
        $row = $this->leases->run($importId, $token, function (BookImport $import) use ($rowId): ?BookImportRow {
            $row = $import->rows()->whereKey($rowId)->lockForUpdate()->first();

            if ($row === null || $row->status !== BookImportRowStatusEnum::Pending
                || $row->processing_requested_at === null || $row->attempt_mode === null) {
                Log::channel(config('book_imports.log_channel'))->info('book_import.row_skipped', ['row_id' => $rowId]);

                return null;
            }

            $row->update([
                'status' => BookImportRowStatusEnum::Processing,
                'attempts' => $row->attempts + 1,
                'failure_code' => null,
                'failure_message' => null,
                'lookup_diagnostics' => null,
                'resolved_payload' => null,
                'field_sources' => null,
                'processed_at' => null,
            ]);
            $this->counters->rowStarted($import);

            return $row;
        });

        if ($row === null) {
            return;
        }

        $import = BookImport::with('library')->findOrFail($importId);
        $resolution = null;

        try {
            $input = $this->resolver->input($row);

            if ($import->has_inventory_column) {
                $this->copies->validateInventory($import->library, $input['inventory_number'] ?? null);
            }

            $resolution = $this->resolver->resolve($import, $row, $input);
            $this->leases->run($importId, $token, function (BookImport $import) use ($row, $resolution): void {
                $current = $import->rows()->findOrFail($row->id);

                if ($current->status !== BookImportRowStatusEnum::Processing || $current->input_version !== $row->input_version) {
                    throw new BookImportLeaseLostException('The import row changed while resolving its metadata.');
                }

                $library = $import->library;
                $sequence = $this->numbers->lockForImport($library);
                $current = $import->rows()->whereKey($row->id)->lockForUpdate()->firstOrFail();
                $result = $this->resolver->persist($library, $resolution);
                $book = $result->book;
                $copy = $this->copies->create($book, $library, $sequence, $resolution->payload, $import->has_inventory_column);
                /** @var Carbon $dateAdded */
                $dateAdded = $copy->date_add;
                $book->load(['authors', 'categoryPrimary', 'categorySecondary']);
                $payload = [
                    ...$copy->only(BookImportCopyService::FIELDS),
                    'title' => $book->name,
                    'authors' => $book->authors->pluck('name')->all(),
                    'description' => $book->description,
                    'cover_url' => $book->cover_url,
                    'category' => $book->categoryPrimary?->fullName(),
                    'category_secondary' => $book->categorySecondary?->fullName(),
                    'inventory_number' => $copy->order_number,
                    'barcode' => $copy->barcode,
                    'date_add' => $dateAdded->toDateString(),
                ];
                $sources = $result->sources;
                $sources['inventory_number'] = $import->has_inventory_column
                    ? $sources['inventory_number'] : 'generated';
                $sources['barcode'] = 'generated';
                $sources['date_add'] = 'generated';
                $current->update([
                    'status' => BookImportRowStatusEnum::Imported,
                    'failure_stage' => null,
                    'book_id' => $book->id,
                    'book_copy_id' => $copy->id,
                    'resolved_payload' => $payload,
                    'field_sources' => $sources,
                    'lookup_diagnostics' => $resolution->diagnostics,
                    'completed_attempts' => $current->completed_attempts + 1,
                    'processing_requested_at' => null,
                    'processed_at' => now(),
                ]);
                $this->counters->rowImported($import);
            });
        } catch (BookImportLeaseLostException $e) {
            $this->resolver->forgetRolledBackCache($import->library);

            throw $e;
        } catch (Throwable $e) {
            $this->resolver->forgetRolledBackCache($import->library);
            $failure = $this->failure($e);
            Log::channel(config('book_imports.log_channel'))->log(
                $failure->failure === BookImportFailureEnum::UnexpectedError ? 'error' : 'warning',
                'book_import.row_failed',
                ['book_import_id' => $importId, 'row_id' => $rowId, 'failure_code' => $failure->failure->value, 'exception' => $e::class],
            );
            $this->fail($importId, $rowId, $token, $failure, $resolution);
        }
    }

    private function failure(Throwable $e): BookImportRowFailureException
    {
        if ($e instanceof BookImportRowFailureException) {
            return $e;
        }

        if ($e instanceof InventoryNumberCollisionException) {
            return new BookImportRowFailureException(BookImportFailureEnum::InventoryCollision);
        }

        if ($e instanceof QueryException && $this->databaseErrors->isUniqueViolation($e)) {
            $message = $e->errorInfo[2] ?? '';

            foreach ([
                'book_copies_library_id_seq_number_unique' => BookImportFailureEnum::DuplicateSeqNumber,
                'book_copies_library_id_order_number_unique' => BookImportFailureEnum::DuplicateInventoryNumber,
                'book_copies_library_id_barcode_unique' => BookImportFailureEnum::InventoryCollision,
            ] as $constraint => $failure) {
                if (str_contains($message, 'unique constraint "'.$constraint.'"')) {
                    return new BookImportRowFailureException($failure);
                }
            }
        }

        return new BookImportRowFailureException(BookImportFailureEnum::UnexpectedError);
    }

    private function fail(
        int $importId,
        int $rowId,
        string $token,
        BookImportRowFailureException $failure,
        ?BookImportResolutionDTO $resolution,
    ): void {
        $this->leases->run($importId, $token, function (BookImport $import) use ($rowId, $failure, $resolution): void {
            $row = $import->rows()->whereKey($rowId)->lockForUpdate()->firstOrFail();

            if ($row->status !== BookImportRowStatusEnum::Processing) {
                throw new BookImportLeaseLostException('The import row is no longer processing.');
            }

            $field = $failure->field;
            $label = $field === null ? null : ($import->headers[$import->column_map[$field] ?? -1] ?? $field);
            $row->update([
                'status' => BookImportRowStatusEnum::Failed,
                'failure_stage' => BookImportFailureStageEnum::Processing,
                'failure_code' => $failure->failure,
                'failure_message' => $label === null ? $failure->getMessage()
                    : __('book_imports.errors.invalid_field', ['field' => $label]),
                'lookup_diagnostics' => $failure->diagnostics ?: $resolution?->diagnostics,
                'resolved_payload' => $resolution?->payload,
                'field_sources' => $resolution?->sources,
                'completed_attempts' => $row->completed_attempts + 1,
                'processing_requested_at' => null,
                'processed_at' => now(),
            ]);
            $this->counters->rowFailed($import);
        });
    }
}
