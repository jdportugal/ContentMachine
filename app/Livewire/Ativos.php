<?php

namespace App\Livewire;

use App\Services\Shorts\MusicLibrary;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Assets — library of reusable media in the app. For now, background music
 * tracks (used by the Clip Generator); designed to grow to other
 * types of assets (logos, intros, etc.).
 */
#[Layout('components.layouts.app')]
#[Title('Assets')]
class Ativos extends Component
{
    use WithFileUploads;

    public $novaMusica = null;

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

    public function render(MusicLibrary $lib)
    {
        return view('livewire.ativos', [
            'musicas' => $lib->all(),
        ]);
    }
}
