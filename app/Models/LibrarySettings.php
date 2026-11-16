<?php

namespace App\Models;

use Database\Factories\LibrarySettingsFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'library_id',
    'reservation_hours',
    'reservation_reminder_hours',
    'lend_days',
    'loan_reminder_days',
    'overdue_reminder_days',
    'overdue_price_per_day',
    'notify_staff_on_reservation',
    'notify_staff_on_issue',
    'notify_staff_on_return',
    'notify_staff_on_cancellation',
    'notify_staff_daily_digest',
    'notify_user_reservation_expiry',
    'notify_user_loan_due',
])]
class LibrarySettings extends Model
{
    /** @use HasFactory<LibrarySettingsFactory> */
    use HasFactory;

    public const DEFAULT_RESERVATION_HOURS = 48;

    public const DEFAULT_LEND_DAYS = 30;

    public const DEFAULT_OVERDUE_PRICE_PER_DAY = 0;

    public const DEFAULT_RESERVATION_REMINDER_HOURS = 24;

    public const DEFAULT_LOAN_REMINDER_DAYS = 3;

    public const DEFAULT_OVERDUE_REMINDER_DAYS = 3;

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'reservation_hours' => self::DEFAULT_RESERVATION_HOURS,
            'reservation_reminder_hours' => self::DEFAULT_RESERVATION_REMINDER_HOURS,
            'lend_days' => self::DEFAULT_LEND_DAYS,
            'loan_reminder_days' => self::DEFAULT_LOAN_REMINDER_DAYS,
            'overdue_reminder_days' => self::DEFAULT_OVERDUE_REMINDER_DAYS,
            'overdue_price_per_day' => self::DEFAULT_OVERDUE_PRICE_PER_DAY,
            'notify_staff_on_reservation' => true,
            'notify_staff_on_issue' => false,
            'notify_staff_on_return' => false,
            'notify_staff_on_cancellation' => true,
            'notify_staff_daily_digest' => true,
            'notify_user_reservation_expiry' => true,
            'notify_user_loan_due' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'reservation_hours' => 'integer',
            'reservation_reminder_hours' => 'integer',
            'lend_days' => 'integer',
            'loan_reminder_days' => 'integer',
            'overdue_reminder_days' => 'integer',
            'overdue_price_per_day' => 'decimal:2',
            'notify_staff_on_reservation' => 'boolean',
            'notify_staff_on_issue' => 'boolean',
            'notify_staff_on_return' => 'boolean',
            'notify_staff_on_cancellation' => 'boolean',
            'notify_staff_daily_digest' => 'boolean',
            'notify_user_reservation_expiry' => 'boolean',
            'notify_user_loan_due' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Library, $this>
     */
    public function library(): BelongsTo
    {
        return $this->belongsTo(Library::class);
    }

    public function reservationHours(): int
    {
        $hours = (int) $this->reservation_hours;

        return $hours > 0 ? $hours : self::DEFAULT_RESERVATION_HOURS;
    }

    public function reservationReminderHours(): int
    {
        $hours = (int) $this->reservation_reminder_hours;

        return $hours > 0 ? $hours : self::DEFAULT_RESERVATION_REMINDER_HOURS;
    }

    public function lendDays(): int
    {
        $days = (int) $this->lend_days;

        return $days > 0 ? $days : self::DEFAULT_LEND_DAYS;
    }

    public function loanReminderDays(): int
    {
        $days = (int) $this->loan_reminder_days;

        return $days > 0 ? $days : self::DEFAULT_LOAN_REMINDER_DAYS;
    }

    public function overdueReminderDays(): int
    {
        $days = (int) $this->overdue_reminder_days;

        return $days > 0 ? $days : self::DEFAULT_OVERDUE_REMINDER_DAYS;
    }

    public function overduePricePerDay(): float
    {
        return max(0, (float) $this->overdue_price_per_day);
    }
}
