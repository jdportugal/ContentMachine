<?php

namespace App\Livewire;

use App\Services\Monitoring\MonitoringHistory;
use App\Services\Monitoring\MonitoringManager;
use App\Services\Monitoring\MonitoringStats;
use App\Services\Vault\VaultContract;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Dashboard')]
class Painel extends Component
{
    public function render(MonitoringManager $monitoring, MonitoringStats $stats, VaultContract $vault, MonitoringHistory $history)
    {
        // Performance summary per platform (best recent content).
        $plataformas = $monitoring->todos()->map(function ($driver, $p) {
            $melhores = $driver->melhores(1);

            return [
                'plataforma' => $p,
                'melhor' => $melhores[0] ?? null,
                'resumo' => $driver->resumo()[1] ?? $driver->resumo()[0] ?? null,
            ];
        })->values();

        return view('livewire.painel', [
            'plataformas' => $plataformas,
            // Channel totals (subscribers, posts, performance) for the networks.
            'estatisticas' => $stats->totais($monitoring->plataformas()),
            // The same totals as of the last recorded day, for the line under each
            // card. Null until a collection has run on some earlier day — nothing
            // recorded the numbers before, so there is no history to backfill.
            'ontem' => $history->anterior(),
            // Read the LAST generated report from the vault. NEVER generate live here:
            // the 'api' news driver throws (no Apify key) or blocks 30s, which crashed «/».
            'destaquesNoticias' => array_slice($this->ultimosDestaques($vault), 0, 3),
        ]);
    }

    /** Highlights from the most recent stored news report, or [] — never throws. */
    private function ultimosDestaques(VaultContract $vault): array
    {
        try {
            $nota = $vault->all('noticias')
                ->filter(fn ($n) => $n->get('tipo') === 'relatorio' && filled($n->get('dados')))
                ->sortByDesc(fn ($n) => (string) $n->get('gerado_em', $n->get('inicio', '')))
                ->first();

            $dados = $nota ? json_decode((string) $nota->get('dados'), true) : null;

            return is_array($dados['destaques'] ?? null) ? $dados['destaques'] : [];
        } catch (\Throwable) {
            return [];
        }
    }
}
