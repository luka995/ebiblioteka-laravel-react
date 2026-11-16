<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Exceptions\ReservationExchangeRequiredException;
use App\Models\BookCopy;
use App\Models\BookHistory;
use App\Models\Borrow;
use App\Models\Library;
use App\Models\LibrarySettings;
use App\Models\Reservation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Domenska logika izdavanja i razduzivanja fizickih jedinica.
 *
 * Pozajmica moze nastati bez rezervacije ili realizacijom postojece
 * rezervacije. Sve promene stanja kopije, rezervacije i audit zapisa se
 * izvrsavaju u jednoj transakciji.
 */
class BorrowService
{
    public function __construct(
        private readonly BookHistoryService $history,
        private readonly ReservationService $reservations,
    ) {}

    /**
     * Izdaje fizicku jedinicu korisniku.
     *
     * Ako korisnik ima aktivnu rezervaciju istog naslova, ali je skenirana
     * druga kopija, izdavanje se ne izvrsava dok osoblje ne potvrdi zamenu
     * (`$confirmReservationExchange`).
     *
     * @throws ValidationException
     * @throws ReservationExchangeRequiredException
     */
    public function issue(
        User $actor,
        Library $library,
        User $member,
        BookCopy $copy,
        ?string $validTill = null,
        bool $confirmReservationExchange = false,
    ): Borrow {
        $this->ensureStaff($actor, $library);

        if (! $member->isMemberOf($library->id)) {
            throw ValidationException::withMessages([
                'user_id' => __('validation.custom.borrow_user_not_member'),
            ]);
        }

        $this->reservations->expireOverdue();

        return DB::transaction(function () use (
            $actor,
            $library,
            $member,
            $copy,
            $validTill,
            $confirmReservationExchange,
        ): Borrow {
            /** @var BookCopy|null $lockedCopy */
            $lockedCopy = BookCopy::withTrashed()->lockForUpdate()->find($copy->id);

            $this->ensureCopyBorrowable($lockedCopy, $library);

            $reservation = $this->findActiveReservationForCopy($lockedCopy);

            if ($reservation !== null && (int) $reservation->user_id !== (int) $member->id) {
                throw ValidationException::withMessages([
                    'barcode' => __('validation.custom.borrow_copy_reserved_for_other', [
                        'name' => $reservation->user?->displayName() ?? '—',
                        'date' => $reservation->valid_till?->format('d.m.Y. H:i') ?? '—',
                    ]),
                ]);
            }

            if ($reservation === null) {
                $reservation = $this->findActiveReservationForUserAndBook($member, $lockedCopy);

                if ($reservation !== null && (int) $reservation->book_copy_id !== (int) $lockedCopy->id) {
                    if (! $confirmReservationExchange) {
                        throw new ReservationExchangeRequiredException(
                            $reservation->loadMissing(['book', 'bookCopy', 'user'])
                        );
                    }
                } elseif ($reservation !== null) {
                    // Ista kopija, ali bez aktivne veze na kopiji (odbrambeno).
                    $reservation = null;
                }
            }

            $settings = $this->settings($library);
            $dueDate = $validTill !== null
                ? Carbon::parse($validTill)->endOfDay()
                : now()->addDays($settings->lendDays())->endOfDay();

            if ($dueDate->isPast()) {
                throw ValidationException::withMessages([
                    'valid_till' => __('validation.custom.borrow_valid_till_past'),
                ]);
            }

            $borrow = Borrow::create([
                'library_id' => $library->id,
                'user_id' => $member->id,
                'book_id' => $lockedCopy->book_id,
                'book_copy_id' => $lockedCopy->id,
                'reservation_id' => $reservation?->id,
                'created_by_id' => $actor->id,
                'from_date' => now(),
                'valid_till' => $dueDate,
                'returned' => false,
            ]);

            if ($reservation !== null) {
                $this->reservations->fulfill($reservation, $actor, $lockedCopy, $borrow);
            }

            $lockedCopy->update([
                'borrowed' => true,
                'reserved' => false,
            ]);

            $this->history->recordBorrow($borrow, BookHistory::ACTION_BORROW, $actor);

            return $borrow->load($this->relations());
        });
    }

    /**
     * Realizuje aktivnu rezervaciju i izdaje rezervisani primerak.
     *
     * @throws ValidationException
     */
    public function issueFromReservation(
        User $actor,
        Reservation $reservation,
        ?string $validTill = null,
    ): Borrow {
        $reservation->loadMissing(['library', 'user', 'book', 'bookCopy']);

        if (! $reservation->isActive() || $reservation->valid_till->isPast()) {
            $this->reservations->expireOverdue();

            throw ValidationException::withMessages([
                'reservation' => __('validation.custom.reservation_not_active'),
            ]);
        }

        if ($reservation->library === null || $reservation->bookCopy === null) {
            throw ValidationException::withMessages([
                'reservation' => __('validation.custom.reservation_not_active'),
            ]);
        }

        return $this->issue(
            $actor,
            $reservation->library,
            $reservation->user,
            $reservation->bookCopy,
            $validTill,
            true,
        );
    }

