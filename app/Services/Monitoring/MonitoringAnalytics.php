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
     * Every metric bucketed by day (last 14), week (last 12, Monday-keyed) or
     * month (last 12), oldest→newest, so a chart reads left-to-right in time.
     * Empty periods are kept (value 0). Each bucket:
     * {label, views, likes, comentarios, partilhas, guardados, posts}.
     *
     * @param  array<int,array<string,mixed>>  $conteudos
     * @return array<int,array<string,mixed>>
     */
    public function serie(array $conteudos, string $granularidade = 'dia', ?int $periodos = null): array
    {
        $periodos ??= $granularidade === 'dia' ? 14 : 12;

        // A bucket's key/label from any date within it. Weeks key on their Monday.
        $chaveDe = fn (Carbon $d): string => match ($granularidade) {
            'mes' => $d->format('Y-m'),
            'semana' => $d->copy()->startOfWeek()->format('Y-m-d'),
            default => $d->format('Y-m-d'),
        };
        $rotuloDe = fn (Carbon $d): string => match ($granularidade) {
            'mes' => $d->translatedFormat('M/y'),
            'semana' => $d->copy()->startOfWeek()->translatedFormat('d/m'),
            default => $d->translatedFormat('d/m'),
        };

        // Pre-seed every bucket so gaps show as zero, not as missing points.
        $baldes = [];
        $agora = Carbon::now();
        for ($i = $periodos - 1; $i >= 0; $i--) {
            $ref = match ($granularidade) {
                'mes' => $agora->copy()->subMonths($i),
                'semana' => $agora->copy()->subWeeks($i),
                default => $agora->copy()->subDays($i),
            };
            $baldes[$chaveDe($ref)] = ['label' => $rotuloDe($ref), 'posts' => 0]
                + array_fill_keys(self::METRICAS, 0);
        }

        foreach ($conteudos as $item) {
            $data = $this->data($item['publicado_em'] ?? null);
            if ($data === null) {
                continue;
            }
            $chave = $chaveDe($data);
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
     * Week-over-week change: each metric (plus posts) summed over the last 7
     * days vs the 7 days before that. Rolling windows, not calendar weeks — a
     * half-elapsed week would always read as a drop. delta is the % change,
     * or null when the previous window is zero (no honest percentage exists).
     *
     * @param  array<int,array<string,mixed>>  $conteudos
     * @return array<string,array{atual:int,anterior:int,delta:?float}>
     */
    public function semanaSobreSemana(array $conteudos): array
    {
        $fim = Carbon::now();
        $corte = $fim->copy()->subDays(7);
        $inicio = $fim->copy()->subDays(14);

        $soma = ['atual' => array_fill_keys([...self::METRICAS, 'posts'], 0)];
        $soma['anterior'] = $soma['atual'];

        foreach ($conteudos as $item) {
            $data = $this->data($item['publicado_em'] ?? null);
            if ($data === null || $data->lt($inicio) || $data->gt($fim)) {
                continue;
            }
            $janela = $data->gte($corte) ? 'atual' : 'anterior';
            foreach (self::METRICAS as $k) {
                $soma[$janela][$k] += (int) ($item[$k] ?? 0);
            }
            $soma[$janela]['posts']++;
        }

        $linhas = [];
        foreach ([...self::METRICAS, 'posts'] as $k) {
            $atual = $soma['atual'][$k];
            $anterior = $soma['anterior'][$k];
            $linhas[$k] = [
                'atual' => $atual,
                'anterior' => $anterior,
                'delta' => $anterior > 0 ? round(($atual - $anterior) / $anterior * 100, 1) : null,
            ];
        }

        return $linhas;
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

    /**
     * Overall engagement rate (%) for a set of items — total interactions over
     * total views — or null when no item has views (nothing honest to show).
     */
    public function engajamento(array $itens): ?float
    {
        $views = 0;
        $inter = 0;
        foreach ($itens as $i) {
            $views += (int) ($i['views'] ?? 0);
            $inter += $this->interacoes($i);
        }

        return $views > 0 ? round($inter / $views * 100, 2) : null;
    }

    /** Engagement rate (%) of ONE item, or null without views. */
    public static function erItem(array $item): ?float
    {
        $views = (int) ($item['views'] ?? 0);
        if ($views <= 0) {
            return null;
        }
        $inter = (int) ($item['likes'] ?? 0) + (int) ($item['comentarios'] ?? 0)
            + (int) ($item['partilhas'] ?? 0) + (int) ($item['guardados'] ?? 0);

        return round($inter / $views * 100, 2);
    }

    /**
     * Best time to post: a 7×24 (ISO weekday × hour) heat grid of engagement at
     * the publish moments, plus the top time slots. Items whose timestamp has no
     * time-of-day (date-only, e.g. YouTube upload dates land at 00:00:00) are
     * skipped — they would pile up in a fake midnight column. Null when fewer
     * than 3 items carry a real hour: too little to call a pattern.
     *
     * @return array{grade:array<int,array<int,array{nivel:int,valor:int}>>,melhores:array<int,array{label:string,valor:int}>,metrica:string}|null
     */
    public function melhoresHoras(array $conteudos): ?array
    {
        $celulas = [];
        $comHora = 0;
        foreach ($conteudos as $item) {
            $d = $this->data($item['publicado_em'] ?? null);
            if ($d === null || $d->format('H:i:s') === '00:00:00') {
                continue;
            }
            $comHora++;
            $dia = $d->isoWeekday() - 1;
            $hora = (int) $d->format('G');
            $celulas[$dia][$hora]['posts'] = ($celulas[$dia][$hora]['posts'] ?? 0) + 1;
            $celulas[$dia][$hora]['inter'] = ($celulas[$dia][$hora]['inter'] ?? 0) + $this->interacoes($item);
        }

        if ($comHora < 3) {
            return null;
        }

        // Rank by engagement; when the network exposes none, post count still shows rhythm.
        $temInter = collect($celulas)->flatten(1)->sum('inter') > 0;
        $metrica = $temInter ? 'inter' : 'posts';

        $max = 0;
        foreach ($celulas as $horas) {
            foreach ($horas as $c) {
                $max = max($max, $c[$metrica]);
            }
        }

        $dias = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $grade = [];
        $topo = [];
        for ($dia = 0; $dia < 7; $dia++) {
            for ($hora = 0; $hora < 24; $hora++) {
                $valor = (int) ($celulas[$dia][$hora][$metrica] ?? 0);
                $grade[$dia][$hora] = [
                    'nivel' => $valor === 0 ? 0 : max(1, (int) ceil($valor / $max * 4)),
                    'valor' => $valor,
                ];
                if ($valor > 0) {
                    $topo[] = ['label' => $dias[$dia].' '.$hora.'h', 'valor' => $valor];
                }
            }
        }
        usort($topo, fn ($a, $b) => $b['valor'] <=> $a['valor']);

        return [
            'grade' => $grade,
            'melhores' => array_slice($topo, 0, 3),
            'metrica' => $temInter ? 'interactions' : 'posts',
        ];
    }

    /**
     * Cross-network totals, one row per platform with collected items:
     * followers, posts, each metric summed, and the aggregate engagement rate.
     * Sorted by views, descending.
     *
     * @param  array<string,array{itens:array<int,array<string,mixed>>,canal:array<string,mixed>}>  $dados
     * @return array<int,array<string,mixed>>
     */
    public function porPlataforma(array $dados): array
    {
        $linhas = [];
        foreach ($dados as $plataforma => $d) {
            $itens = $d['itens'] ?? [];
            if ($itens === []) {
                continue;
            }
            $linha = ['plataforma' => $plataforma, 'posts' => count($itens), 'seguidores' => (int) (($d['canal'] ?? [])['subscribers'] ?? 0)];
            foreach (self::METRICAS as $k) {
                $linha[$k] = (int) array_sum(array_map(fn ($i) => (int) ($i[$k] ?? 0), $itens));
            }
            $linha['er'] = $this->engajamento($itens);
            $linhas[] = $linha;
        }
        usort($linhas, fn ($a, $b) => $b['views'] <=> $a['views']);

        return $linhas;
    }

    /**
     * Follower prediction for one platform: at the current growth trend, how
     * many days — and, at the current posting pace, how many more posts — until
     * the next 1000-follower milestone.
     *
     * The trend is followers/day between the OLDEST recorded day that carries
     * this platform (history rows with a `redes` breakdown) and today's count.
     * Null when there is no earlier point to draw a trend from. `dias`/`posts`
     * come back null when the trend is flat or negative — no honest ETA exists.
     *
     * @param  array<int,array<string,mixed>>  $historico  MonitoringHistory::serie()
     * @param  array<int,array<string,mixed>>  $conteudos  the platform's items (posting pace + mix)
     * @return array{alvo:int,faltam:int,crescimentoDia:float,dias:?int,eta:?string,postsPorDia:float,posts:?int,mix:array<string,int>,desde:string}|null
     */
    public function previsaoSeguidores(array $historico, string $plataforma, int $subsAtual, array $conteudos): ?array
    {
        if ($subsAtual <= 0) {
            return null;
        }

        // Oldest recorded day with this platform's followers, before today.
        $hoje = Carbon::now()->toDateString();
        $base = null;
        foreach ($historico as $dia) {
            $subs = (int) (($dia['redes'] ?? [])[$plataforma] ?? 0);
            if ($subs > 0 && ($dia['data'] ?? '') < $hoje) {
                $base = ['data' => (string) $dia['data'], 'subs' => $subs];
                break;
            }
        }
        if ($base === null) {
            return null;
        }

        $diasSpan = max(1, (int) Carbon::parse($base['data'])->diffInDays(Carbon::now()->startOfDay()));
        $crescimentoDia = ($subsAtual - $base['subs']) / $diasSpan;

        // Posting pace and type mix over the last 14 days.
        $corte = Carbon::now()->subDays(14);
        $recentes = 0;
        $mix = [];
        foreach ($conteudos as $item) {
            $d = $this->data($item['publicado_em'] ?? null);
            if ($d === null || $d->lt($corte)) {
                continue;
            }
            $recentes++;
            $tipo = (string) ($item['tipo'] ?? 'post');
            $mix[$tipo] = ($mix[$tipo] ?? 0) + 1;
        }
        arsort($mix);
        $postsPorDia = $recentes / 14;

        $alvo = max(1000, (int) (ceil(($subsAtual + 1) / 1000) * 1000));
        $faltam = $alvo - $subsAtual;
        $dias = $crescimentoDia > 0 ? (int) ceil($faltam / $crescimentoDia) : null;

        // The post estimate scales the mix to the total, so the parts add up.
        $posts = ($dias !== null && $postsPorDia > 0) ? max(1, (int) ceil($dias * $postsPorDia)) : null;
        if ($posts !== null && $recentes > 0) {
            $mix = array_map(fn (int $n) => max(1, (int) round($posts * $n / $recentes)), $mix);
        }

        return [
            'alvo' => $alvo,
            'faltam' => $faltam,
            'crescimentoDia' => round($crescimentoDia, 2),
            'dias' => $dias,
            'eta' => $dias !== null ? Carbon::now()->addDays($dias)->translatedFormat('j M Y') : null,
            'postsPorDia' => round($postsPorDia, 2),
            'posts' => $posts,
            'mix' => $mix,
            'desde' => $base['data'],
        ];
    }

    /** Interactions of one item: likes + comments + shares + saves. */
    private function interacoes(array $item): int
    {
        return (int) ($item['likes'] ?? 0) + (int) ($item['comentarios'] ?? 0)
            + (int) ($item['partilhas'] ?? 0) + (int) ($item['guardados'] ?? 0);
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
