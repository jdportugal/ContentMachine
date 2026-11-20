<?php

namespace App\Services\Marketing;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * The campaign funnel for a period: what Meta reports for the ads (impressions, link
 * clicks, spend) joined with what happened on the site (visits, free videos asked for,
 * videos ready, purchases), by channel and by ad. Each site event is attributed to the
 * first touch kept on it (Origem). Our own tests (utm_source=teste-interno) are left out.
 */
class Funil
{
    /** A lead paid within this many seconds of being created was a paid video from the start, not a free request. */
    private const PAGO_DE_RAIZ = 120;

    public function __construct(private MetaAnuncios $meta) {}

    public function calcular(?CarbonInterface $de, CarbonInterface $ate, bool $fresco = false): array
    {
        $noPeriodo = fn ($q) => $q->when($de, fn ($q) => $q->where('created_at', '>=', $de))->where('created_at', '<=', $ate);

        $eventos = ['visitas' => [], 'pedidos' => [], 'prontos' => [], 'compras' => []];
        foreach ($noPeriodo(DB::table('visitas'))->get(['origem']) as $v) {
            $eventos['visitas'][] = ['o' => self::ler($v->origem)];
        }
        foreach ($noPeriodo(DB::table('leads'))->get(['origem', 'pronto_em', 'pago_em', 'created_at']) as $l) {
            if ($l->pago_em && strtotime($l->pago_em) - strtotime($l->created_at) <= self::PAGO_DE_RAIZ) {
                continue;
            }
            $o = self::ler($l->origem);
            $eventos['pedidos'][] = ['o' => $o];
            if ($l->pronto_em) {
                $eventos['prontos'][] = ['o' => $o];
            }
        }
        foreach ($noPeriodo(DB::table('compras'))->where('total', '>', 0)->get(['origem', 'total']) as $c) {
            $eventos['compras'][] = ['o' => self::ler($c->origem), 'total' => (int) $c->total];
        }

        // By channel.
        $vazio = ['visitas' => 0, 'pedidos' => 0, 'prontos' => 0, 'compras' => 0, 'receita' => 0];
        $canais = array_fill_keys(array_keys(Canal::NOMES), $vazio);
        foreach ($eventos as $passo => $linhas) {
            foreach ($linhas as $e) {
                $canal = Canal::de($e['o']);
                if ($canal === Canal::INTERNO) {
                    continue;
                }
                $canais[$canal][$passo]++;
                $canais[$canal]['receita'] += $e['total'] ?? 0;
            }
        }
        $total = $vazio;
        foreach ($canais as $c) {
            foreach ($c as $k => $n) {
                $total[$k] += $n;
            }
        }

        // The ads, each with the site events that carry its tags.
        $meta = $this->meta->resultados($de, $ate, $fresco);
        $doCanal = fn (string $passo) => array_values(array_filter($eventos[$passo], fn ($e) => Canal::de($e['o']) === Canal::META_ADS));
        $anuncios = [];
        foreach ($meta['anuncios'] as $a) {
            foreach (['visitas', 'pedidos', 'prontos', 'compras'] as $passo) {
                $seus = array_filter($doCanal($passo), fn ($e) => Canal::doAnuncio($e['o'], $a['id'], $a['nome']));
                $a[$passo] = count($seus);
                if ($passo === 'compras') {
                    $a['receita'] = array_sum(array_column($seus, 'total'));
                }
            }
            $anuncios[] = $a;
        }
        usort($anuncios, fn ($x, $y) => $y['gasto'] <=> $x['gasto']);

        $ads = $canais[Canal::META_ADS];
        $gasto = round(array_sum(array_column($anuncios, 'gasto')), 2);
        $passos = [];
        if ($meta['ok']) {
            $passos[] = ['nome' => 'Impressões', 'nota' => 'vezes que os anúncios foram vistos', 'n' => array_sum(array_column($anuncios, 'impressoes'))];
            $passos[] = ['nome' => 'Cliques', 'nota' => 'cliques na ligação do anúncio', 'n' => array_sum(array_column($anuncios, 'cliques'))];
        }
        $passos[] = ['nome' => 'Visitas ao site', 'nota' => 'chegaram a imocena.com', 'n' => $ads['visitas']];
        $passos[] = ['nome' => 'Vídeos grátis pedidos', 'nota' => 'colaram o link de um anúncio', 'n' => $ads['pedidos']];
        $passos[] = ['nome' => 'Vídeos prontos', 'nota' => 'o vídeo grátis ficou feito', 'n' => $ads['prontos']];
        $passos[] = ['nome' => 'Compras', 'nota' => 'pagaram no Stripe', 'n' => $ads['compras']];
        $maior = max(1, ...array_column($passos, 'n'));
        foreach ($passos as $i => &$p) {
            $anterior = $i > 0 ? $passos[$i - 1]['n'] : 0;
            $p['taxa'] = $i > 0 && $anterior > 0 ? $p['n'] / $anterior : null;
            // Square-root scale: impressions are thousands of times the purchases, and both must be visible.
            $p['largura'] = $p['n'] > 0 ? max(3, (int) round(100 * sqrt($p['n'] / $maior))) : 0;
        }
        unset($p);

        return [
            'meta' => ['ok' => $meta['ok'], 'erro' => $meta['erro']],
            'passos' => $passos,
            'anuncios' => $anuncios,
            'canais' => array_filter($canais, fn ($c) => array_sum($c) > 0),
            'total' => $total,
            'gasto' => $gasto,
            'receita' => $ads['receita'],
            'custos' => [
                'clique' => self::por($gasto, array_sum(array_column($anuncios, 'cliques'))),
                'pedido' => self::por($gasto, $ads['pedidos']),
                'compra' => self::por($gasto, $ads['compras']),
            ],
            'retorno' => $gasto > 0 ? ($ads['receita'] / 100) / $gasto : null,
            'semEtiquetas' => $meta['ok'] && array_sum(array_column($anuncios, 'cliques')) > 0 && $ads['visitas'] === 0,
        ];
    }

