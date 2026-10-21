<?php

namespace App\Http\Middleware;

use App\Services\Projects\ProjectActivator;
use App\Services\Projects\ProjectRepository;
use App\Services\Themes\ThemeContext;
use Closure;
use Illuminate\Http\Request;

/**
 * Imocena has one project (config contentmachine.project, else the first in the
 * registry): switches the whole app at it (vault + design-system + clip-style +
 * model overlay + locale) via ProjectActivator, before any Livewire component /
 * service resolves the vault.
 */
class SetActiveProject
{
    public function __construct(
        private ProjectRepository $projects,
        private ProjectActivator $activator,
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $slug = (string) config('contentmachine.project');
        $project = ($slug !== '' ? $this->projects->find($slug) : null) ?? $this->projects->default();

        $this->activator->activate($project);
        // The studio's chosen theme (ThemeSwitcher), else the default.
        app(ThemeContext::class)->use((string) $request->session()->get('theme', ''));

        return $next($request);
    }
}
