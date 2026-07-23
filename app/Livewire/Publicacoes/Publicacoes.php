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

    /** A publicação está a gerar imagens? (bandeira de cache posta pela oficina/job) */
    public function aGerar(string $slug): bool
    {
        return \Illuminate\Support\Facades\Cache::has(\App\Jobs\GerarImagensJob::notaKey($slug));
    }

    /** Há alguma publicação a gerar? (controla a sondagem do painel) */
    #[Computed]
    public function algumAGerar(): bool
    {
        foreach ($this->publicacoes as $nota) {
            if ($this->aGerar($nota->slug())) {
                return true;
            }
        }

        return false;
    }

    public function remover(string $path, VaultContract $vault): void
    {
        $vault->delete($path);
        unset($this->publicacoes);
    }

    /** Alterna uma publicação entre «rascunho» (a trabalhar) e «pronto» (vai para Rascunhos). */
    public function alternarPronto(string $path, VaultContract $vault): void
    {
        $nota = $vault->get($path);
        if (! $nota) {
            return;
        }

        // Não mexe numa peça já agendada.
        if ($nota->get('estado') === 'agendado') {
            return;
        }

        $vault->updateFrontmatter($path, [
            'estado' => $nota->get('estado') === 'pronto' ? 'rascunho' : 'pronto',
        ]);
        unset($this->publicacoes);
    }

    public function render()
    {
        return view('livewire.publicacoes.index');
    }
}
