<?php

namespace App\Livewire\Imoplanta;

use App\Services\Imoplanta\ImoplantaCustos;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Admin only: what each Imoplanta property cost against its credit's revenue, by step, with averages per tier. */
#[Layout('components.layouts.app')]
#[Title('Imoplanta · custos')]
class Custos extends Component
{
    public function render(ImoplantaCustos $custos)
    {
        $linhas = $custos->porImovel();

        return view('livewire.imoplanta.custos', ['linhas' => $linhas, 'resumo' => $custos->resumo($linhas), 'passos' => ImoplantaCustos::PASSOS]);
    }
}
