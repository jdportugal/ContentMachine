<?php

namespace App\Livewire;

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Gerador de Clips')]
class Clips extends Component
{
    public function render()
    {
        return view('livewire.clips');
    }
}
