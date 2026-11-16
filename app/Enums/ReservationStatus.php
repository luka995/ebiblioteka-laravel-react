<?php

namespace App\Enums;

use App\Enums\Traits\EnumToArray;

/**
 * Zivotni ciklus rezervacije fizicke jedinice.
 *
 * Legacy `reservation.history` flag je ovde zamenjen statusom:
 * - Active    -> rezervacija jos traje, kopija je rezervisana;
 * - Cancelled -> korisnik ili osoblje je ponistilo rezervaciju;
 * - Expired   -> istekao je rok drzanja kopije;
 * - Fulfilled -> rezervacija je realizovana pozajmicom.
 */
enum ReservationStatus: string
{
    use EnumToArray;

    case Active = 'active';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Fulfilled = 'fulfilled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active => __('reservations.status.active'),
            self::Cancelled => __('reservations.status.cancelled'),
            self::Expired => __('reservations.status.expired'),
            self::Fulfilled => __('reservations.status.fulfilled'),
        };
    }

    public function isHistory(): bool
    {
        return $this !== self::Active;
    }
}
