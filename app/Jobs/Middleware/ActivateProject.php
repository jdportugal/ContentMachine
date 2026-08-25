<?php

namespace App\Jobs\Middleware;

use App\Services\Projects\ProjectActivator;
use App\Services\Projects\ProjectRepository;
use Closure;

/**
 * Switches to the job's project BEFORE handle() runs.
 *
 * Calling activateProject() inside handle() is too late: Laravel resolves
 * handle()'s type-hinted dependencies first, so the vault, settings and stores
 * a job receives are built from whatever project the worker happened to be on —
 * usually the one left behind by the PREVIOUS job. Two projects then bleed into
 * each other on a long-lived worker: a run reads one project's channels and
 * archives into another's vault.
 *
 * Job middleware runs inside the pipeline that wraps handle(), so activating
 * here means every dependency is resolved against the right project.
 */
class ActivateProject
{
    public function __construct(private readonly string $slug) {}

    public function handle(object $job, Closure $next): mixed
    {
        if ($this->slug !== '') {
            $project = app(ProjectRepository::class)->find($this->slug);
            if ($project !== null) {
                app(ProjectActivator::class)->activate($project);
            }
        }

        return $next($job);
    }
}
