<?php

namespace App\Jobs\Concerns;

use App\Jobs\Middleware\ActivateProject;
use App\Services\Projects\ProjectActivator;
use App\Services\Projects\ProjectContext;
use App\Services\Projects\ProjectRepository;
use App\Services\Themes\ThemeContext;

/**
 * Carries the active project onto a queued job. Queue workers have no web
 * session, so a job must remember which project it belongs to (captured at
 * dispatch) and re-activate it before touching the vault.
 */
trait RunsInProject
{
    public string $projectSlug = '';

    public string $themeSlug = '';

    /**
     * Capture the current project — call from the job constructor (dispatch time).
     *
     * Also appends the activation middleware onto Queueable's own $middleware
     * array (Laravel merges that with any middleware() method, so the few jobs
     * declaring their own keep both). The middleware runs before handle()'s
     * dependencies are resolved, which is the only moment early enough to
     * switch project — see ActivateProject.
     */
    protected function captureProject(): void
    {
        $this->projectSlug = app(ProjectContext::class)->current()->slug;
        $this->themeSlug = app(ThemeContext::class)->current()->slug;
        $this->middleware[] = new ActivateProject($this->projectSlug, $this->themeSlug);
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
