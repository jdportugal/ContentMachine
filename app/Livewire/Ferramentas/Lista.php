<?php

namespace App\Livewire\Ferramentas;

use App\Models\FerramentaIa;
use App\Services\Ferramentas\Ferramentas;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Ferramentas IA's start page: the AI videos left (and the packs), the tools, the customer's videos (admins: everyone's). */
#[Layout('components.layouts.app')]
#[Title('Ferramentas IA')]
class Lista extends Component
{
    public ?string $pagamento = null;

    public function mount(): void
    {
        if (session('pago')) {   // back from a pack's Payment Link (/obrigado)
            $this->pagamento = 'Pagamento recebido. Os vídeos ficam disponíveis aqui assim que o pagamento for confirmado.';
        }
    }

    public function render()
    {
        $user = auth()->user();

        return view('livewire.ferramentas.lista', [
            'videos' => FerramentaIa::query()->when(! $user->is_admin, fn ($q) => $q->where('user_id', $user->id))->latest('id')->get(),
            'disponiveis' => $user->is_admin || $user->imoplantaGratis() ? null : $user->videosIaDisponiveis(),
            'ferramentas' => Ferramentas::disponiveis(),
            'packs' => config('contentmachine.ferramentas.packs'),
        ]);
    }
}
