<?php

namespace App\Console\Commands;

use App\Services\Monitoring\MonitoringRefresher;
use App\Services\Projects\ProjectActivator;
use App\Services\Projects\ProjectRepository;
use App\Services\Settings\SettingsRepository;
use Illuminate\Console\Command;

/**
 * Collects every configured social profile. Runs from the Monitoring tab's
 * "Collect all" button and on the nightly schedule (see routes/console.php).
 *
 * Profiles are per PROJECT, so this walks every project and activates each one
 * before reading its settings — a queue/cron process has no session to inherit
 * the active project from.
 */
class CollectMonitoring extends Command
{
    protected $signature = 'monitoring:collect
                            {--project= : Only this project slug (default: every project)}';

    protected $description = 'Collect performance data for every configured social profile';

    public function handle(MonitoringRefresher $refresher, ProjectRepository $projects, ProjectActivator $activator): int
    {
        $alvo = (string) ($this->option('project') ?? '');
        $lista = collect($projects->all())->filter(fn ($p) => $alvo === '' || $p->slug === $alvo);

        if ($lista->isEmpty()) {
            $this->error($alvo !== '' ? "No project «{$alvo}»." : 'No projects configured.');

            return self::FAILURE;
        }

        $plataformas = (array) config('contentmachine.monitoring.plataformas', []);
        $falhou = false;

        foreach ($lista as $project) {
            $activator->activate($project);
            $settings = app(SettingsRepository::class);

            $urls = [];
            foreach ($plataformas as $p) {
                $urls[$p] = (string) ($settings->get("perfis.{$p}.url") ?? '');
            }

            $this->line("── {$project->slug}");
            foreach ($refresher->atualizarTodas($urls) as $plataforma => $r) {
                if ($r['ok']) {
                    $this->info("   {$plataforma}: {$r['count']} posts");
                } else {
                    $falhou = true;
                    $this->warn("   {$plataforma}: {$r['error']}");
                }
            }
        }

        // Non-zero so a cron/CI wrapper can notice, without stopping the other
        // projects — every network is still attempted above.
        return $falhou ? self::FAILURE : self::SUCCESS;
    }
}
