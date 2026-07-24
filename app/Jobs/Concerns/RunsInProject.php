<?php

namespace App\Jobs\Concerns;

use App\Services\Projects\ProjectActivator;
use App\Services\Projects\ProjectContext;
use App\Services\Projects\ProjectRepository;

/**
 * Carries the active project onto a queued job. Queue workers have no web
 * session, so a job must remember which project it belongs to (captured at
 * dispatch) and re-activate it before touching the vault.
 */
trait RunsInProject
{
    public string $projectSlug = '';

    /** Capture the current project — call from the job constructor (dispatch time). */
    protected function captureProject(): void
    {
        $this->projectSlug = app(ProjectContext::class)->current()->slug;
    }

    /** Re-activate the captured project — call first thing in handle(). */
    protected function activateProject(): void
    {
        if ($this->projectSlug === '') {
            return;
        }
        if ($project = app(ProjectRepository::class)->find($this->projectSlug)) {
            // Full switch (vault + design-system + clip-style + models + locale),
            // so background renders/plans use the clip's own project — not default.
            app(ProjectActivator::class)->activate($project);
        }
    }
}
