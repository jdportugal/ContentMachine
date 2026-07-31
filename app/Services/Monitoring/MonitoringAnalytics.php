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
    /** Metrics summed into each time bucket, alongside the post count. */
    public const METRICAS = ['views', 'likes', 'comentarios', 'partilhas', 'guardados'];

    /**
     * Every metric bucketed by day (last 14) or month (last 12), oldest→newest, so a
     * chart reads left-to-right in time. Empty periods are kept (value 0). Each bucket:
     * {label, views, likes, comentarios, partilhas, guardados, posts}.
     *
     * @param  array<int,array<string,mixed>>  $conteudos
     * @return array<int,array<string,mixed>>
     */
    public function serie(array $conteudos, string $granularidade = 'dia', ?int $periodos = null): array
    {
        $mes = $granularidade === 'mes';
        $periodos ??= $mes ? 12 : 14;

        // Pre-seed every bucket so gaps show as zero, not as missing points.
        $baldes = [];
        $agora = Carbon::now();
        for ($i = $periodos - 1; $i >= 0; $i--) {
            $ref = $mes ? $agora->copy()->subMonths($i) : $agora->copy()->subDays($i);
            $chave = $ref->format($mes ? 'Y-m' : 'Y-m-d');
            $baldes[$chave] = ['label' => $ref->translatedFormat($mes ? 'M/y' : 'd/m'), 'posts' => 0]
                + array_fill_keys(self::METRICAS, 0);
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
            foreach (self::METRICAS as $k) {
                $baldes[$chave][$k] += (int) ($item[$k] ?? 0);
            }
            $baldes[$chave]['posts']++;
        }

        return array_values($baldes);
    }

    /**
     * Smooth SVG paths (Catmull-Rom → cubic bézier) through $vals within a $w×$h
     * viewBox: a `line` for the stroke and an `area` closed down to the baseline for
     * the fill. Dependency-free — the prettiness is just maths, no chart library.
     *
     * @param  array<int,int|float>  $vals
     * @return array{line:string,area:string}
     */
    public static function curvePath(array $vals, float $w = 100, float $h = 40, float $pad = 3): array
    {
        $vals = array_values(array_map('floatval', $vals));
        $n = count($vals);
        if ($n === 0) {
            return ['line' => '', 'area' => ''];
        }

        $max = max($vals + [0.0]) ?: 1e-9;
        $base = $h - $pad;
        $innerH = $h - 2 * $pad;
        $px = fn (int $i): float => $n === 1 ? $w / 2 : $pad + ($i / ($n - 1)) * ($w - 2 * $pad);
        $py = fn (float $v): float => $base - ($v / $max) * $innerH;

        $pts = [];
        foreach ($vals as $i => $v) {
            $pts[] = [$px($i), $py($v)];
        }

        $f = static fn (float $v): string => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
        $line = 'M '.$f($pts[0][0]).' '.$f($pts[0][1]);
        for ($i = 0; $i < $n - 1; $i++) {
            $p0 = $pts[max($i - 1, 0)];
            $p1 = $pts[$i];
            $p2 = $pts[$i + 1];
            $p3 = $pts[min($i + 2, $n - 1)];
            $c1x = $p1[0] + ($p2[0] - $p0[0]) / 6;
            $c1y = $p1[1] + ($p2[1] - $p0[1]) / 6;
            $c2x = $p2[0] - ($p3[0] - $p1[0]) / 6;
            $c2y = $p2[1] - ($p3[1] - $p1[1]) / 6;
            $line .= ' C '.$f($c1x).' '.$f($c1y).' '.$f($c2x).' '.$f($c2y).' '.$f($p2[0]).' '.$f($p2[1]);
        }

        $area = $line.' L '.$f($pts[$n - 1][0]).' '.$f($base).' L '.$f($pts[0][0]).' '.$f($base).' Z';

        return ['line' => $line, 'area' => $area];
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
