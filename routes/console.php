<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Free-video leads (landing page): read the agent's name/phone off the listing and mark each reel ready or failed.
 */
Schedule::command('leads:atualizar')->everyMinute()->withoutOverlapping();

/*
 * New-listing alerts: agents who ticked the box on the free-video form get an email when they publish a listing.
 */
Schedule::command('alertas:imoveis')->hourlyAt(7)->withoutOverlapping();
