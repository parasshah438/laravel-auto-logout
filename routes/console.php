<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Process queued email jobs through scheduler (triggered by server cron).
Schedule::command('queue:work --queue=emails --stop-when-empty --tries=3 --sleep=1')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();
