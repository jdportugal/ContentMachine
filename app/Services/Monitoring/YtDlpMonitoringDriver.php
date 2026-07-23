<?php

namespace App\Services\Monitoring;

use App\Services\Scoring\EngagementScorer;

/**
 * Driver de monitorização com dados REAIS do canal do utilizador, recolhidos
 * via yt-dlp e guardados no MonitoringStore (ver YtDlpMonitoringFetcher). Lê o
 * armazenado (rápido); se ainda não houve recolha, devolve vazio — a UI mostra
 * «sem dados, atualize». Deriva resumo/último-por-tipo/melhores dos itens reais.
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
     * Cartões de resumo derivados dos itens REAIS recolhidos. Sem métricas de
     * canal (subscritores, etc.) que o yt-dlp não expõe — usa agregados das
     * publicações recentes, sem deltas inventados.
     *
     * @return array<int,array<string,mixed>>
     */
    public function resumo(): array
    {
        $itens = $this->conteudosRecentes(100);

        if ($itens === []) {
            return [];
        }

        $publicacoes = ['label' => 'Publicações recentes', 'value' => (string) count($itens), 'delta' => null, 'unit' => 'recolhidas'];

        // Redes sem métricas públicas (Instagram) só mostram a contagem — os
        // gostos/visualizações viriam a zero e seriam enganadores.
        if (in_array($this->plataforma, (array) config('contentmachine.monitoring.sem_metricas', []), true)) {
            return [$publicacoes];
        }

        return [
            $publicacoes,
            ['label' => 'Visualizações', 'value' => MonitoringStats::numero((int) array_sum(array_column($itens, 'views'))), 'delta' => null, 'unit' => 'recentes'],
            ['label' => 'Gostos', 'value' => MonitoringStats::numero((int) array_sum(array_column($itens, 'likes'))), 'delta' => null, 'unit' => 'recentes'],
            ['label' => 'Comentários', 'value' => MonitoringStats::numero((int) array_sum(array_column($itens, 'comentarios'))), 'delta' => null, 'unit' => 'recentes'],
        ];
    }
}
