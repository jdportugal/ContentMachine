<?php

namespace App\Livewire\Publicacoes;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Publicações')]
class Publicacoes extends Component
{
    public function render()
    {
        return view('livewire.publicacoes.index');
    }
}
