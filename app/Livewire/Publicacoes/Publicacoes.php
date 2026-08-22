<?php

namespace App\Livewire\Publicacoes;

use App\Services\Publicacoes\PublicacaoKinds;
use App\Services\Vault\VaultContract;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Posts')]
class Publicacoes extends Component
{
    /** @return array<string,array<string,mixed>> */
    #[Computed]
    public function tipos(): array
    {
        return app(PublicacaoKinds::class)->all();
    }

    /** Posts already composed in the workshop, most recent first. */
    #[Computed]
    public function publicacoes()
    {
        return app(VaultContract::class)->all('rascunhos')
            ->filter(fn ($n) => $n->get('origem') === 'publicacoes/oficina');
    }

    /** Is the post generating images? (cache flag set by the workshop/job) */
    public function aGerar(string $slug): bool
    {
        return \Illuminate\Support\Facades\Cache::has(\App\Jobs\GerarImagensJob::notaKey($slug));
    }

    /** Is any post generating? (controls the panel's polling) */
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

    /** Toggles a post between "draft" (in progress) and "ready" (goes to Finished). */
    public function alternarPronto(string $path, VaultContract $vault): void
    {
        $nota = $vault->get($path);
        if (! $nota) {
            return;
        }

        // Do not touch a piece that is already scheduled.
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
        return view('livewire.publicacoes.index', [
            'custos' => app(\App\Services\Costs\CostLedger::class)->totaisPorPeca('publicacao'),
        ]);
    }
}
