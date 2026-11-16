<?php

namespace App\Services\Notification;

use App\Enums\CirculationNotificationType;
use App\Enums\LibraryRole;
use App\Enums\LoanReminderWindow;
use App\Jobs\SendCirculationNotification;
use App\Mail\CirculationNotificationMail;
use App\Models\Borrow;
use App\Models\Library;
use App\Models\LibrarySettings;
use App\Models\NotificationDelivery;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Priprema i queue-uje email notifikacije za cirkulaciju.
 *
 * Email se uvek queue-uje; poziv se izvrsava posle commit-a transakcije
 * koja je promenila stanje rezervacije ili pozajmice.
 */
class CirculationNotificationService
{
    public function __construct(private readonly NotificationDeliveryService $deliveries) {}

    public function reservationCreated(Reservation $reservation): void
    {
        $reservation->loadMissing(['library.settings', 'user', 'book.authors', 'bookCopy']);

        $library = $reservation->library;
        $user = $reservation->user;

        if ($library === null || $user === null) {
            return;
        }

        $data = $this->reservationData($reservation);

        $this->queueForUser(
            $user,
            CirculationNotificationType::ReservationCreatedUser,
            $data,
            "reservation-created:{$reservation->id}:user",
            $library,
            $reservation,
        );

        if ($this->settings($library)->notify_staff_on_reservation) {
            $this->queueForStaff(
                $library,
                CirculationNotificationType::ReservationCreatedStaff,
                $data,
                "reservation-created:{$reservation->id}:staff",
                $reservation,
            );
        }
    }

    public function reservationCancelled(Reservation $reservation, ?User $actor = null): void
    {
        $reservation->loadMissing(['library.settings', 'user', 'book.authors', 'bookCopy']);

        $library = $reservation->library;
        $user = $reservation->user;

        if ($library === null || $user === null) {
            return;
        }

        $data = array_merge($this->reservationData($reservation), ['actor' => $actor]);
        $cancelledByOwner = $actor === null || (int) $actor->id === (int) $user->id;

        $this->queueForUser(
            $user,
            CirculationNotificationType::ReservationCancelledUser,
            $data,
            "reservation-cancelled:{$reservation->id}:user",
            $library,
            $reservation,
        );

        if ($cancelledByOwner && $this->settings($library)->notify_staff_on_cancellation) {
            $this->queueForStaff(
                $library,
                CirculationNotificationType::ReservationCancelledStaff,
                $data,
                "reservation-cancelled:{$reservation->id}:staff",
                $reservation,
            );
        }
    }

    public function reservationExpiring(Reservation $reservation): void
    {
        $reservation->loadMissing(['library.settings', 'user', 'book.authors', 'bookCopy']);

        if ($reservation->library === null || $reservation->user === null) {
            return;
        }

        $data = $this->reservationData($reservation);

        if (! $this->settings($reservation->library)->notify_user_reservation_expiry) {
            return;
        }

        $this->queueForUser(
            $reservation->user,
            CirculationNotificationType::ReservationExpiringUser,
            $data,
            "reservation-expiring:{$reservation->id}",
            $reservation->library,
            $reservation,
        );
    }

    public function reservationExpired(Reservation $reservation): void
    {
        $reservation->loadMissing(['library.settings', 'user', 'book.authors', 'bookCopy']);

        if ($reservation->library === null || $reservation->user === null) {
            return;
        }

        $data = $this->reservationData($reservation);

        if (! $this->settings($reservation->library)->notify_user_reservation_expiry) {
            return;
        }

        $this->queueForUser(
            $reservation->user,
            CirculationNotificationType::ReservationExpiredUser,
            $data,
            "reservation-expired:{$reservation->id}",
            $reservation->library,
            $reservation,
        );
    }

    public function loanIssued(Borrow $borrow): void
    {
        $borrow->loadMissing(['library.settings', 'user', 'book.authors', 'bookCopy']);

        if ($borrow->library === null || $borrow->user === null) {
            return;
        }

        $data = $this->loanData($borrow);

        $this->queueForUser(
            $borrow->user,
            CirculationNotificationType::LoanIssuedUser,
            $data,
            "loan-issued:{$borrow->id}:user",
            $borrow->library,
            $borrow,
        );

        if ($this->settings($borrow->library)->notify_staff_on_issue) {
            $this->queueForStaff(
                $borrow->library,
                CirculationNotificationType::LoanIssuedStaff,
                $data,
                "loan-issued:{$borrow->id}:staff",
                $borrow,
            );
        }
    }

    public function loanReturned(Borrow $borrow): void
    {
        $borrow->loadMissing(['library.settings', 'user', 'book.authors', 'bookCopy']);

        if ($borrow->library === null || $borrow->user === null) {
            return;
        }

        $data = $this->loanData($borrow);

        $this->queueForUser(
            $borrow->user,
            CirculationNotificationType::LoanReturnedUser,
            $data,
            "loan-returned:{$borrow->id}:user",
            $borrow->library,
            $borrow,
        );

        if ($this->settings($borrow->library)->notify_staff_on_return) {
            $this->queueForStaff(
                $borrow->library,
                CirculationNotificationType::LoanReturnedStaff,
                $data,
                "loan-returned:{$borrow->id}:staff",
                $borrow,
            );
        }
    }

