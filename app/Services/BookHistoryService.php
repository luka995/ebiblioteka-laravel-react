<?php

namespace App\Services;

use App\Models\BookHistory;
use App\Models\Borrow;
use App\Models\Reservation;
use App\Models\User;

/**
 * Append-only audit zapisi za cirkulaciju knjiga.
 */
class BookHistoryService
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function recordReservation(
        Reservation $reservation,
        string $action,
        ?User $actor = null,
        array $meta = [],
    ): BookHistory {
        return BookHistory::create([
            'library_id' => $reservation->library_id,
            'book_id' => $reservation->book_id,
            'book_copy_id' => $reservation->book_copy_id,
            'user_id' => $reservation->user_id,
            'actor_id' => $actor?->id,
            'action' => $action,
            'action_date' => now(),
            'meta' => array_merge([
                'from_date' => $reservation->from_date?->toIso8601String(),
                'valid_till' => $reservation->valid_till?->toIso8601String(),
                'status' => $reservation->status?->value,
            ], $meta),
        ]);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function recordBorrow(
        Borrow $borrow,
        string $action,
        ?User $actor = null,
        array $meta = [],
    ): BookHistory {
        return BookHistory::create([
            'library_id' => $borrow->library_id,
            'book_id' => $borrow->book_id,
            'book_copy_id' => $borrow->book_copy_id,
            'user_id' => $borrow->user_id,
            'actor_id' => $actor?->id,
            'action' => $action,
            'action_date' => now(),
            'meta' => array_merge([
                'borrow_id' => $borrow->id,
                'from_date' => $borrow->from_date?->toIso8601String(),
                'valid_till' => $borrow->valid_till?->toIso8601String(),
                'return_date' => $borrow->return_date?->toIso8601String(),
                'reservation_id' => $borrow->reservation_id,
            ], $meta),
        ]);
    }
}
