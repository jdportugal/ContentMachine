<?php

namespace App\Services\Scoring;

/**
 * Índice de desempenho de conteúdo (padrão head-of-content).
 * Combina taxa de interação ponderada por plataforma com o "outlier multiple"
 * (quantas vezes acima da mediana do canal um conteúdo se destacou).
 */
class EngagementScorer
{
    /**
     * @param  array<string,float>  $item  métricas normalizadas do conteúdo
     * @param  float  $medianaViews  mediana de views do canal (para outlier)
     * @return array{score:int, taxa_interacao:float, outlier:float}
     */
    public function score(string $plataforma, array $item, float $medianaViews = 0.0): array
    {
        $pesos = config("contentmachine.scoring.pesos.{$plataforma}")
            ?? ['likes' => 1.0, 'comentarios' => 2.0, 'partilhas' => 2.0, 'guardados' => 1.5];

        $views = max(1, (int) ($item['views'] ?? 0));

        $interacoesPonderadas =
            ($item['likes'] ?? 0) * $pesos['likes']
            + ($item['comentarios'] ?? 0) * $pesos['comentarios']
            + ($item['partilhas'] ?? 0) * $pesos['partilhas']
            + ($item['guardados'] ?? 0) * $pesos['guardados'];

        $taxa = $interacoesPonderadas / $views; // interações ponderadas por view

        $outlier = $medianaViews > 0 ? $views / $medianaViews : 1.0;

        // Índice 0–100: taxa de interação (log-comprimida) reforçada pelo outlier.
        $base = min(1.0, $taxa * 8);                 // ~12.5% de taxa ponderada = topo
        $bonus = min(0.35, ($outlier - 1) * 0.12);   // desempenho acima da mediana
        $score = (int) round(max(0, min(1.0, $base + $bonus)) * 100);

        return [
            'score' => $score,
            'taxa_interacao' => round($taxa * 100, 2),
            'outlier' => round($outlier, 2),
        ];
    }

    /** Mediana de uma lista de valores (para o cálculo de outliers). */
    public function mediana(array $valores): float
    {
        $valores = array_values(array_filter($valores, 'is_numeric'));
        sort($valores);
        $n = count($valores);

        if ($n === 0) {
            return 0.0;
        }

        $mid = intdiv($n, 2);

        return $n % 2 ? (float) $valores[$mid] : ($valores[$mid - 1] + $valores[$mid]) / 2;
    }
}
