<?php

namespace App\Livewire\Publicacoes;

use App\Services\Publicacoes\PublicacaoKinds;
use App\Services\Vault\VaultContract;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Publicações')]
class Publicacoes extends Component
{
    /** @return array<string,array<string,mixed>> */
    #[Computed]
    public function tipos(): array
    {
        return app(PublicacaoKinds::class)->all();
    }

    /** Publicações já compostas na oficina, mais recentes primeiro. */
    #[Computed]
    public function publicacoes()
    {
        return app(VaultContract::class)->all('rascunhos')
            ->filter(fn ($n) => $n->get('origem') === 'publicacoes/oficina');
    }

    public function remover(string $path, VaultContract $vault): void
    {
        $vault->delete($path);
        unset($this->publicacoes);
    }

    public function render()
    {
        return view('livewire.publicacoes.index');
    }
}
