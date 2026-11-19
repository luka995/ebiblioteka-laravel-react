<?php

namespace App\Console\Commands;

use App\Services\SchoolYearService;
use Illuminate\Console\Command;

class ActivateSchoolYear extends Command
{
    protected $signature = 'school-years:activate';

    protected $description = 'Aktivira skolsku godinu koja je pocela (automatski rezim)';

    public function handle(SchoolYearService $years): int
    {
        if (! $years->automaticActivationEnabled()) {
            $this->info('Automatska aktivacija skolske godine nije ukljucena.');

            return self::SUCCESS;
        }

        $activated = $years->activateDueYears();

        if ($activated === null) {
            $this->info('Nema skolske godine koju treba aktivirati.');

            return self::SUCCESS;
        }

        $this->info("Aktivirana skolska godina: {$activated->name}");

        return self::SUCCESS;
    }
}
