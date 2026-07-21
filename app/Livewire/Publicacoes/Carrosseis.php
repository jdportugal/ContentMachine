<?php

namespace App\Livewire\Publicacoes;

use App\Services\Vault\VaultContract;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Carrosséis')]
class Carrosseis extends Component
{
    #[Validate('required|string|min:3|max:120')]
    public string $titulo = '';

    #[Validate('required|in:instagram,linkedin,tiktok,youtube')]
    public string $plataforma = 'instagram';

    /** @var array<int,string> */
    public array $slides = ['', ''];

    public ?string $guardado = null;

    public function adicionarSlide(): void
    {
        if (count($this->slides) < 10) {
            $this->slides[] = '';
        }
    }

    public function removerSlide(int $i): void
    {
        if (count($this->slides) > 1) {
            unset($this->slides[$i]);
            $this->slides = array_values($this->slides);
        }
    }

    public function criarRascunho(VaultContract $vault): void
    {
        $this->validate();

        $slides = array_values(array_filter($this->slides, fn ($s) => filled(trim($s))));

        if (count($slides) < 2) {
            $this->addError('slides', 'Um carrossel precisa de pelo menos 2 cartões com texto.');

            return;
        }

        $body = collect($slides)
            ->map(fn ($texto, $i) => '## Cartão '.($i + 1)."\n\n".trim($texto))
            ->implode("\n\n---\n\n");

        $nota = $vault->create('rascunhos', [
            'titulo' => $this->titulo,
            'tipo' => 'carrossel',
            'plataforma' => $this->plataforma,
            'estado' => 'rascunho',
            'origem' => 'publicacoes/carrosseis',
            'cartoes' => count($slides),
            'tags' => ['carrossel', $this->plataforma],
        ], $body);

        $this->guardado = $nota->title();
        $this->reset('titulo');
        $this->slides = ['', ''];
    }

    public function render()
    {
        return view('livewire.publicacoes.carrosseis');
    }
}
