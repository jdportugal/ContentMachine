<?php

namespace App\Livewire;

use App\Services\Shorts\MusicLibrary;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Ativos — biblioteca de media reutilizável na app. Por agora, faixas de música
 * de fundo (usadas pelo Gerador de Clips); pensada para crescer para outros
 * tipos de ativos (logótipos, intros, etc.).
 */
#[Layout('components.layouts.app')]
#[Title('Ativos')]
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
            'novaMusica.required' => 'Escolha um ficheiro de áudio.',
            'novaMusica.mimes' => 'Formato não suportado (use mp3, wav, m4a, aac, ogg ou flac).',
            'novaMusica.max' => 'Ficheiro demasiado grande (máx. 30 MB).',
        ]);

        $lib->add($this->novaMusica->getRealPath(), $this->novaMusica->getClientOriginalName());

        $this->reset('novaMusica');
        $this->notificar('Música adicionada à biblioteca.');
    }

    public function removerMusica(string $name, MusicLibrary $lib): void
    {
        $lib->remove($name);
        $this->notificar('Música removida.');
    }

    public function render(MusicLibrary $lib)
    {
        return view('livewire.ativos', [
            'musicas' => $lib->all(),
        ]);
    }
}
