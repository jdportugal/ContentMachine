<?php

namespace App\Services\Scoring;

/**
 * Content performance index (head-of-content pattern).
 * Combines a platform-weighted interaction rate with the "outlier multiple"
 * (how many times above the channel median a piece of content stood out).
 */
class EngagementScorer
{
    /**
     * @param  array<string,float>  $item  normalized content metrics
     * @param  float  $medianaViews  channel median views (for the outlier)
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

        $taxa = $interacoesPonderadas / $views; // weighted interactions per view

        $outlier = $medianaViews > 0 ? $views / $medianaViews : 1.0;

        // Index 0–100: interaction rate (log-compressed) reinforced by the outlier.
        $base = min(1.0, $taxa * 8);                 // ~12.5% weighted rate = top
        $bonus = min(0.35, ($outlier - 1) * 0.12);   // performance above the median
        $score = (int) round(max(0, min(1.0, $base + $bonus)) * 100);

        return [
            'score' => $score,
            'taxa_interacao' => round($taxa * 100, 2),
            'outlier' => round($outlier, 2),
        ];
    }

    /** Median of a list of values (for the outlier calculation). */
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
