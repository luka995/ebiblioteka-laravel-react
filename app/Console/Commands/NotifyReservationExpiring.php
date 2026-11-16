<?php

namespace App\Console\Commands;

use App\Enums\ReservationStatus;
use App\Models\LibrarySettings;
use App\Models\Reservation;
use App\Services\Notification\CirculationNotificationService;
use Illuminate\Console\Command;

class NotifyReservationExpiring extends Command
{
    protected $signature = 'notifications:reservation-expiring';

    protected $description = 'Posalji korisnicima podsetnik pre isteka rezervacije';

    public function handle(CirculationNotificationService $notifications): int
    {
        $processed = 0;
        $now = now();

        Reservation::query()
            ->with(['user', 'book.authors', 'bookCopy', 'library.settings'])
            ->where('status', ReservationStatus::Active)
            ->where('valid_till', '>', $now)
            ->where('valid_till', '<=', $now->copy()->addDays(31))
            ->orderBy('id')
            ->chunkById(100, function ($reservations) use ($notifications, $now, &$processed): void {
                foreach ($reservations as $reservation) {
                    $settings = $reservation->library?->settings;
                    $hours = $settings?->reservationReminderHours()
                        ?? LibrarySettings::DEFAULT_RESERVATION_REMINDER_HOURS;

                    if ($reservation->valid_till->lte($now->copy()->addHours($hours))) {
                        $notifications->reservationExpiring($reservation);
                        $processed++;
                    }
                }
            });

        $this->info("Obradjeno podsetnika za rezervacije: {$processed}");

        return self::SUCCESS;
    }
}
