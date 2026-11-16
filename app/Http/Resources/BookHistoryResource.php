<?php

namespace App\Http\Resources;

use App\Models\BookHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BookHistory
 */
class BookHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'library_id' => $this->library_id,
            'library_name' => $this->whenLoaded('library', fn () => $this->library?->name),
            'book_id' => $this->book_id,
            'book_name' => $this->whenLoaded('book', fn () => $this->book?->name),
            'book_copy_id' => $this->book_copy_id,
            'barcode' => $this->whenLoaded('bookCopy', fn () => $this->bookCopy?->barcode),
            'order_number' => $this->whenLoaded('bookCopy', fn () => $this->bookCopy?->order_number),
            'user_id' => $this->user_id,
            'user_name' => $this->whenLoaded('user', fn () => $this->user?->displayName()),
            'actor_id' => $this->actor_id,
            'actor_name' => $this->whenLoaded('actor', fn () => $this->actor?->displayName()),
            'action' => $this->action,
            'action_date' => $this->action_date?->toIso8601String(),
            'action_date_formatted' => $this->action_date?->format('d.m.Y. H:i'),
            'meta' => $this->meta,
        ];
    }
}
