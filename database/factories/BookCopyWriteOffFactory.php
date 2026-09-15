<?php

namespace Database\Factories;

use App\Enums\BookCopyWriteOffReason;
use App\Models\BookCopy;
use App\Models\BookCopyWriteOff;
use App\Models\Library;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookCopyWriteOff>
 */
class BookCopyWriteOffFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'book_copy_id' => BookCopy::factory(),
            'book_id' => null,
            'order_number' => null,
            'library_id' => Library::factory(),
            'reason' => BookCopyWriteOffReason::Unusable,
            'occurred_at' => now()->toDateString(),
            'notice' => null,
            'cancelled_at' => null,
        ];
    }

    /**
     * Popunjava snapshot polja iz date fizicke jedinice.
     */
    public function forCopy(BookCopy $copy): static
    {
        return $this->state(fn (): array => [
            'book_copy_id' => $copy->id,
            'book_id' => $copy->book_id,
            'order_number' => $copy->order_number,
            'library_id' => $copy->library_id,
        ]);
    }
}
