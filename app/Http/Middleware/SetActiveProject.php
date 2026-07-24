<?php

namespace App\Http\Middleware;

use App\Services\Projects\ProjectContext;
use App\Services\Projects\ProjectRepository;
use App\Services\Settings\SettingsOverlay;
use App\Services\Settings\SettingsRepository;
use Closure;
use Illuminate\Http\Request;

/**
 * Resolves the active project (from the session) and repoints the whole vault
 * surface at it: the vault path + the design-system and clip-style config paths,
 * then re-applies that project's settings overlay and locale. Runs per web
 * request, before any Livewire component / service resolves the vault.
 */
class SetActiveProject
{
    public function __construct(
        private ProjectRepository $projects,
        private ProjectContext $context,
        private SettingsOverlay $overlay,
        private SettingsRepository $settings,
    ) {}

    public function handle(Request $request, Closure $next)
    {
        $slug = (string) $request->session()->get('project_slug', '');
        $project = ($slug !== '' ? $this->projects->find($slug) : null) ?? $this->projects->default();

        $this->context->set($project);

        // Repoint every vault-derived path at the active project's directory.
        config([
            'contentmachine.vault.path' => $project->path,
            'contentmachine.design_system.path' => $project->path.'/design-system.md',
            'contentmachine.clips.style_md' => $project->path.'/estilo-animacao.md',
        ]);

        // Re-apply this project's own API keys / model config, then its language.
        $this->overlay->apply($this->settings);
        app()->setLocale($project->language ?: (string) config('app.locale', 'en'));

        return $next($request);
    }
}
