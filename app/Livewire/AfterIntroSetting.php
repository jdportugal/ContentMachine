<?php

namespace App\Livewire;

use App\Services\Clips\Store\EffectStore;
use App\Services\RealEstate\PropertyVideoAnalyzer;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * The project's "after intro" video and the line said over it. Shown on both
 * the SFX page and Design System → Project resources.
 */
class AfterIntroSetting extends Component
{
    use WithFileUploads;

    /** Saved as soon as it is picked. */
    public $afterIntroUpload = null;

    /** What the voiceover says over it, right after the intro. */
    public string $afterIntroLine = '';

    public function mount(): void
    {
        $this->afterIntroLine = $this->effects()->afterIntroLine();
    }

    public function updatedAfterIntroUpload(): void
    {
        $this->validate(
            ['afterIntroUpload' => 'required|file|max:1048576'],
            ['afterIntroUpload.required' => 'Escolha um vídeo.', 'afterIntroUpload.file' => 'Escolha um vídeo.', 'afterIntroUpload.max' => 'O vídeo é demasiado grande (máx. 1 GB).'],
        );
        // ffprobe is the real check: a file it can't read as video would fail the render.
        try {
            PropertyVideoAnalyzer::probe($this->afterIntroUpload->getRealPath());
        } catch (\RuntimeException $e) {
            $this->addError('afterIntroUpload', $e->getMessage());

            return;
        }
        $this->effects()->putAfterIntro($this->afterIntroUpload->getRealPath(), $this->afterIntroUpload->getClientOriginalExtension() ?: 'mp4');
        $this->afterIntroUpload = null;
    }

    public function updatedAfterIntroLine(): void
    {
        $this->validate(['afterIntroLine' => 'nullable|string|max:200'], ['afterIntroLine.max' => 'A frase pode ter no máximo 200 caracteres.']);
        $this->effects()->putAfterIntroLine($this->afterIntroLine);
    }

    public function apagarAfterIntro(): void
    {
        $this->effects()->deleteAfterIntro();
    }

    /** Length of the project's after-intro video in seconds, or null when none is set. */
    public function getAfterIntroProperty(): ?float
    {
        $path = $this->effects()->afterIntroPath();

        try {
            return $path ? round(PropertyVideoAnalyzer::probe(Storage::disk(config('contentmachine.clips.disk'))->path($path))['duration'], 1) : null;
        } catch (\RuntimeException) {
            return 0.0;   // set but unreadable: shown as 0 s, the user replaces it
        }
    }

    private function effects(): EffectStore
    {
        return app(EffectStore::class);
    }

    public function render()
    {
        return view('livewire.after-intro-setting');
    }
}
