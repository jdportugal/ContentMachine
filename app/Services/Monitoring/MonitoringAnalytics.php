<?php

namespace App\Services\Monitoring;

use Illuminate\Support\Carbon;

/**
 * Derives chart series and per-type averages from the collected content items.
 * Pure and driver-agnostic — it works off whatever conteudosRecentes() returns,
 * so it behaves the same for YouTube (yt-dlp), Apify and the dev fake.
 */
class MonitoringAnalytics
{
    /**
     * Views + post count bucketed by day (last 14) or month (last 12), oldest→newest,
     * so a bar chart reads left-to-right in time. Empty periods are kept (value 0).
     *
     * @param  array<int,array<string,mixed>>  $conteudos
     * @return array<int,array{label:string,views:int,posts:int}>
     */
    public function serie(array $conteudos, string $granularidade = 'dia', ?int $periodos = null): array
    {
        $mes = $granularidade === 'mes';
        $periodos ??= $mes ? 12 : 14;

        // Pre-seed every bucket so gaps show as zero, not as missing bars.
        $baldes = [];
        $agora = Carbon::now();
        for ($i = $periodos - 1; $i >= 0; $i--) {
            $ref = $mes ? $agora->copy()->subMonths($i) : $agora->copy()->subDays($i);
            $chave = $ref->format($mes ? 'Y-m' : 'Y-m-d');
            $baldes[$chave] = [
                'label' => $ref->translatedFormat($mes ? 'M/y' : 'd/m'),
                'views' => 0,
                'posts' => 0,
            ];
        }

        foreach ($conteudos as $item) {
            $data = $this->data($item['publicado_em'] ?? null);
            if ($data === null) {
                continue;
            }
            $chave = $data->format($mes ? 'Y-m' : 'Y-m-d');
            if (! isset($baldes[$chave])) {
                continue; // outside the window
            }
            $baldes[$chave]['views'] += (int) ($item['views'] ?? 0);
            $baldes[$chave]['posts']++;
        }

        return array_values($baldes);
    }

    /**
     * Per-content-type averages: how many, mean views/likes, engagement rate, and
     * subscribers per post of that type (null when the subscriber count is unknown).
     * Sorted by post count, descending.
     *
     * @param  array<int,array<string,mixed>>  $conteudos
     * @return array<int,array{tipo:string,posts:int,views_med:int,likes_med:int,engajamento:float,subs_por:?int}>
     */
    public function mediasPorTipo(array $conteudos, int $subscribers = 0): array
    {
        $grupos = [];
        foreach ($conteudos as $item) {
            $tipo = (string) ($item['tipo'] ?? '—');
            $grupos[$tipo][] = $item;
        }

        $linhas = [];
        foreach ($grupos as $tipo => $itens) {
            $n = count($itens);
            $views = array_map(fn ($i) => (int) ($i['views'] ?? 0), $itens);
            $likes = array_map(fn ($i) => (int) ($i['likes'] ?? 0), $itens);

            $linhas[] = [
                'tipo' => $tipo,
                'posts' => $n,
                'views_med' => (int) round(array_sum($views) / $n),
                'likes_med' => (int) round(array_sum($likes) / $n),
                'engajamento' => round($this->engajamentoMedio($itens), 1),
                'subs_por' => $subscribers > 0 ? (int) round($subscribers / $n) : null,
            ];
        }

        usort($linhas, fn ($a, $b) => $b['posts'] <=> $a['posts']);

        return $linhas;
    }

    /** Mean engagement rate (%) — (likes+comments+shares+saves)/views — over items with views. */
    private function engajamentoMedio(array $itens): float
    {
        $taxas = [];
        foreach ($itens as $i) {
            $views = (int) ($i['views'] ?? 0);
            if ($views <= 0) {
                continue;
            }
            $inter = (int) ($i['likes'] ?? 0) + (int) ($i['comentarios'] ?? 0)
                + (int) ($i['partilhas'] ?? 0) + (int) ($i['guardados'] ?? 0);
            $taxas[] = $inter / $views * 100;
        }

        return $taxas === [] ? 0.0 : array_sum($taxas) / count($taxas);
    }

    private function data(mixed $valor): ?Carbon
    {
        if (blank($valor)) {
            return null;
        }
        try {
            return Carbon::parse((string) $valor);
        } catch (\Throwable) {
            return null;
        }
    }
}
