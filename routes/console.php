<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Run with: php artisan schedule:work   (or a cron calling schedule:run every minute)
Schedule::command('tron:watch')->everyMinute()->withoutOverlapping();
Schedule::command('tron:sync-blacklist')->hourly()->withoutOverlapping();
