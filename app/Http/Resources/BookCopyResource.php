<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\InteractsWithResourceAbilities;
use App\Models\BookCopy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BookCopy
 */
class BookCopyResource extends JsonResource
{
    use InteractsWithResourceAbilities;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'library_id' => $this->library_id,
            'library_name' => $this->whenLoaded('library', fn () => $this->library?->name),
            'inv_number_auto' => $this->whenLoaded('library', fn () => (bool) $this->library?->inv_number_auto),
            'book_id' => $this->book_id,
            'book_name' => $this->whenLoaded('book', fn () => $this->book?->name),
            'order_number' => $this->order_number,
            'seq_number' => $this->seq_number,
            'barcode' => $this->barcode,
            'isbn' => $this->isbn,
            'publisher' => $this->publisher,
            'publish_place' => $this->publish_place,
            'publish_year' => $this->publish_year,
            'issue_number' => $this->issue_number,
            'num_of_pages' => $this->num_of_pages,
            'dimension' => $this->dimension,
            'part' => $this->part,
            'udk' => $this->udk,
            'binding' => $this->binding,
            'origin' => $this->origin,
            'book_number' => $this->book_number,
            'place_on_shelf' => $this->place_on_shelf,
            'price' => $this->price,
            'date_add' => $this->date_add?->toDateString(),
            'date_add_formatted' => $this->date_add?->format('d.m.Y.'),
            'notice' => $this->notice,
            'borrowed' => $this->borrowed,
            'reserved' => $this->reserved,
            'rec_error' => $this->rec_error,
            'rec_error_notice' => $this->rec_error_notice,
            'status' => $this->status(),
            'active_write_off' => $this->whenLoaded('activeWriteOff', fn () => new BookCopyWriteOffResource($this->activeWriteOff)),
            'deleted_at' => $this->deleted_at?->format('d.m.Y. H:i'),
            'created_at' => $this->created_at?->format('d.m.Y. H:i'),
            'updated_at' => $this->updated_at?->format('d.m.Y. H:i'),
            'can' => $this->abilities($request->user(), ['view', 'update', 'delete', 'restore', 'forceDelete', 'writeOff']),
        ];
    }

    private function status(): string
    {
        if ($this->trashed()) {
            return 'archived';
        }

        if ($this->relationLoaded('activeWriteOff') && $this->activeWriteOff !== null) {
            return 'written_off';
        }

        if ($this->borrowed) {
            return 'borrowed';
        }

        if ($this->rec_error) {
            return 'record_error';
        }

        return 'available';
    }
}
