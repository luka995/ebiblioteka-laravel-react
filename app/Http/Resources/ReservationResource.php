<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\InteractsWithResourceAbilities;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Reservation
 */
class ReservationResource extends JsonResource
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
            'user_id' => $this->user_id,
            'user_name' => $this->whenLoaded('user', fn () => $this->user?->displayName()),
            'book_id' => $this->book_id,
            'book_name' => $this->whenLoaded('book', fn () => $this->book?->name),
            'book_authors' => $this->whenLoaded('book', fn () => $this->book?->authors
                ?->map(fn ($author): string => $author->displayName())
                ->join('; ')),
            'book_copy_id' => $this->book_copy_id,
            'barcode' => $this->whenLoaded('bookCopy', fn () => $this->bookCopy?->barcode),
            'order_number' => $this->whenLoaded('bookCopy', fn () => $this->bookCopy?->order_number),
            'from_date' => $this->from_date?->toIso8601String(),
            'from_date_formatted' => $this->from_date?->format('d.m.Y. H:i'),
            'valid_till' => $this->valid_till?->toIso8601String(),
            'valid_till_formatted' => $this->valid_till?->format('d.m.Y. H:i'),
            'status' => $this->status?->value,
            'status_label' => $this->status?->getLabel(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'can' => [
                'cancel' => $this->isActive() && $request->user()?->can('cancel', $this->resource),
                'issue' => $this->isActive() && $request->user()?->can('issue', $this->resource),
            ],
        ];
    }
}
