<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// JobRadar. Both need `php artisan schedule:run` on a once-a-minute cron.
// Fetching is hourly; each source still decides whether it is due, so a
// publisher with a stricter limit (Remotive: a few reads a day) is respected.
Schedule::command('jobs:fetch')->hourly()->withoutOverlapping(30);

// Digests go out in the morning. Weekly monitors sit out six of these.
Schedule::command('jobs:digest')->dailyAt('07:00')->timezone('Asia/Manila')->withoutOverlapping(30);
