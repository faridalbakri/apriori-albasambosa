<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('orders:sync-pending')->everyFifteenMinutes();

Schedule::command('orders:expire-pending')->hourly();

// Apriori: run monthly on the 1st at 01:00 WIB
Schedule::command('apriori:mine')
    ->monthlyOn(1, '1:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->onOneServer();

// Anonimisasi: run monthly on the 1st at 01:00 WIB
Schedule::command('privacy:anonymize-guests')
    ->monthlyOn(1, '1:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('privacy:anonymize-registered')
    ->monthlyOn(1, '1:10')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->onOneServer();
