<?php

namespace App\Livewire\Publicacoes;

use App\Services\Vault\VaultContract;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Posts de página única')]
class Posts extends Component
{
    #[Validate('required|string|min:3|max:120')]
    public string $titulo = '';

    #[Validate('required|string|min:3')]
    public string $legenda = '';

    #[Validate('required|in:instagram,linkedin,tiktok,youtube')]
    public string $plataforma = 'instagram';

    public ?string $guardado = null;

    public function criarRascunho(VaultContract $vault): void
    {
        $this->validate();

        $nota = $vault->create('rascunhos', [
            'titulo' => $this->titulo,
            'tipo' => 'post',
            'plataforma' => $this->plataforma,
            'estado' => 'rascunho',
            'origem' => 'publicacoes/posts',
            'tags' => ['post', $this->plataforma],
        ], $this->legenda);

        $this->guardado = $nota->title();
        $this->reset('titulo', 'legenda');
    }

    public function render()
    {
        return view('livewire.publicacoes.posts');
    }
}
