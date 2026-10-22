<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Pay scheduled invoices when due, and keep payout statuses in sync with PayPal.
Schedule::command('handlemyap:run-due')->hourly();
Schedule::command('handlemyap:sync-payouts')->everyFiveMinutes();
