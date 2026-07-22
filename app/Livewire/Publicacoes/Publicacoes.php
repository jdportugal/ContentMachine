<?php

namespace App\Livewire\Publicacoes;

use App\Services\Publicacoes\PublicacaoKinds;
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

    public function render()
    {
        return view('livewire.publicacoes.index');
    }
}
