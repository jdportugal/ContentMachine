<?php

namespace App\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Clips Animados')]
class ClipsAnimados extends Component
{
    public function render()
    {
        return view('livewire.clips-animados');
    }
}
