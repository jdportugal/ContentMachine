<?php

namespace App\Jobs\Middleware;

use App\Services\Projects\ProjectActivator;
use App\Services\Clips\Store\ClipStore;
use App\Services\Projects\ProjectRepository;
use App\Services\Themes\ThemeContext;
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
    public function __construct(private readonly string $slug, private readonly string $theme = '') {}

    public function handle(object $job, Closure $next): mixed
    {
        if ($this->slug !== '') {
            $project = app(ProjectRepository::class)->find($this->slug);
            if ($project !== null) {
                app(ProjectActivator::class)->activate($project);
            }
        }

        app(ThemeContext::class)->use($this->themeFor($job));

        return $next($job);
    }

    /**
     * A clip's jobs use the clip's own theme, whatever the studio was on when they
     * were queued; anything else (SFX, backgrounds, samples) the theme it was queued in.
     */
    private function themeFor(object $job): string
    {
        $id = $job->clipId ?? $job->projectId ?? null;
        $clip = is_string($id) ? app(ClipStore::class)->find($id) : null;

        return (string) ($clip?->meta['theme'] ?? $this->theme);
    }
}
