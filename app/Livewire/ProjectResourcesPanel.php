<?php

namespace App\Livewire;

use App\Services\Projects\ProjectResources;
use App\Services\Shorts\MusicLibrary;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Design System → Project resources: the agent this project is for, plus the
 * logo, music and images every listing clip uses unless the clip brings its own.
 * Uploads save as soon as they are picked; the text fields save with the button.
 */
class ProjectResourcesPanel extends Component
{
    use WithFileUploads;

    /** @var array{name:string,phone:string,email:string,licence:string} */
    public array $agent = [];

    public string $music = '';

    public $agentPhoto = null;

    public $logo = null;

    public $newImage = null;

    public string $newImageDesc = '';

    /** @var list<array{file:string,description:string}> Extra images, descriptions edited in place. */
    public array $extras = [];

    public function mount(): void
    {
        $this->agent = $this->resources()->agent();
        $this->music = $this->resources()->music();
        $this->loadExtras();
    }

    public function guardar(): void
    {
        $this->validate([
            'agent.name' => 'nullable|string|max:80',
            'agent.phone' => 'nullable|string|max:40',
            'agent.email' => 'nullable|email|max:120',
            'agent.licence' => 'nullable|string|max:60',
            'extras.*.description' => 'required|string|max:300',
        ], ['extras.*.description.required' => 'Say what the image shows — the planner reads it.']);
        $this->resources()->putAgent($this->agent);
        $this->resources()->putMusic($this->music);
        foreach ($this->extras as $e) {
            $this->resources()->describeImage($e['file'], $e['description']);
        }
        $this->dispatch('toast', type: 'ok', message: 'Project resources saved.');
    }

    public function updatedAgentPhoto(): void
    {
        $this->validate(['agentPhoto' => 'image|max:10240']);
        $this->resources()->putFile('agent-photo', $this->agentPhoto->getRealPath(), $this->agentPhoto->extension());
        $this->agentPhoto = null;
    }

    public function updatedLogo(): void
    {
        $this->validate(['logo' => 'image|max:10240']);
        $this->resources()->putFile('logo', $this->logo->getRealPath(), $this->logo->extension());
        $this->logo = null;
    }

    /** @param 'agent-photo'|'logo' $name */
    public function apagar(string $name): void
    {
        if (in_array($name, ['agent-photo', 'logo'], true)) {
            $this->resources()->deleteFile($name);
        }
    }

    public function adicionarImagem(): void
    {
        $this->validate([
            'newImage' => 'required|image|max:20480',
            'newImageDesc' => 'required|string|max:300',
        ], ['newImageDesc.required' => 'Say what the image shows — the planner reads it.']);
        $this->resources()->addImage($this->newImage->getRealPath(), $this->newImage->extension(), $this->newImageDesc);
        $this->reset('newImage', 'newImageDesc');
        $this->loadExtras();
    }

    public function removerImagem(string $file): void
    {
        $this->resources()->removeImage($file);
        $this->loadExtras();
    }

    private function loadExtras(): void
    {
        $this->extras = array_map(fn ($i) => ['file' => $i['file'], 'description' => $i['description']], $this->resources()->images());
    }

    public function render()
    {
        $resources = $this->resources();

        return view('livewire.project-resources-panel', [
            'hasPhoto' => $resources->agentPhotoPath() !== null,
            'hasLogo' => $resources->logoPath() !== null,
            'tracks' => array_column(app(MusicLibrary::class)->all(), 'name'),
            'defaultTrack' => (string) config('contentmachine.real_estate.music'),
            'version' => time(),   // busts the thumbnail cache after a replace
        ]);
    }

    private function resources(): ProjectResources
    {
        return app(ProjectResources::class);
    }
}
