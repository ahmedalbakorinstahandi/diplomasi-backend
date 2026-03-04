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

Schedule::command('billing:send-ending-reminders --days=3')->dailyAt('09:00')->withoutOverlapping();

Schedule::command('users:mark-inactive --minutes=2 --limit=2000')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('users:send-reengagement-reminders --window=10 --limit=500')
    ->everyTenMinutes()
    ->withoutOverlapping();

