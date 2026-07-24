<?php

namespace App\Http\Middleware;

use App\Services\Projects\ProjectActivator;
use App\Services\Projects\ProjectRepository;
use Closure;
use Illuminate\Http\Request;

/**
 * Resolves the active project (from the session) and switches the whole app at
 * it (vault + design-system + clip-style + model overlay + locale) via
 * ProjectActivator, before any Livewire component / service resolves the vault.
 */
class SetActiveProject
{
    public function __construct(
        private ProjectRepository $projects,
        private ProjectActivator $activator,
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $slug = (string) $request->session()->get('project_slug', '');
        $project = ($slug !== '' ? $this->projects->find($slug) : null) ?? $this->projects->default();

        $this->activator->activate($project);

        return $next($request);
    }
}
