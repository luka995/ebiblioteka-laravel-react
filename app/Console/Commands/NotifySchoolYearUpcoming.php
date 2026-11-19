<?php

namespace App\Console\Commands;

use App\Services\Notification\SchoolYearNotificationService;
use App\Services\SchoolYearAutoCreationService;
use App\Services\SchoolYearService;
use Illuminate\Console\Command;

class NotifySchoolYearUpcoming extends Command
{
    protected $signature = 'school-years:notify-upcoming';

    protected $description = 'Obavestava osoblje sedam dana pre automatske aktivacije nove skolske godine';

    public function handle(
        SchoolYearService $yearsService,
        SchoolYearAutoCreationService $autoCreation,
        SchoolYearNotificationService $notifications,
    ): int {

        $active = $yearsService->active();

        if ($active === null) {
            $this->info('Nema aktivne skolske godine.');

            return self::SUCCESS;
        }

        $next = $autoCreation->ensureNextYear();

        if ($yearsService->isActive($next)) {
            $this->info('Naredna skolska godina je vec aktivna.');

            return self::SUCCESS;
        }

        // The reminder window covers the seven days before activation, including the activation day.
        $daysUntil = (int) now()->startOfDay()->diffInDays($next->starts_on->copy()->startOfDay(), false);

        if ($daysUntil < 0 || $daysUntil > 7) {
            $this->info("Naredna skolska godina pocinje za {$daysUntil} dana; podsetnik se ne salje.");

            return self::SUCCESS;
        }

        $sent = $notifications->upcomingActivation($next, $active, $daysUntil);

        $this->info("Poslato podsetnika za skolsku godinu {$next->name}: {$sent}");

        return self::SUCCESS;
    }
}
