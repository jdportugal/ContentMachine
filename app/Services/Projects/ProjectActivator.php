<?php

namespace App\Services\Projects;

use App\Services\Settings\SettingsOverlay;
use App\Services\Settings\SettingsRepository;

/**
 * Single place that switches the whole app to a project: the vault + the
 * design-system and clip-style paths, this project's model config overlay, and
 * its language. Used by the SetActiveProject middleware (web) AND by
 * RunsInProject jobs (queue), so background work targets the same project the
 * request did — not just the vault.
 */
class ProjectActivator
{
    public function __construct(private ProjectContext $context, private SettingsOverlay $overlay) {}

    public function activate(Project $project): void
    {
        $this->context->set($project);

        config([
            'contentmachine.vault.path' => $project->path,
            'contentmachine.design_system.path' => $project->path.'/design-system.md',
            'contentmachine.clips.style_md' => $project->path.'/estilo-animacao.md',
        ]);

        // Re-apply this project's model config (keys are global) with a FRESH,
        // project-aware SettingsRepository, then set its language.
        $this->overlay->apply(app(SettingsRepository::class));
        app()->setLocale($project->language !== '' ? $project->language : (string) config('app.locale', 'en'));
    }
}
