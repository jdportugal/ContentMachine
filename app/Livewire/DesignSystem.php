<?php

namespace App\Livewire;

use App\Services\DesignSystem\DesignSystemRepository;
use App\Services\DesignSystem\DesignThemeExtractor;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
#[Title('Design System')]
class DesignSystem extends Component
{
    use WithFileUploads;

    /** Design system Markdown (editable). */
    public string $conteudo = '';

    /**
     * The project's animation style manual (estilo-animacao.md), appended to
     * every clip's planning prompt. Lives beside the design system because it
     * is the same job: telling the generators how this project should look.
     */
    public string $estilo = '';

    /** Uploaded .md file to seed the editor. */
    public $ficheiro = null;

    public ?string $guardado = null;

    public ?string $atualizado = null;

    public function mount(DesignSystemRepository $design): void
    {
        // Seed the editor with the saved content, or the starter template if still empty.
        $this->conteudo = $design->readOrTemplate();
        $this->atualizado = $design->updatedAt();
        $this->estilo = (string) (@file_get_contents($this->caminhoEstilo()) ?: '');
    }

    /** The active project's style manual — repointed per project by ProjectActivator. */
    private function caminhoEstilo(): string
    {
        return (string) config('contentmachine.clips.style_md');
    }

    /** Loads a .md into the editor (does not save — the user reviews and saves). */
    public function carregar(): void
    {
        $this->validate([
            'ficheiro' => 'required|file|max:1024|mimetypes:text/plain,text/markdown,text/x-markdown',
        ], [
            'ficheiro.required' => 'Choose a .md file.',
            'ficheiro.max' => 'The file is too large (maximum 1 MB).',
            'ficheiro.mimetypes' => 'The file must be Markdown/text (.md).',
        ], ['ficheiro' => 'file']);

        $this->conteudo = (string) file_get_contents($this->ficheiro->getRealPath());
        $this->ficheiro = null;
        $this->dispatch('toast', message: 'File loaded — review and save.', type: 'ok');
    }

    public function guardar(DesignSystemRepository $design, DesignThemeExtractor $extractor): void
    {
        // Only a CHANGED design system is rewritten and re-extracted. Rewriting it
        // changes the design hash every effect/background preview is keyed on, so
        // saving just the animation instructions used to throw away and re-render
        // every preview — and wait on the theme extraction call — for nothing.
        $designMudou = $this->conteudo !== $design->read() || $design->readTokens() === null;
        if ($designMudou) {
            $design->write($this->conteudo);
            // Extract the theme tokens (colors/fonts/texture) from the Markdown, so that
            // the animations MATCH the design. On failure → keep/use defaults.
            $design->writeTokens($extractor->extract($this->conteudo));
        }

        // The animation instructions are read at planning time only — nothing to rebuild.
        $caminho = $this->caminhoEstilo();
        if ($this->estilo !== (string) (@file_get_contents($caminho) ?: '')) {
            @mkdir(dirname($caminho), 0775, true);
            file_put_contents($caminho, $this->estilo);
        }

        $this->guardado = now()->timezone(config('app.timezone'))->translatedFormat('H:i');
        $this->atualizado = $design->updatedAt();
        $this->dispatch('toast', type: 'ok', message: $designMudou
            ? 'Design system saved and theme extracted.'
            : 'Animation instructions saved.');
    }

    public function render()
    {
        $design = app(DesignSystemRepository::class);

        return view('livewire.design-system', [
            'caminho' => $design->path(),
            'caminhoEstilo' => $this->caminhoEstilo(),
            'tokens' => $design->readTokens(),
        ]);
    }
}
