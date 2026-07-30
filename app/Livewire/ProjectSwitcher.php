<?php

namespace App\Livewire;

use App\Services\Projects\ProjectContext;
use App\Services\Projects\ProjectRepository;
use Livewire\Component;

/**
 * Navbar workspace switcher. Selecting a project stores its slug in the session;
 * SetActiveProject then repoints the vault on the next request. Creating a
 * project makes a fresh vault directory and switches to it.
 */
class ProjectSwitcher extends Component
{
    public bool $creating = false;

    public string $newName = '';

    public string $newLanguage = 'en';

    public function trocar(string $slug, ProjectRepository $projects)
    {
        if ($projects->exists($slug)) {
            session(['project_slug' => $slug]);
        }

        return $this->redirect(request()->header('Referer') ?: route('painel'));
    }

    public function criar(ProjectRepository $projects)
    {
        $this->validate([
            'newName' => 'required|string|min:2|max:60',
            'newLanguage' => 'required|string|max:8',
        ], [
            'newName.required' => 'Name the project.',
            'newName.min' => 'Give it a longer name.',
        ]);

        $project = $projects->create($this->newName, $this->newLanguage);
        session(['project_slug' => $project->slug]);

        return $this->redirect(route('painel'));
    }

    public function render(ProjectRepository $projects, ProjectContext $context)
    {
        return view('livewire.project-switcher', [
            'projects' => $projects->all(),
            'current' => $context->current(),
        ]);
    }
}
