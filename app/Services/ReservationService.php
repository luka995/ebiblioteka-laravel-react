<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\BookHistory;
use App\Models\Borrow;
use App\Models\Library;
use App\Models\LibrarySettings;
use App\Models\Reservation;
use App\Models\User;
use App\Services\Notification\CirculationNotificationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Domenska logika rezervacija fizickih jedinica.
 *
 * Rezervacija zauzima slobodnu kopiju koja je na polici. `valid_till` je rok
 * do kog biblioteka drzi kopiju za korisnika (nije rok za vracanje pozajmice).
 * Kada rok istekne, kopija se oslobadja i ponovo je dostupna.
 */
class ReservationService
{
    public function __construct(
        private readonly BookHistoryService $history,
        private readonly CirculationNotificationService $notifications,
    ) {}

    /**
     * Rezervise prvu slobodnu kopiju naslova za korisnika.
     *
     * @throws ValidationException
     */
    public function create(User $user, Book $book): Reservation
    {
        $book->loadMissing('library');
        $library = $book->library;

        if ($library === null || $library->deleted) {
            throw ValidationException::withMessages([
                'book_id' => __('validation.custom.reservation_library_unavailable'),
            ]);
        }

        if ($book->trashed()) {
            throw ValidationException::withMessages([
                'book_id' => __('validation.custom.reservation_book_unavailable'),
            ]);
        }

        if (! $user->libraries()->whereKey($library->id)->exists()) {
            throw ValidationException::withMessages([
                'book_id' => __('validation.custom.reservation_not_member'),
            ]);
        }

        $this->expireOverdue();

        if ($this->hasActiveReservation($user, $book, $library)) {
            throw ValidationException::withMessages([
                'book_id' => __('validation.custom.reservation_duplicate'),
            ]);
        }

        $reservation = DB::transaction(function () use ($user, $book, $library): Reservation {
            $copy = $this->findAvailableCopy($book, $library);

            if ($copy === null) {
                throw ValidationException::withMessages([
                    'book_id' => __('validation.custom.reservation_no_copies'),
                ]);
            }

            $settings = $library->settings()->firstOrCreate([], LibrarySettings::defaults());

            $reservation = Reservation::create([
                'library_id' => $library->id,
                'user_id' => $user->id,
                'book_id' => $book->id,
                'book_copy_id' => $copy->id,
                'from_date' => now(),
                'valid_till' => now()->addHours($settings->reservationHours()),
                'status' => ReservationStatus::Active,
            ]);

            $copy->update(['reserved' => true]);

            $this->history->recordReservation($reservation, BookHistory::ACTION_RESERVATION, $user);

            return $reservation;
        });

        $this->notifications->reservationCreated($reservation);

        return $reservation;
    }

    /**
     * Oznacava rezervaciju kao realizovanu pozajmicom.
     *
     * Ako je izdata druga kopija od rezervisane, rezervisana kopija se
     * oslobadja. Poziv je bezbedan unutar transakcije koju vodi BorrowService.
     */
    public function fulfill(
        Reservation $reservation,
        ?User $actor = null,
        ?BookCopy $issuedCopy = null,
        ?Borrow $borrow = null,
    ): Reservation {
        if (! $reservation->isActive()) {
            return $reservation;
        }

        return DB::transaction(function () use ($reservation, $actor, $issuedCopy, $borrow): Reservation {
            /** @var Reservation|null $locked */
            $locked = Reservation::query()->lockForUpdate()->find($reservation->id);

            if ($locked === null || ! $locked->isActive()) {
                return $locked ?? $reservation;
            }

            $locked->update([
                'status' => ReservationStatus::Fulfilled,
                'ended_at' => now(),
            ]);

            $reservedCopy = BookCopy::withTrashed()->lockForUpdate()->find($locked->book_copy_id);

            if ($reservedCopy !== null && ($issuedCopy === null || $reservedCopy->id !== $issuedCopy->id)) {
                $reservedCopy->update(['reserved' => false]);
            }

            $this->history->recordReservation(
                $locked,
                BookHistory::ACTION_RESERVATION_FULFILLED,
                $actor,
                [
                    'borrow_id' => $borrow?->id,
                    'issued_book_copy_id' => $issuedCopy?->id,
                    'issued_barcode' => $issuedCopy?->barcode,
                ],
            );

            return $locked;
        });
    }

