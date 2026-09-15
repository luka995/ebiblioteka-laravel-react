<?php

namespace App\Services;

use App\Enums\BookCopyWriteOffReason;
use App\Models\BookCopy;
use App\Models\BookCopyWriteOff;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Otpis fizickih jedinica (append-only istorija) i napomena o pogresnom
 * zavodjenju (rec_error).
 */
class BookCopyWriteOffService
{
    public function writeOff(
        BookCopy $copy,
        BookCopyWriteOffReason $reason,
        ?string $notice,
        ?string $occurredAt,
        User $actor,
    ): BookCopyWriteOff {
        if ($copy->trashed()) {
            throw ValidationException::withMessages([
                'reason' => __('validation.custom.book_copy_book_mismatch'),
            ]);
        }

        if ($copy->writeOffs()->whereNull('cancelled_at')->exists()) {
            throw ValidationException::withMessages([
                'reason' => __('validation.custom.book_copy_duplicate_order_number'),
            ]);
        }

        return DB::transaction(fn (): BookCopyWriteOff => BookCopyWriteOff::create([
            'book_copy_id' => $copy->id,
            'book_id' => $copy->book_id,
            'order_number' => $copy->order_number,
            'library_id' => $copy->library_id,
            'reason' => $reason,
            'occurred_at' => $occurredAt ?? now()->toDateString(),
            'notice' => $notice,
            'created_by_id' => $actor->id,
        ]));
    }

    public function cancel(BookCopy $copy, User $actor): void
    {
        $active = $copy->writeOffs()->whereNull('cancelled_at')->first();

        if ($active === null) {
            throw ValidationException::withMessages([
                'reason' => __('validation.custom.book_copy_book_mismatch'),
            ]);
        }

        $active->update([
            'cancelled_at' => now(),
            'cancelled_by_id' => $actor->id,
        ]);
    }

    public function setRecError(BookCopy $copy, bool $recError, ?string $notice): BookCopy
    {
        $copy->update([
            'rec_error' => $recError,
            'rec_error_notice' => $recError ? $notice : null,
        ]);

        return $copy;
    }
}
