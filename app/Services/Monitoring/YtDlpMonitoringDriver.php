<?php

namespace App\Services\Monitoring;

use App\Services\Scoring\EngagementScorer;

/**
 * Monitoring driver with REAL data from the user's channel, collected
 * via yt-dlp and stored in the MonitoringStore (see YtDlpMonitoringFetcher). Reads the
 * stored data (fast); if no collection has happened yet, returns empty — the UI shows
 * «no data, refresh». Derives summary/latest-per-type/best from the real items.
 */
class YtDlpMonitoringDriver implements MonitoringDriver
{
    public function __construct(
        private readonly string $plataforma,
        private readonly EngagementScorer $scorer,
        private readonly MonitoringStore $store,
    ) {}

    public function plataforma(): string
    {
        return $this->plataforma;
    }

    /** @return array<int,array<string,mixed>> */
    public function conteudosRecentes(int $limite = 12): array
    {
        return array_slice($this->store->itens($this->plataforma), 0, $limite);
    }

    /** @return array<int,array<string,mixed>> */
    public function ultimoPorTipo(): array
    {
        $porTipo = [];
        foreach ($this->conteudosRecentes(100) as $item) {
            $porTipo[$item['tipo']] ??= $item;
        }

        return array_values($porTipo);
    }

    /** @return array<int,array<string,mixed>> */
    public function melhores(int $limite = 5): array
    {
        $itens = $this->conteudosRecentes(100);
        usort($itens, fn ($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));

        return array_slice($itens, 0, $limite);
    }

    /**
     * Summary cards derived from the REAL collected items. Without channel
     * metrics (subscribers, etc.) that yt-dlp does not expose — uses aggregates
     * of the recent posts, with no made-up deltas.
     *
     * @return array<int,array<string,mixed>>
     */
    public function resumo(): array
    {
        $itens = $this->conteudosRecentes(100);

        if ($itens === []) {
            return [];
        }

        $publicacoes = ['label' => 'Recent posts', 'value' => (string) count($itens), 'delta' => null, 'unit' => 'collected'];

        // Networks without public metrics (Instagram) only show the count — the
        // likes/views would come out as zero and would be misleading.
        if (in_array($this->plataforma, (array) config('contentmachine.monitoring.sem_metricas', []), true)) {
            return [$publicacoes];
        }

        return [
            $publicacoes,
            ['label' => 'Views', 'value' => MonitoringStats::numero((int) array_sum(array_column($itens, 'views'))), 'delta' => null, 'unit' => 'recent'],
            ['label' => 'Likes', 'value' => MonitoringStats::numero((int) array_sum(array_column($itens, 'likes'))), 'delta' => null, 'unit' => 'recent'],
            ['label' => 'Comments', 'value' => MonitoringStats::numero((int) array_sum(array_column($itens, 'comentarios'))), 'delta' => null, 'unit' => 'recent'],
        ];
    }
}