    private static function por(float $gasto, int $n): ?float
    {
        return $gasto > 0 && $n > 0 ? $gasto / $n : null;
    }

    /**
     * Day by day, newest first, so one day can be set against the one before: the Meta ads
     * channel (visits, free videos asked for, purchases, revenue in cents) and every channel
     * together. 'agora' sets today so far against yesterday up to the same time of day.
     *
     * @return array{dias: list<array>, agora: array{hora: string, hoje: array, ontem: array}}
     */
    public function diaADia(int $dias = 14): array
    {
        $agora = now();
        $de = $agora->copy()->startOfDay()->subDays($dias - 1);
        $vazio = ['visitas' => 0, 'pedidos' => 0, 'compras' => 0, 'receita' => 0, 'pedidosTodos' => 0, 'comprasTodas' => 0, 'receitaTodos' => 0];
        $porDia = [];
        for ($d = $agora->copy()->startOfDay(); $d >= $de; $d = $d->copy()->subDay()) {
            $porDia[$d->toDateString()] = ['dia' => $d->copy()] + $vazio;
        }
        $limiteOntem = $agora->copy()->subDay();
        $ate = ['hoje' => $vazio, 'ontem' => $vazio];
        $somar = function (string $quando, ?array $origem, string $passo, int $total = 0) use (&$porDia, &$ate, $agora, $limiteOntem) {
            $canal = Canal::de($origem);
            if ($canal === Canal::INTERNO) {
                return;
            }
            $t = \Illuminate\Support\Carbon::parse($quando);
            $dia = $t->toDateString();
            if (! isset($porDia[$dia])) {
                return;
            }
            $campos = $canal === Canal::META_ADS ? [$passo] : [];
            if ($passo !== 'visitas') {
                $campos[] = ['pedidos' => 'pedidosTodos', 'compras' => 'comprasTodas'][$passo];
                if ($passo === 'compras') {
                    $campos[] = 'receitaTodos';
                }
            }
            if ($passo === 'compras' && $canal === Canal::META_ADS) {
                $campos[] = 'receita';
            }
            $alvos = [&$porDia[$dia]];
            if ($dia === $agora->toDateString()) {
                $alvos[] = &$ate['hoje'];
            } elseif ($dia === $limiteOntem->toDateString() && $t <= $limiteOntem) {
                $alvos[] = &$ate['ontem'];
            }
            foreach ($alvos as &$linha) {
                foreach ($campos as $c) {
                    $linha[$c] += str_starts_with($c, 'receita') ? $total : 1;
                }
            }
            unset($linha);
        };
        foreach (DB::table('visitas')->where('created_at', '>=', $de)->get(['origem', 'created_at']) as $v) {
            $somar((string) $v->created_at, self::ler($v->origem), 'visitas');
        }
        foreach (DB::table('leads')->where('created_at', '>=', $de)->get(['origem', 'pago_em', 'created_at']) as $l) {
            if ($l->pago_em && strtotime($l->pago_em) - strtotime($l->created_at) <= self::PAGO_DE_RAIZ) {
                continue;
            }
            $somar((string) $l->created_at, self::ler($l->origem), 'pedidos');
        }
        foreach (DB::table('compras')->where('created_at', '>=', $de)->where('total', '>', 0)->get(['origem', 'total', 'created_at']) as $c) {
            $somar((string) $c->created_at, self::ler($c->origem), 'compras', (int) $c->total);
        }

        return ['dias' => array_values($porDia), 'agora' => ['hora' => $agora->format('H:i'), 'hoje' => $ate['hoje'], 'ontem' => $ate['ontem']]];
    }

    private static function ler(?string $origem): ?array
    {
        $o = $origem ? json_decode($origem, true) : null;

        return is_array($o) && $o !== [] ? $o : null;
    }
}
