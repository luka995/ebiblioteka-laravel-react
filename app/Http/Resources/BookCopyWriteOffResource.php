<?php

namespace App\Http\Resources;

use App\Models\BookCopyWriteOff;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BookCopyWriteOff
 */
class BookCopyWriteOffResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'book_copy_id' => $this->book_copy_id,
            'book_id' => $this->book_id,
            'order_number' => $this->order_number,
            'reason' => $this->reason->value,
            'reason_label' => $this->reason->getLabel(),
            'occurred_at' => $this->occurred_at?->format('d.m.Y.'),
            'notice' => $this->notice,
            'cancelled_at' => $this->cancelled_at?->format('d.m.Y. H:i'),
            'created_by' => $this->whenLoaded('createdBy', fn () => $this->createdBy?->displayName()),
            'cancelled_by' => $this->whenLoaded('cancelledBy', fn () => $this->cancelledBy?->displayName()),
            'created_at' => $this->created_at?->format('d.m.Y. H:i'),
            'updated_at' => $this->updated_at?->format('d.m.Y. H:i'),
        ];
    }
}
