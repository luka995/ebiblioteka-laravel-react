<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Rezervacija cuva kopiju do `valid_till`; po isteku je potrebno osloboditi
// kopiju i oznaciti rezervaciju kao istoriju.
Schedule::command('reservations:expire')
    ->everyMinute()
    ->withoutOverlapping(10);

Schedule::command('notifications:reservation-expiring')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('notifications:user-loans-due')
    ->dailyAt('08:00')
    ->timezone('Europe/Belgrade')
    ->withoutOverlapping();

Schedule::command('notifications:staff-daily-digest')
    ->dailyAt('08:00')
    ->timezone('Europe/Belgrade')
    ->withoutOverlapping();

// Automatska aktivacija nove skolske godine: 1. septembar u 00:00 UTC.
Schedule::command('school-years:activate')
    ->yearlyOn(9, 1, '00:00')
    ->timezone('UTC')
    ->withoutOverlapping();

// Podsetnik osoblju sedam dana pre pocetka nove godine. Scheduler proverava
// samo prozor 25.08-01.09, a komanda i dalje proverava deduplikaciju.
Schedule::command('school-years:notify-upcoming')
    ->dailyAt('08:00')
    ->timezone('Europe/Belgrade')
    ->when(function (): bool {
        $today = today('Europe/Belgrade');
        $windowStart = $today->copy()->setMonth(8)->setDay(25);
        $windowEnd = $today->copy()->setMonth(9)->setDay(1);

        return $today->betweenIncluded($windowStart, $windowEnd);
    })
    ->withoutOverlapping();
