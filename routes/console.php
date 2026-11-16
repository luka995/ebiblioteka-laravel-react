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
