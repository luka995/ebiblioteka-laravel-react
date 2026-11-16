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
