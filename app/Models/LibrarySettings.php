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
    'lend_days',
    'overdue_price_per_day',
])]
class LibrarySettings extends Model
{
    /** @use HasFactory<LibrarySettingsFactory> */
    use HasFactory;

    public const DEFAULT_RESERVATION_HOURS = 48;

    public const DEFAULT_LEND_DAYS = 30;

    public const DEFAULT_OVERDUE_PRICE_PER_DAY = 0;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'reservation_hours' => 'integer',
            'lend_days' => 'integer',
            'overdue_price_per_day' => 'decimal:2',
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

    public function lendDays(): int
    {
        $days = (int) $this->lend_days;

        return $days > 0 ? $days : self::DEFAULT_LEND_DAYS;
    }

    public function overduePricePerDay(): float
    {
        return max(0, (float) $this->overdue_price_per_day);
    }
}
