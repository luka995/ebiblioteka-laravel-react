<?php

namespace App\Console\Commands;

use App\Enums\ReservationStatus;
use App\Models\Borrow;
use App\Models\Library;
use App\Models\Reservation;
use App\Services\Notification\CirculationNotificationService;
use Illuminate\Console\Command;

class NotifyStaffDailyDigest extends Command
{
    protected $signature = 'notifications:staff-daily-digest';

    protected $description = 'Posalji bibliotekarima dnevni pregled vracanja i rezervacija';

    public function handle(CirculationNotificationService $notifications): int
    {
        $processed = 0;
        $now = now();
        $today = $now->copy()->startOfDay();

        Library::query()
            ->where('deleted', false)
            ->with('settings')
            ->orderBy('id')
            ->chunk(50, function ($libraries) use ($notifications, $now, $today, &$processed): void {
                foreach ($libraries as $library) {
                    $settings = $library->settings;
                    $loanReminderDays = $settings?->loanReminderDays() ?? 3;
                    $reservationReminderHours = $settings?->reservationReminderHours() ?? 24;

                    $loansDue = Borrow::query()
                        ->with(['user', 'book.authors', 'bookCopy', 'library'])
                        ->where('library_id', $library->id)
                        ->where('returned', false)
                        ->whereDate('valid_till', $today->copy()->addDays($loanReminderDays))
                        ->get();

                    $loansOverdue = Borrow::query()
                        ->with(['user', 'book.authors', 'bookCopy', 'library'])
                        ->where('library_id', $library->id)
                        ->where('returned', false)
                        ->where('valid_till', '<', $today)
                        ->orderBy('valid_till')
                        ->get();

                    $reservationsExpiring = Reservation::query()
                        ->with(['user', 'book.authors', 'bookCopy', 'library'])
                        ->where('library_id', $library->id)
                        ->where('status', ReservationStatus::Active)
                        ->where('valid_till', '>', $now)
                        ->where('valid_till', '<=', $now->copy()->addHours($reservationReminderHours))
                        ->orderBy('valid_till')
                        ->get();

                    if ($loansDue->isEmpty() && $loansOverdue->isEmpty() && $reservationsExpiring->isEmpty()) {
                        continue;
                    }

                    $notifications->sendStaffDailyDigest($library, $loansDue, $loansOverdue, $reservationsExpiring);
                    $processed++;
                }
            });

        $this->info("Obradjeno biblioteka za dnevni digest: {$processed}");

        return self::SUCCESS;
    }
}
