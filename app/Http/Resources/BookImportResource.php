<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\InteractsWithResourceAbilities;
use App\Models\BookImport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BookImport
 */
class BookImportResource extends JsonResource
{
    use InteractsWithResourceAbilities;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'library_id' => $this->library_id,
            'library_name' => $this->whenLoaded('library', fn () => $this->library?->name),
            'requested_by' => $this->whenLoaded('user', fn () => $this->user?->name),
            'original_filename' => $this->original_filename,
            'file_size' => $this->file_size,
            'sheet_name' => $this->sheet_name,
            'status' => $this->status->value,
            'status_label' => $this->status->getLabel(),
            'has_inventory_column' => $this->has_inventory_column,
            'headers' => $this->headers,
            'column_map' => $this->column_map,
            'total_rows' => $this->total_rows,
            'pending_rows' => $this->pending_rows,
            'processing_rows' => $this->processing_rows,
            'preparation_failed_rows' => $this->preparation_failed_rows,
            'import_failed_rows' => $this->import_failed_rows,
            'processed_rows' => $this->processed_rows,
            'imported_rows' => $this->imported_rows,
            'failed_rows' => $this->failed_rows,
            'completed_attempts' => $this->completed_attempts,
            'failure_code' => $this->failure_code?->value,
            'failure_message' => $this->failure_message,
            'started_at' => $this->started_at?->format('d-m-Y H:i'),
            'finished_at' => $this->finished_at?->format('d-m-Y H:i'),
            'created_at' => $this->created_at?->format('d-m-Y H:i'),
            'can' => $this->abilities($request->user(), ['view', 'cancel', 'delete']),
        ];
    }
}
