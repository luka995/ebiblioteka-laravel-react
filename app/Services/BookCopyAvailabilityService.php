<?php

namespace App\Services;

use App\Models\Book;
use App\Models\BookCopy;

/**
 * Racuna dostupnost naslova iz fizickih jedinica (bez denormalizovanih
 * brojaca na `books`).
 *
 * Otpisane (aktivan otpis) i arhivirane (soft-deleted) kopije nisu u fondu.
 * Dostupnost umanjuju pozajmljene kopije i one sa napomenom o gresci.
 */
class BookCopyAvailabilityService
{
    /**
     * @return array{total: int, available: int, archived: int, written_off: int}
     */
    public function forBook(Book $book): array
    {
        $inFund = fn () => BookCopy::query()
            ->where('book_id', $book->id)
            ->whereDoesntHave('writeOffs', fn ($query) => $query->whereNull('cancelled_at'));

        $total = $inFund()->count();
        $available = $inFund()->where('borrowed', false)->where('rec_error', false)->count();
        $archived = BookCopy::onlyTrashed()->where('book_id', $book->id)->count();
        $writtenOff = BookCopy::where('book_id', $book->id)
            ->whereHas('writeOffs', fn ($query) => $query->whereNull('cancelled_at'))
            ->count();

        return [
            'total' => $total,
            'available' => $available,
            'archived' => $archived,
            'written_off' => $writtenOff,
        ];
    }
}
