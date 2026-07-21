<?php

namespace App\Livewire;

use App\Jobs\Clips\TranscribeJob;
use App\Models\ClipProject;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
#[Title('Clips Animados')]
class ClipsAnimados extends Component
{
    use WithFileUploads;

    /** 'animation' | 'overlay' */
    public string $activeTab = 'animation';

    // Separador "Animação"
    public string $text = '';
    public $audio = null;

    // Separador "Vídeo + Animações"
    public $video = null;

    public function trocarSeparador(string $tab): void
    {
        $this->activeTab = in_array($tab, ['animation', 'overlay'], true) ? $tab : 'animation';
        $this->resetValidation();
    }

    public function submitAnimation(): void
    {
        $this->validate([
            'text' => 'required_without:audio|nullable|string|max:5000',
            'audio' => 'nullable|file|mimetypes:audio/mpeg,audio/wav,audio/x-wav,audio/mp4,audio/webm|max:51200',
        ], [], ['text' => 'texto', 'audio' => 'locução']);

        $kind = $this->audio ? 'audio' : 'text';
        $path = $this->audio ? $this->audio->store('clips/uploads') : null;

        $project = ClipProject::create([
            'type' => ClipProject::TYPE_ANIMATION,
            'input_kind' => $kind,
            'title' => $this->tituloDe($this->text),
            'source_text' => $kind === 'text' ? $this->text : null,
            'source_path' => $path,
        ]);

        TranscribeJob::dispatch($project->id);

        $this->reset(['text', 'audio']);
    }

    public function submitOverlay(): void
    {
        $this->validate([
            'video' => 'required|file|mimetypes:video/mp4,video/quicktime|max:512000',
        ], [], ['video' => 'vídeo']);

        $path = $this->video->store('clips/uploads');

        $project = ClipProject::create([
            'type' => ClipProject::TYPE_OVERLAY,
            'input_kind' => 'video',
            'title' => $this->video->getClientOriginalName(),
            'source_path' => $path,
        ]);

        TranscribeJob::dispatch($project->id);

        $this->reset(['video']);
    }

    /** @return \Illuminate\Support\Collection<int,ClipProject> */
    public function getProjectsProperty()
    {
        $type = $this->activeTab === 'overlay' ? ClipProject::TYPE_OVERLAY : ClipProject::TYPE_ANIMATION;

        return ClipProject::where('type', $type)->latest()->take(10)->get();
    }

    public function getHasActiveProperty(): bool
    {
        return $this->projects->contains(fn (ClipProject $p) => $p->isActive());
    }

    private function tituloDe(?string $text): string
    {
        $text = trim((string) $text);

        return $text === '' ? 'Sem título' : Str::limit($text, 48);
    }

    public function render()
    {
        return view('livewire.clips-animados');
    }
}
