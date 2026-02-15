<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule subscription housekeeping (hourly to process period-end cancellations promptly)
Schedule::command('subscriptions:process-renewals')->hourly();

// Schedule Stripe subscription sync (hourly)
Schedule::command('subscriptions:sync-stripe')->hourly();
