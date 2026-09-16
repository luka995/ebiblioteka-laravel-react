<?php

namespace App\Http\Resources;

use App\Enums\InventoryBookStatus;
use App\Http\Resources\Concerns\InteractsWithResourceAbilities;
use App\Models\InventoryBook;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin InventoryBook
 */
class InventoryBookResource extends JsonResource
{
    use InteractsWithResourceAbilities;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->status instanceof InventoryBookStatus
            ? $this->status
            : InventoryBookStatus::from((string) $this->status);

        return [
            'id' => $this->id,
            'library_id' => $this->library_id,
            'library_name' => $this->whenLoaded('library', fn () => $this->library?->name),
            'requested_by' => $this->whenLoaded('user', fn () => $this->user?->name),
            'status' => $status->value,
            'file_size' => $this->file_size,
            'rows_count' => $this->rows_count,
            'locale' => $this->locale,
            'started_at' => $this->started_at?->format('d.m.Y. H:i'),
            'finished_at' => $this->finished_at?->format('d.m.Y. H:i'),
            'failure_reason' => $this->failure_reason,
            'created_at' => $this->created_at?->format('d.m.Y. H:i'),
            'can' => $this->abilities($request->user(), ['view', 'download']),
        ];
    }
}
