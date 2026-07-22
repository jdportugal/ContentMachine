<?php

namespace App\Livewire;

use App\Services\DesignSystem\DesignSystemRepository;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
#[Title('Sistema de Design')]
class DesignSystem extends Component
{
    use WithFileUploads;

    /** Markdown do sistema de design (editável). */
    public string $conteudo = '';

    /** Ficheiro .md carregado para semear o editor. */
    public $ficheiro = null;

    public ?string $guardado = null;

    public ?string $atualizado = null;

    public function mount(DesignSystemRepository $design): void
    {
        // Semeia o editor com o guardado, ou o modelo inicial se ainda vazio.
        $this->conteudo = $design->readOrTemplate();
        $this->atualizado = $design->updatedAt();
    }

    /** Carrega um .md para o editor (não grava — o utilizador revê e guarda). */
    public function carregar(): void
    {
        $this->validate([
            'ficheiro' => 'required|file|max:1024|mimetypes:text/plain,text/markdown,text/x-markdown',
        ], [
            'ficheiro.required' => 'Escolha um ficheiro .md.',
            'ficheiro.max' => 'O ficheiro é demasiado grande (máximo 1 MB).',
            'ficheiro.mimetypes' => 'O ficheiro tem de ser Markdown/texto (.md).',
        ], ['ficheiro' => 'ficheiro']);

        $this->conteudo = (string) file_get_contents($this->ficheiro->getRealPath());
        $this->ficheiro = null;
        $this->dispatch('toast', message: 'Ficheiro carregado — reveja e guarde.', type: 'ok');
    }

    public function guardar(DesignSystemRepository $design): void
    {
        $design->write($this->conteudo);

        $this->guardado = now()->timezone(config('app.timezone'))->translatedFormat('H:i');
        $this->atualizado = $design->updatedAt();
        $this->dispatch('toast', message: 'Sistema de design guardado.', type: 'ok');
    }

    public function render()
    {
        return view('livewire.design-system', [
            'caminho' => app(DesignSystemRepository::class)->path(),
        ]);
    }
}
