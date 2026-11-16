<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\InteractsWithResourceAbilities;
use App\Models\Borrow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Borrow
 */
class BorrowResource extends JsonResource
{
    use InteractsWithResourceAbilities;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $overdueDays = $this->returned
            ? (int) $this->overdue_days
            : $this->calculateOverdueDays();

        $overdueAmount = $this->returned
            ? (float) $this->overdue_amount
            : round($overdueDays * (float) ($this->library?->settings?->overduePricePerDay() ?? 0), 2);

        return [
            'id' => $this->id,
            'library_id' => $this->library_id,
            'library_name' => $this->whenLoaded('library', fn () => $this->library?->name),
            'user_id' => $this->user_id,
            'user_name' => $this->whenLoaded('user', fn () => $this->user?->displayName()),
            'user_barcode' => $this->whenLoaded('user', fn () => $this->user?->bar_code),
            'book_id' => $this->book_id,
            'book_name' => $this->whenLoaded('book', fn () => $this->book?->name),
            'book_authors' => $this->whenLoaded('book', fn () => $this->book?->authors
                ?->map(fn ($author): string => $author->displayName())
                ->join('; ')),
            'book_copy_id' => $this->book_copy_id,
            'barcode' => $this->whenLoaded('bookCopy', fn () => $this->bookCopy?->barcode),
            'order_number' => $this->whenLoaded('bookCopy', fn () => $this->bookCopy?->order_number),
            'reservation_id' => $this->reservation_id,
            'from_date' => $this->from_date?->toIso8601String(),
            'from_date_formatted' => $this->from_date?->format('d.m.Y. H:i'),
            'valid_till' => $this->valid_till?->toIso8601String(),
            'valid_till_formatted' => $this->valid_till?->format('d.m.Y. H:i'),
            'return_date' => $this->return_date?->toIso8601String(),
            'return_date_formatted' => $this->return_date?->format('d.m.Y. H:i'),
            'returned' => (bool) $this->returned,
            'status' => $this->status(),
            'is_overdue' => $this->isOverdue(),
            'overdue_days' => $overdueDays,
            'overdue_amount' => $overdueAmount,
            'created_by' => $this->whenLoaded('createdBy', fn () => $this->createdBy?->displayName()),
            'created_at' => $this->created_at?->format('d.m.Y. H:i'),
            'updated_at' => $this->updated_at?->format('d.m.Y. H:i'),
            'can' => $this->abilities($request->user(), ['view', 'returnBook']),
        ];
    }
}