    /**
     * Razduzuje pozajmicu i obracunava eventualno prekoracenje.
     *
     * @throws ValidationException
     */
    public function returnBook(User $actor, Borrow $borrow): Borrow
    {
        $this->ensureStaff($actor, $borrow->library);

        return DB::transaction(function () use ($actor, $borrow): Borrow {
            /** @var Borrow|null $locked */
            $locked = Borrow::query()->lockForUpdate()->find($borrow->id);

            if ($locked === null) {
                throw ValidationException::withMessages([
                    'borrow' => __('validation.custom.borrow_not_found'),
                ]);
            }

            if ($locked->returned) {
                throw ValidationException::withMessages([
                    'borrow' => __('validation.custom.borrow_already_returned'),
                ]);
            }

            $returnDate = now();
            $overdueDays = $locked->calculateOverdueDays($returnDate);
            $overdueAmount = round($overdueDays * $this->settings($locked->library)->overduePricePerDay(), 2);

            $locked->update([
                'returned' => true,
                'return_date' => $returnDate,
                'overdue_days' => $overdueDays,
                'overdue_amount' => $overdueAmount,
            ]);

            $copy = BookCopy::withTrashed()->lockForUpdate()->find($locked->book_copy_id);

            if ($copy !== null) {
                $copy->update(['borrowed' => false]);
            }

            $this->history->recordBorrow(
                $locked,
                BookHistory::ACTION_RETURN,
                $actor,
                [
                    'overdue_days' => $overdueDays,
                    'overdue_amount' => $overdueAmount,
                ],
            );

            return $locked->load($this->relations());
        });
    }

    /**
     * @return array<int, string>
     */
    private function relations(): array
    {
        return [
            'library.settings',
            'user',
            'book.authors',
            'bookCopy',
            'reservation',
            'createdBy',
        ];
    }

    private function ensureStaff(User $actor, ?Library $library): void
    {
        if ($library === null) {
            throw ValidationException::withMessages([
                'library_id' => __('validation.custom.active_library_required'),
            ]);
        }

        if (! $actor->isSuperAdmin() && ! $actor->isStaffIn($library->id)) {
            throw new AuthorizationException;
        }
    }

    /**
     * @throws ValidationException
     */
    private function ensureCopyBorrowable(?BookCopy $copy, Library $library): void
    {
        if ($copy === null || $copy->trashed()) {
            throw ValidationException::withMessages([
                'barcode' => __('validation.custom.borrow_copy_not_found'),
            ]);
        }

        if ((int) $copy->library_id !== (int) $library->id) {
            throw ValidationException::withMessages([
                'barcode' => __('validation.custom.borrow_copy_library_mismatch'),
            ]);
        }

        if ($copy->borrowed) {
            throw ValidationException::withMessages([
                'barcode' => __('validation.custom.borrow_copy_already_borrowed'),
            ]);
        }

        if ($copy->rec_error) {
            throw ValidationException::withMessages([
                'barcode' => __('validation.custom.borrow_copy_rec_error'),
            ]);
        }

        if ($copy->writeOffs()->whereNull('cancelled_at')->exists()) {
            throw ValidationException::withMessages([
                'barcode' => __('validation.custom.borrow_copy_written_off'),
            ]);
        }
    }

    private function findActiveReservationForCopy(BookCopy $copy): ?Reservation
    {
        return Reservation::query()
            ->with('user')
            ->where('book_copy_id', $copy->id)
            ->where('status', ReservationStatus::Active)
            ->where('valid_till', '>', now())
            ->lockForUpdate()
            ->first();
    }

    private function findActiveReservationForUserAndBook(User $user, BookCopy $copy): ?Reservation
    {
        return Reservation::query()
            ->with(['book', 'bookCopy', 'user'])
            ->where('user_id', $user->id)
            ->where('book_id', $copy->book_id)
            ->where('library_id', $copy->library_id)
            ->where('status', ReservationStatus::Active)
            ->where('valid_till', '>', now())
            ->lockForUpdate()
            ->first();
    }

    private function settings(?Library $library): LibrarySettings
    {
        if ($library === null) {
            throw ValidationException::withMessages([
                'library_id' => __('validation.custom.active_library_required'),
            ]);
        }

        return $library->settings()->firstOrCreate([], [
            'reservation_hours' => LibrarySettings::DEFAULT_RESERVATION_HOURS,
            'lend_days' => LibrarySettings::DEFAULT_LEND_DAYS,
            'overdue_price_per_day' => LibrarySettings::DEFAULT_OVERDUE_PRICE_PER_DAY,
        ]);
    }
}
