<?php

namespace App\Livewire;

use App\Services\Clips\ImageLibrary;
use App\Services\Shorts\MusicLibrary;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Assets — library of reusable media in the app: background music tracks (used by
 * the Clip Generator) and reusable images (logos, brand shots) the animated-clip
 * planner searches before asking the user or generating.
 */
#[Layout('components.layouts.app')]
#[Title('Assets')]
class Ativos extends Component
{
    use WithFileUploads;

    public $novaMusica = null;

    /** Dropped/selected image files staged for describing before they are saved. */
    public array $novasImagens = [];

    /** Description typed for each staged image, keyed by its index in $novasImagens. */
    public array $descricoes = [];

    private function notificar(string $texto, string $tipo = 'ok'): void
    {
        $this->dispatch('toast', message: $texto, type: $tipo);
    }

    public function adicionarMusica(MusicLibrary $lib): void
    {
        $this->validate([
            'novaMusica' => 'required|file|mimes:mp3,wav,m4a,aac,ogg,flac|max:30720',
        ], [
            'novaMusica.required' => 'Choose an audio file.',
            'novaMusica.mimes' => 'Unsupported format (use mp3, wav, m4a, aac, ogg or flac).',
            'novaMusica.max' => 'File too large (max. 30 MB).',
        ]);

        $lib->add($this->novaMusica->getRealPath(), $this->novaMusica->getClientOriginalName());

        $this->reset('novaMusica');
        $this->notificar('Music added to the library.');
    }

    public function removerMusica(string $name, MusicLibrary $lib): void
    {
        $lib->remove($name);
        $this->notificar('Music removed.');
    }

    /** A fresh selection replaces any half-described previous one. */
    public function updatedNovasImagens(): void
    {
        $this->reset('descricoes');
        $this->resetValidation();
    }

    /** Save the staged images with the descriptions typed for each. */
    public function adicionarImagens(ImageLibrary $lib): void
    {
        $files = array_values(array_filter($this->novasImagens));
        if ($files === []) {
            return;
        }
        $this->validate(
            ['novasImagens.*' => 'image|max:20480'],
            ['novasImagens.*.image' => 'Only image files are allowed.', 'novasImagens.*.max' => 'Each image must be under 20 MB.'],
        );

        foreach ($files as $i => $f) {
            $lib->add($f->getRealPath(), $f->getClientOriginalName(), (string) ($this->descricoes[$i] ?? ''));
        }
        $n = count($files);
        $this->reset('novasImagens', 'descricoes');
        $this->notificar($n.' '.($n === 1 ? 'image' : 'images').' added to the library.');
    }

    public function descartarImagens(): void
    {
        $this->reset('novasImagens', 'descricoes');
        $this->resetValidation();
    }

    public function atualizarDescricao(string $id, string $descricao, ImageLibrary $lib): void
    {
        $lib->updateDescription($id, $descricao);
        $this->notificar('Description updated.');
    }

    public function removerImagem(string $id, ImageLibrary $lib): void
    {
        $lib->remove($id);
        $this->notificar('Image removed.');
    }

    public function render(MusicLibrary $lib, ImageLibrary $images)
    {
        return view('livewire.ativos', [
            'musicas' => $lib->all(),
            'imagens' => $images->all(),
        ]);
    }
}
