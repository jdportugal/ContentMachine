<?php

namespace App\Livewire;

use App\Services\Monitoring\MonitoringAnalytics;
use App\Services\Monitoring\MonitoringHistory;
use App\Services\Monitoring\MonitoringManager;
use App\Services\Monitoring\MonitoringStats;
use App\Services\Monitoring\MonitoringStore;
use App\Services\Vault\VaultContract;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Dashboard')]
class Painel extends Component
{
    public function render(MonitoringManager $monitoring, MonitoringStats $stats, VaultContract $vault, MonitoringHistory $history, MonitoringStore $store)
    {
        // Everything below reads the store/cache only — nothing slow on «/».
        $analytics = new MonitoringAnalytics;
        $porRede = [];
        foreach ($monitoring->plataformas() as $p) {
            $porRede[$p] = ['itens' => $store->itens($p), 'canal' => $store->canal($p)];
        }
        $breakdown = collect($analytics->porPlataforma($porRede))->keyBy('plataforma');

        // Performance summary per platform: best recent content, the per-network
        // totals (followers/posts/views/likes/ER) and a views-per-day sparkline.
        $plataformas = $monitoring->todos()->map(function ($driver, $p) use ($analytics, $breakdown, $porRede) {
            $melhores = $driver->melhores(1);

            return [
                'plataforma' => $p,
                'melhor' => $melhores[0] ?? null,
                'resumo' => $driver->resumo()[1] ?? $driver->resumo()[0] ?? null,
                'stats' => $breakdown->get($p),
                'serie' => array_column($analytics->serie($porRede[$p]['itens'], 'dia'), 'views'),
            ];
        })->values();

        // Week-over-week (last 7 days vs the 7 before), across every network.
        // «Interactions» matches MonitoringStats::totais: likes + comments.
        $todosItens = array_merge(...array_column($porRede, 'itens'));
        $wow = $analytics->semanaSobreSemana($todosItens);
        $interAtual = $wow['likes']['atual'] + $wow['comentarios']['atual'];
        $interAnterior = $wow['likes']['anterior'] + $wow['comentarios']['anterior'];

        return view('livewire.painel', [
            'plataformas' => $plataformas,
            // Channel totals (subscribers, posts, performance) for the networks.
            'estatisticas' => $stats->totais($monitoring->plataformas()),
            // Aggregate engagement rate across every collected network.
            'engajamento' => $analytics->engajamento($todosItens),
            'wowViews' => $wow['views']['delta'],
            'wowPosts' => $wow['posts']['delta'],
            'wowInteracoes' => $interAnterior > 0 ? round(($interAtual - $interAnterior) / $interAnterior * 100, 1) : null,
            // Daily recorded totals, for the trend curves under the cards.
            'historico' => $history->serie(30),
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
