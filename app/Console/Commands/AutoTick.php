<?php

namespace App\Console\Commands;

use App\Jobs\AutoDailyJob;
use App\Services\Projects\ProjectActivator;
use App\Services\Projects\ProjectRepository;
use App\Services\Settings\SettingsRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Fires each project's daily "auto" pipeline once its configured time passes.
 * Runs every minute from the scheduler (see routes/console.php); the dated
 * once-a-day cache guard makes it self-healing — a scheduler that was down at
 * HH:MM still fires later the same day, and never twice.
 */
class AutoTick extends Command
{
    protected $signature = 'auto:tick';

    protected $description = 'Kick off the daily auto news-clip pipeline for projects whose time has come';

    public function handle(ProjectRepository $projects, ProjectActivator $activator): int
    {
        foreach ($projects->all() as $project) {
            $activator->activate($project);
            $auto = (array) app(SettingsRepository::class)->get('auto', []);

            if (empty($auto['ligado']) || now()->format('H:i') < (string) ($auto['hora'] ?? '09:00')) {
                continue;
            }
            if (! Cache::add('auto.ran.'.$project->slug.'.'.now()->toDateString(), true, now()->endOfDay())) {
                continue; // already fired today
            }

            $this->info("auto: {$project->slug}");
            AutoDailyJob::dispatch();
        }

        return self::SUCCESS;
    }
}
