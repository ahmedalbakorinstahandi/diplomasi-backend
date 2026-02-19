<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('billing:process-renewals')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

Schedule::command('billing:send-notifications')->everyFiveMinutes()->withoutOverlapping();

Schedule::command('billing:sync-artifacts')->everyFiveMinutes()->withoutOverlapping();

