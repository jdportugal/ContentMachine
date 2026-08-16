<?php

namespace App\Services\Projects;

/**
 * Holds the active project for the current process. The vault binding, the
 * SetActiveProject middleware and project-aware jobs read from here so every
 * vault operation targets the right workspace. Defaults to the first registered
 * project when nothing has been selected (e.g. a queue worker with no session).
 */
class ProjectContext
{
    private ?Project $current = null;

    public function __construct(private ProjectRepository $projects) {}

    public function current(): Project
    {
        return $this->current ??= $this->projects->default();
    }

    public function set(Project $project): void
    {
        $this->current = $project;
    }

    public function vaultPath(): string
    {
        return $this->current()->path;
    }
}
