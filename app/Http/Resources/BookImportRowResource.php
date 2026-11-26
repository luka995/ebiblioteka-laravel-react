<?php

namespace App\Http\Resources;

use App\Enums\BookImportRowStatusEnum;
use App\Models\BookImportRow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BookImportRow
 */
class BookImportRowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'row_number' => $this->row_number,
            'status' => $this->status->value,
            'status_label' => $this->status->getLabel(),
            'raw_payload' => $this->raw_payload,
            'input_overrides' => $this->input_overrides ?? [],
            'input_version' => $this->input_version,
            'effective_input' => array_replace($this->raw_payload, $this->input_overrides ?? []),
            'resolved_payload' => $this->resolved_payload,
            'field_sources' => $this->field_sources,
            'isbn' => $this->isbn,
            'title' => $this->title,
            'inventory_number' => $this->inventory_number,
            'assigned_inventory_number' => $this->status === BookImportRowStatusEnum::Imported
                ? ($this->bookCopy?->order_number ?? $this->resolved_payload['inventory_number'] ?? null)
                : null,
            'barcode' => $this->status === BookImportRowStatusEnum::Imported
                ? ($this->bookCopy?->barcode ?? $this->resolved_payload['barcode'] ?? null)
                : null,
            'failure_code' => $this->failure_code?->value,
            'failure_code_label' => $this->failure_code?->getLabel(),
            'failure_message' => $this->failure_message,
            'lookup_diagnostics' => $this->lookup_diagnostics,
            'attempts' => $this->attempts,
            'completed_attempts' => $this->completed_attempts,
            'attempt_mode' => $this->attempt_mode?->value,
            'attempt_mode_label' => $this->attempt_mode?->getLabel(),
            'book_id' => $this->book_id,
            'book_copy_id' => $this->book_copy_id,
            'processed_at' => $this->processed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