    /**
     * Salje jedan email korisniku sa listom pozajmica u istom prozoru.
     *
     * @param  Collection<int, Borrow>  $loans
     */
    public function sendUserLoanReminder(User $user, Collection $loans, LoanReminderWindow $window): void
    {
        if ($user->email === null || $user->email === '' || $loans->isEmpty()) {
            return;
        }

        $type = match ($window) {
            LoanReminderWindow::Soon => CirculationNotificationType::LoanDueSoonUser,
            LoanReminderWindow::Today => CirculationNotificationType::LoanDueTodayUser,
            LoanReminderWindow::Overdue => CirculationNotificationType::LoanOverdueUser,
        };

        $claimed = [];
        $claimedLoans = collect();

        foreach ($loans as $loan) {
            $loan->loadMissing(['library.settings', 'book.authors', 'bookCopy']);

            if (! ($loan->library?->settings?->notify_user_loan_due ?? true)) {
                continue;
            }

            if ($window === LoanReminderWindow::Overdue) {
                $settings = $loan->library?->settings;
                $interval = max(1, $settings?->overdueReminderDays() ?? LibrarySettings::DEFAULT_OVERDUE_REMINDER_DAYS);
                $daysOverdue = max(1, $loan->valid_till->startOfDay()->diffInDays(now()->startOfDay()));
                $suffix = 'overdue:'.(int) ceil($daysOverdue / $interval);
            } else {
                $suffix = $window->value;
            }

            $delivery = $this->deliveries->claim(
                $type,
                "loan-due:{$loan->id}:{$suffix}",
                $user,
                $loan->library,
                $loan,
                ['window' => $window->value],
            );

            if ($delivery !== null) {
                $claimed[] = $delivery;
                $claimedLoans->push($loan);
            }
        }

        if ($claimedLoans->isEmpty()) {
            return;
        }

        try {
            SendCirculationNotification::dispatch(
                array_map(fn (NotificationDelivery $delivery): int => $delivery->id, $claimed),
                new CirculationNotificationMail($user, $type, [
                    'loans' => $claimedLoans,
                    'window' => $window->value,
                    'user' => $user,
                ]),
            );
        } catch (Throwable $exception) {
            foreach ($claimed as $delivery) {
                $this->deliveries->markFailed($delivery, $exception->getMessage());
            }

            report($exception);
        }
    }

    /**
     * @param  Collection<int, Borrow>  $loansDue
     * @param  Collection<int, Borrow>  $loansOverdue
     * @param  Collection<int, Reservation>  $reservationsExpiring
     */
    public function sendStaffDailyDigest(
        Library $library,
        Collection $loansDue,
        Collection $loansOverdue,
        Collection $reservationsExpiring,
    ): void {
        if ($loansDue->isEmpty() && $loansOverdue->isEmpty() && $reservationsExpiring->isEmpty()) {
            return;
        }

        if (! $this->settings($library)->notify_staff_daily_digest) {
            return;
        }

        $data = [
            'library' => $library,
            'loans_due' => $loansDue,
            'loans_overdue' => $loansOverdue,
            'reservations_expiring' => $reservationsExpiring,
            'date' => now(),
        ];

        foreach ($this->staffRecipients($library) as $staff) {
            $this->queueForUser(
                $staff,
                CirculationNotificationType::StaffDailyDigest,
                $data,
                'staff-digest:'.$library->id.':'.now()->toDateString().':'.$staff->id,
                $library,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function queueForUser(
        User $recipient,
        CirculationNotificationType $type,
        array $data,
        string $dedupeKey,
        ?Library $library = null,
        ?Model $related = null,
    ): void {
        if ($recipient->email === null || $recipient->email === '') {
            return;
        }

        $delivery = $this->deliveries->claim($type, $dedupeKey, $recipient, $library, $related);

        if ($delivery === null) {
            return;
        }

        try {
            SendCirculationNotification::dispatch(
                [$delivery->id],
                new CirculationNotificationMail($recipient, $type, $data),
            );
        } catch (Throwable $exception) {
            $this->deliveries->markFailed($delivery, $exception->getMessage());
            report($exception);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function queueForStaff(
        Library $library,
        CirculationNotificationType $type,
        array $data,
        string $dedupePrefix,
        ?Model $related = null,
    ): void {
        foreach ($this->staffRecipients($library) as $staff) {
            $this->queueForUser(
                $staff,
                $type,
                $data,
                $dedupePrefix.':'.$staff->id,
                $library,
                $related,
            );
        }
    }

    /**
     * @return Collection<int, User>
     */
    private function staffRecipients(Library $library): Collection
    {
        return User::query()
            ->whereNotNull('email')
            ->where('email', '<>', '')
            ->whereHas('libraries', function ($query) use ($library): void {
                $query->whereKey($library->id)
                    ->whereIn('library_user.role', [
                        LibraryRole::Librarian->value,
                        LibraryRole::LibraryAdmin->value,
                    ]);
            })
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function reservationData(Reservation $reservation): array
    {
        return [
            'reservation' => $reservation,
            'library' => $reservation->library,
            'user' => $reservation->user,
            'book' => $reservation->book,
            'copy' => $reservation->bookCopy,
            'valid_till' => $reservation->valid_till,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function loanData(Borrow $borrow): array
    {
        return [
            'loan' => $borrow,
            'library' => $borrow->library,
            'user' => $borrow->user,
            'book' => $borrow->book,
            'copy' => $borrow->bookCopy,
            'from_date' => $borrow->from_date,
            'valid_till' => $borrow->valid_till,
            'return_date' => $borrow->return_date,
            'overdue_days' => $borrow->overdue_days,
            'overdue_amount' => $borrow->overdue_amount,
        ];
    }

    private function settings(Library $library): LibrarySettings
    {
        return $library->settings()->firstOrCreate([], LibrarySettings::defaults());
    }
}
