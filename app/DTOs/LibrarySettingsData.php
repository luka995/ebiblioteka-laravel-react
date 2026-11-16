<?php

namespace App\DTOs;

use App\Models\LibrarySettings;

final readonly class LibrarySettingsData
{
    public function __construct(
        public int $reservationHours,
        public int $reservationReminderHours,
        public int $lendDays,
        public int $loanReminderDays,
        public int $overdueReminderDays,
        public float $overduePricePerDay,
        public bool $notifyStaffOnReservation,
        public bool $notifyStaffOnIssue,
        public bool $notifyStaffOnReturn,
        public bool $notifyStaffOnCancellation,
        public bool $notifyStaffDailyDigest,
        public bool $notifyUserReservationExpiry,
        public bool $notifyUserLoanDue,
    ) {}

    public static function fromSettings(?LibrarySettings $settings): self
    {
        return new self(
            reservationHours: $settings?->reservationHours() ?? LibrarySettings::DEFAULT_RESERVATION_HOURS,
            reservationReminderHours: $settings?->reservationReminderHours() ?? LibrarySettings::DEFAULT_RESERVATION_REMINDER_HOURS,
            lendDays: $settings?->lendDays() ?? LibrarySettings::DEFAULT_LEND_DAYS,
            loanReminderDays: $settings?->loanReminderDays() ?? LibrarySettings::DEFAULT_LOAN_REMINDER_DAYS,
            overdueReminderDays: $settings?->overdueReminderDays() ?? LibrarySettings::DEFAULT_OVERDUE_REMINDER_DAYS,
            overduePricePerDay: $settings?->overduePricePerDay() ?? LibrarySettings::DEFAULT_OVERDUE_PRICE_PER_DAY,
            notifyStaffOnReservation: $settings?->notify_staff_on_reservation ?? true,
            notifyStaffOnIssue: $settings?->notify_staff_on_issue ?? false,
            notifyStaffOnReturn: $settings?->notify_staff_on_return ?? false,
            notifyStaffOnCancellation: $settings?->notify_staff_on_cancellation ?? true,
            notifyStaffDailyDigest: $settings?->notify_staff_daily_digest ?? true,
            notifyUserReservationExpiry: $settings?->notify_user_reservation_expiry ?? true,
            notifyUserLoanDue: $settings?->notify_user_loan_due ?? true,
        );
    }

    /**
     * @return array<string, int|float|bool>
     */
    public function toArray(): array
    {
        return [
            'reservation_hours' => $this->reservationHours,
            'reservation_reminder_hours' => $this->reservationReminderHours,
            'lend_days' => $this->lendDays,
            'loan_reminder_days' => $this->loanReminderDays,
            'overdue_reminder_days' => $this->overdueReminderDays,
            'overdue_price_per_day' => $this->overduePricePerDay,
            'notify_staff_on_reservation' => $this->notifyStaffOnReservation,
            'notify_staff_on_issue' => $this->notifyStaffOnIssue,
            'notify_staff_on_return' => $this->notifyStaffOnReturn,
            'notify_staff_on_cancellation' => $this->notifyStaffOnCancellation,
            'notify_staff_daily_digest' => $this->notifyStaffDailyDigest,
            'notify_user_reservation_expiry' => $this->notifyUserReservationExpiry,
            'notify_user_loan_due' => $this->notifyUserLoanDue,
        ];
    }
}
