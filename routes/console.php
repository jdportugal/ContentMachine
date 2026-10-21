<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Nightly social collection. Needs a running scheduler for the app's timezone:
 *   * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
 *
 * withoutOverlapping guards the case where a slow Apify run is still going when
 * the next night comes round; runInBackground keeps one slow network from
 * delaying the rest of the schedule.
 */
Schedule::command('monitoring:collect')
    ->dailyAt('00:00')
    ->withoutOverlapping()
    ->runInBackground();

/*
 * Per-project "auto" mode (Settings → General): every minute, fire the daily
 * news→report→clip pipeline for any enabled project whose time has passed —
 * the command itself guards the once-a-day part.
 */
Schedule::command('auto:tick')->everyMinute();

/*
 * Free-video leads (landing page): read the agent's name/phone off the listing and mark each reel ready or failed.
 */
Schedule::command('leads:atualizar')->everyMinute()->withoutOverlapping();
