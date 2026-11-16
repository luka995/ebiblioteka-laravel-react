<?php

namespace App\Console\Commands;

use App\Services\ReservationService;
use Illuminate\Console\Command;

class ExpireReservations extends Command
{
    protected $signature = 'reservations:expire';

    protected $description = 'Oznaci istekle rezervacije i oslobodi njihove fizicke jedinice';

    public function handle(ReservationService $reservations): int
    {
        $processed = $reservations->expireOverdue();

        $this->info("Obradjeno isteklih rezervacija: {$processed}");

        return self::SUCCESS;
    }
}