    /**
     * Otkazuje aktivnu rezervaciju i oslobadja kopiju.
     */
    public function cancel(Reservation $reservation, ?User $actor = null): Reservation
    {
        if (! $reservation->isActive()) {
            return $reservation;
        }

        $cancelled = DB::transaction(function () use ($reservation, $actor): Reservation {
            $reservation->refresh();

            if (! $reservation->isActive()) {
                return $reservation;
            }

            $reservation->update([
                'status' => ReservationStatus::Cancelled,
                'ended_at' => now(),
            ]);

            $copy = BookCopy::withTrashed()->lockForUpdate()->find($reservation->book_copy_id);

            if ($copy !== null) {
                $copy->update(['reserved' => false]);
            }

            $this->history->recordReservation($reservation, BookHistory::ACTION_RESERVATION_CANCEL, $actor);

            return $reservation;
        });

        $this->notifications->reservationCancelled($cancelled, $actor);

        return $cancelled;
    }

    /**
     * Oznacava istekle rezervacije kao `expired` i oslobadja njihove kopije.
     *
     * @return int broj obradjenih rezervacija
     */
    public function expireOverdue(): int
    {
        $processed = 0;

        Reservation::query()
            ->where('status', ReservationStatus::Active)
            ->where('valid_till', '<', now())
            ->orderBy('id')
            ->chunkById(100, function (Collection $reservations) use (&$processed): void {
                $expiredReservations = [];

                DB::transaction(function () use ($reservations, &$processed, &$expiredReservations): void {
                    $now = now();
                    $ids = $reservations->pluck('id');
                    $copyIds = $reservations->pluck('book_copy_id');

                    Reservation::query()
                        ->whereIn('id', $ids)
                        ->where('status', ReservationStatus::Active)
                        ->update([
                            'status' => ReservationStatus::Expired,
                            'ended_at' => $now,
                            'updated_at' => $now,
                        ]);

                    // Kopiju oslobadjamo samo ako na njoj nema druge aktivne
                    // rezervacije (parcijalni unique indeks to i inace cuva).
                    BookCopy::withTrashed()
                        ->whereIn('id', $copyIds)
                        ->whereDoesntHave('reservations', fn ($query) => $query
                            ->where('status', ReservationStatus::Active)
                            ->where('valid_till', '>=', $now))
                        ->update(['reserved' => false]);

                    foreach ($reservations as $reservation) {
                        $this->history->recordReservation(
                            $reservation,
                            BookHistory::ACTION_RESERVATION_EXPIRED,
                            null,
                            ['status' => ReservationStatus::Expired->value, 'reason' => 'expired'],
                        );

                        $expiredReservations[] = $reservation;
                    }

                    $processed += $ids->count();
                });

                foreach ($expiredReservations as $reservation) {
                    $this->notifications->reservationExpired($reservation);
                }
            });

        return $processed;
    }

    private function hasActiveReservation(User $user, Book $book, Library $library): bool
    {
        return Reservation::query()
            ->where('user_id', $user->id)
            ->where('book_id', $book->id)
            ->where('library_id', $library->id)
            ->where('status', ReservationStatus::Active)
            ->where('valid_till', '>', now())
            ->exists();
    }

    private function findAvailableCopy(Book $book, Library $library): ?BookCopy
    {
        return BookCopy::query()
            ->where('book_id', $book->id)
            ->where('library_id', $library->id)
            ->where('borrowed', false)
            ->where('reserved', false)
            ->where('rec_error', false)
            ->whereDoesntHave('writeOffs', fn ($query) => $query->whereNull('cancelled_at'))
            ->orderByRaw('CAST(order_number AS BIGINT)')
            ->orderBy('id')
            ->lockForUpdate()
            ->first();
    }
}
