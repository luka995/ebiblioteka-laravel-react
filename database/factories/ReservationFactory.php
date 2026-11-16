<?php

namespace Database\Factories;

use App\Enums\ReservationStatus;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Library;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'library_id' => Library::factory(),
            'user_id' => User::factory(),
            'book_id' => Book::factory(),
            'book_copy_id' => BookCopy::factory(),
            'from_date' => now(),
            'valid_till' => now()->addHours(48),
            'status' => ReservationStatus::Active,
            'ended_at' => null,
        ];
    }

    /**
     * Vezuje rezervaciju za konkretnu fizicku jedinicu i njene relacije.
     */
    public function forCopy(BookCopy $copy, ?User $user = null): static
    {
        return $this->state(fn (): array => [
            'library_id' => $copy->library_id,
            'book_id' => $copy->book_id,
            'book_copy_id' => $copy->id,
            'user_id' => $user?->id ?? User::factory(),
        ]);
    }
}
