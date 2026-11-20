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

/*
 * Two days after a free video, agents who did nothing since (and agreed to be contacted) are reminded
 * that inviting a colleague gets their video without the watermark. In the morning and late afternoon,
 * not at night.
 */
Schedule::command('leads:lembrar-convite --enviar')->dailyAt('10:17')->withoutOverlapping();
Schedule::command('leads:lembrar-convite --enviar')->dailyAt('17:47')->withoutOverlapping();
