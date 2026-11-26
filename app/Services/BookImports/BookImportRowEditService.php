<?php

namespace App\Services\BookImports;

use App\DTOs\ExcelImportHeaderMapDTO;
use App\Enums\BookImportFailureEnum;
use App\Enums\BookImportRowStatusEnum;
use App\Enums\BookImportStatusEnum;
use App\Exceptions\BookImportRowFailureException;
use App\Models\BookImport;
use App\Models\BookImportRow;
use App\Services\InventoryNumberService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use OpenSpout\Common\Entity\Cell;

class BookImportRowEditService
{
    public function __construct(
        private readonly BookImportHeaderMapper $headers,
        private readonly BookImportRowPayloadMapper $payloads,
        private readonly BookImportCopyService $copies,
        private readonly InventoryNumberService $numbers,
    ) {}

    /**
     * @param  array<string, mixed>  $changes
     */
    public function update(BookImport $import, int $rowId, int $inputVersion, array $changes): BookImportRow
    {
        return DB::transaction(function () use ($import, $rowId, $inputVersion, $changes): BookImportRow {
            $import = BookImport::lockForUpdate()->findOrFail($import->id);

            if (in_array($import->status, [BookImportStatusEnum::Pending, BookImportStatusEnum::Processing], true)) {
                abort(409, __('book_imports.errors.import_busy'));
            }

            $row = $import->rows()->whereKey($rowId)->lockForUpdate()->firstOrFail();

            if (! in_array($row->status, [BookImportRowStatusEnum::Pending, BookImportRowStatusEnum::Failed], true)) {
                abort(409, __('book_imports.errors.row_not_editable'));
            }

            if ($row->input_version !== $inputVersion) {
                abort(409, __('book_imports.errors.stale_input'));
            }

            $normalized = $this->normalizeChanges($changes, $row->row_number);
            $this->validateInventoryChange($import, $row, $changes, $normalized);

            $overrides = array_replace($row->input_overrides ?? [], $normalized);
            $effective = array_replace($row->raw_payload, $overrides);

            $row->update([
                'input_overrides' => $overrides,
                'input_version' => $row->input_version + 1,
                'isbn' => $this->searchValue($effective['isbn'] ?? null),
                'title' => $this->searchValue($effective['title'] ?? null),
                'inventory_number' => $this->searchValue($effective['inventory_number'] ?? null),
            ]);

            return $row->refresh()->load('bookCopy');
        });
    }

    /**
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    private function normalizeChanges(array $changes, int $rowNumber): array
    {
        $allowed = $this->headers->fields();
        $unknown = array_diff(array_keys($changes), $allowed);

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'changes' => __('book_imports.errors.invalid_changes'),
            ]);
        }

        $normalized = [];

        foreach ($changes as $field => $value) {
            if ($field === 'inventory_number') {
                $normalized[$field] = $this->normalizeTextValue($field, $value, $rowNumber);

                continue;
            }

            if ($value === null || (is_string($value) && trim($value) === '')) {
                $normalized[$field] = $field === 'authors' ? [] : null;

                continue;
            }

            if ($field === 'authors' && is_array($value)
                && array_is_list($value) && count(array_filter($value, is_string(...))) === count($value)) {
                $value = implode('; ', $value);

                if (trim($value) === '') {
                    $normalized[$field] = [];

                    continue;
                }
            }

            if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
                $this->invalidField($field);
            }

            $result = $this->payloads->map(
                [Cell::fromValue($value)],
                $rowNumber,
                new ExcelImportHeaderMapDTO('preview', $rowNumber, [$field], [$field => 0]),
            );

            if ($result === null || $result->failureCode !== null) {
                $this->invalidField($field);
            }

            $normalized[$field] = $result->payload[$field] ?? null;
        }

        return $normalized;
    }

    /**
     * Validates an edited inventory number against the library and other rows in this import.
     *
     * @param  array<string, mixed>  $changes
     * @param  array<string, mixed>  $normalized
     */
    private function validateInventoryChange(
        BookImport $import,
        BookImportRow $row,
        array $changes,
        array $normalized,
    ): void {
        if (! array_key_exists('inventory_number', $changes)) {
            return;
        }

        if (! $import->has_inventory_column) {
            throw ValidationException::withMessages([
                'changes.inventory_number' => __('book_imports.errors.inventory_column_missing'),
            ]);
        }

        $value = $normalized['inventory_number'] ?? null;

        if ($value !== null && ! is_string($value)) {
            $this->invalidField('inventory_number');
        }

        try {
            $number = $this->copies->validateInventory($import->library, $value);
        } catch (BookImportRowFailureException $exception) {
            throw ValidationException::withMessages([
                'changes.inventory_number' => $exception->failure->getLabel(),
            ]);
        }

        $otherRows = $import->rows()->where('id', '<>', $row->id)
            ->get(['raw_payload', 'input_overrides']);

        foreach ($otherRows as $other) {
            $otherValue = array_replace($other->raw_payload, $other->input_overrides ?? [])['inventory_number'] ?? null;

            if (! is_string($otherValue) && ! is_int($otherValue)) {
                continue;
            }

            try {
                if ($this->numbers->normalizeOrderNumber($otherValue) === $number) {
                    throw ValidationException::withMessages([
                        'changes.inventory_number' => BookImportFailureEnum::DuplicateInventoryNumber->getLabel(),
                    ]);
                }
            } catch (InvalidArgumentException) {
                continue;
            }
        }
    }

    private function normalizeTextValue(string $field, mixed $value, int $rowNumber): ?string
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }

        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            $this->invalidField($field);
        }

        $result = $this->payloads->map(
            [Cell::fromValue($value)],
            $rowNumber,
            new ExcelImportHeaderMapDTO('preview', $rowNumber, [$field], [$field => 0]),
        );

        if ($result === null || $result->failureCode !== null) {
            $this->invalidField($field);
        }

        $text = $result->payload[$field] ?? null;

        return is_string($text) ? $text : (is_int($text) ? (string) $text : null);
    }

    private function searchValue(mixed $value): ?string
    {
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function invalidField(string $field): never
    {
        throw ValidationException::withMessages([
            'changes.'.$field => BookImportFailureEnum::InvalidFieldValue->getLabel(),
        ]);
    }
}
