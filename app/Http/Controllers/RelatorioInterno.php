<?php

namespace App\Http\Controllers;

use App\Services\Marketing\Canal;
use App\Services\Marketing\Funil;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The progress report as JSON, for the operator's tools, without a shell on the server:
 * what happened on the site in a period (visits, free videos, purchases, landing events,
 * running costs), the ads funnel, and the running totals since launch with the runway
 * (how many days the ads can keep running, at today's budget, before the profit made so
 * far is spent if nobody else buys). Read with "Authorization: Bearer <token>"; no token
 * configured = the endpoints do not exist (404). Dates are Lisbon days.
 */
class RelatorioInterno
{
    private const FUSO = 'Europe/Lisbon';

    public function __construct(private Funil $funil) {}

    /** GET /interno/resumo?de=YYYY-MM-DD&ate=YYYY-MM-DD (default: today). */
    public function resumo(Request $request): JsonResponse
    {
        $this->autorizar($request);
        [$de, $ate] = $this->periodo($request);

        return response()->json([
            'periodo' => ['de' => $de->toIso8601String(), 'ate' => $ate->toIso8601String(), 'gerado' => now(self::FUSO)->toIso8601String()],
            'visitas' => $this->visitas($de, $ate),
            'pedidos' => $this->pedidos($de, $ate),
            'compras' => $this->compras($de, $ate),
            'eventos' => DB::table('landing_eventos')->whereBetween('created_at', [$de->utc(), $ate->utc()])
                ->select('tipo', DB::raw('count(*) as n'))->groupBy('tipo')->orderByDesc('n')->pluck('n', 'tipo'),
            'custos_usd' => $this->custos($de, $ate),
            'funil' => rescue(fn () => $this->funil->calcular($de->utc(), $ate->utc()), ['erro' => 'funil indisponível'], report: false),
            'financas' => $this->financas($ate),
        ]);
    }

    /** GET /interno/dias?n=14: day by day, newest first (the admin's Campanhas table). */
    public function dias(Request $request): JsonResponse
    {
        $this->autorizar($request);
        $n = max(1, min(60, (int) $request->query('n', 14)));

        return response()->json($this->funil->diaADia($n));
    }

    private function autorizar(Request $request): void
    {
        $token = (string) config('contentmachine.relatorio.token');
        if ($token === '' && is_file($ficheiro = storage_path('app/relatorio_token'))) {
            $token = trim((string) file_get_contents($ficheiro));
        }
        // A short token is as good as none: the endpoints stay hidden.
        abort_if(strlen($token) < 32, 404);
        abort_unless(hash_equals($token, (string) $request->bearerToken()), 401);
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function periodo(Request $request): array
    {
        $dia = fn (string $campo, string $omissao) => rescue(
            fn () => CarbonImmutable::createFromFormat('Y-m-d', (string) $request->query($campo, $omissao), self::FUSO),
            null, report: false,
        ) ?? abort(422, "$campo: use YYYY-MM-DD");
        $hoje = now(self::FUSO)->toDateString();
        $de = $dia('de', $hoje)->startOfDay();
        $ate = $dia('ate', $request->query('de', $hoje))->endOfDay();
        abort_if($ate < $de, 422, 'ate antes de de');

        return [$de, $ate];
    }

    private function visitas(CarbonImmutable $de, CarbonImmutable $ate): array
    {
        $porCanal = [];
        $porAnuncio = [];
        $porPagina = [];
        $total = 0;
        foreach (DB::table('visitas')->whereBetween('created_at', [$de->utc(), $ate->utc()])->get(['origem', 'pagina']) as $v) {
            $o = self::ler($v->origem);
            $canal = Canal::de($o);
            if ($canal === Canal::INTERNO) {
                continue;
            }
            $total++;
            $porCanal[$canal] = ($porCanal[$canal] ?? 0) + 1;
            $porPagina[$v->pagina] = ($porPagina[$v->pagina] ?? 0) + 1;
            if ($canal === Canal::META_ADS) {
                $porAnuncio[$o['utm_content'] ?? '?'] = ($porAnuncio[$o['utm_content'] ?? '?'] ?? 0) + 1;
            }
        }
        arsort($porCanal);
        arsort($porAnuncio);
        arsort($porPagina);

        return ['total' => $total, 'por_canal' => $porCanal, 'por_anuncio' => $porAnuncio, 'paginas' => array_slice($porPagina, 0, 10, true)];
    }

    private function pedidos(CarbonImmutable $de, CarbonImmutable $ate): array
    {
        $lista = [];
        foreach (DB::table('leads')->whereBetween('created_at', [$de->utc(), $ate->utc()])->orderBy('id')->get() as $l) {
            $o = self::ler($l->origem);
            $canal = Canal::de($o);
            if ($canal === Canal::INTERNO) {
                continue;
            }
            $lista[] = [
                'id' => $l->id,
                'hora' => self::hora($l->created_at),
                'canal' => $canal,
                'anuncio' => $o['utm_content'] ?? null,
                'agencia' => $l->agencia ?? null,
                'imovel' => parse_url((string) $l->anuncio, PHP_URL_HOST),
                'email' => ! empty($l->email) || ! empty($l->email_video),
                'marketing' => ! empty($l->marketing_em) && empty($l->marketing_cancelado_em),
                'pronto' => ! empty($l->pronto_em),
                'falhou' => ! empty($l->falhou_em),
                'pago' => ! empty($l->pago_em),
            ];
        }
        $porCanal = array_count_values(array_column($lista, 'canal'));
        arsort($porCanal);

        return [
            'total' => count($lista),
            'com_email' => count(array_filter($lista, fn ($l) => $l['email'])),
            'pagos' => count(array_filter($lista, fn ($l) => $l['pago'])),
            'falhados' => count(array_filter($lista, fn ($l) => $l['falhou'])),
            'por_canal' => $porCanal,
            'lista' => $lista,
        ];
    }

    private function compras(CarbonImmutable $de, CarbonImmutable $ate): array
    {
        $lista = [];
        foreach (DB::table('compras')->whereBetween('created_at', [$de->utc(), $ate->utc()])->where('total', '>', 0)->orderBy('id')->get() as $c) {
            $s = json_decode((string) $c->dados, true) ?: [];
            $cliente = (array) ($s['customer_details'] ?? []);
            $lista[] = [
                'id' => $c->id,
                'hora' => self::hora($c->created_at),
                'total' => $c->total / 100,
                'iva' => isset($s['total_details']['amount_tax']) ? $s['total_details']['amount_tax'] / 100 : null,
                'desconto' => isset($s['total_details']['amount_discount']) ? $s['total_details']['amount_discount'] / 100 : 0,
                'moeda' => $c->moeda,
                'videos' => $c->videos,
                'lead_id' => $c->lead_id,
                'canal' => Canal::de(self::ler($c->origem ?? null)),
                // For the invoice: who, which company, which tax id, where.
                'email' => $c->email,
                'nome' => $c->nome,
                'empresa' => $cliente['business_name'] ?? null,
                'nif' => $c->nif,
                'pais' => $cliente['address']['country'] ?? null,
                'pagamento' => $s['payment_intent'] ?? null,
            ];
        }

        return [
            'total' => count($lista),
            'receita' => round(array_sum(array_column($lista, 'total')), 2),
            'iva' => round(array_sum(array_map(fn ($c) => $c['iva'] ?? 0, $lista)), 2),
            'lista' => $lista,
        ];
    }

    private function custos(CarbonImmutable $de, CarbonImmutable $ate): array
    {
        return DB::table('custos')->whereBetween('created_at', [$de->utc(), $ate->utc()])
            ->select('servico', DB::raw('round(sum(custo), 4) as usd'))->groupBy('servico')->pluck('usd', 'servico')
            ->map(fn ($v) => (float) $v)->all();
    }

    /**
     * Since launch (relatorio.inicio) up to the end of the period: revenue without VAT, what the
     * ads and the services cost, the Stripe fees (an estimate), what is left, and how many days
     * of today's budget, plus the free videos it brings, that pays for if nobody buys again.
     */
    private function financas(CarbonImmutable $ate): array
    {
        $inicio = CarbonImmutable::parse((string) config('contentmachine.relatorio.inicio'), self::FUSO)->startOfDay();
        $receita = 0.0;
        $iva = 0.0;
        foreach (DB::table('compras')->whereBetween('created_at', [$inicio->utc(), $ate->utc()])->where('total', '>', 0)->get(['total', 'dados']) as $c) {
            $s = json_decode((string) $c->dados, true) ?: [];
            $receita += $c->total / 100;
            // No tax recorded on the session = the Portuguese rate, the price includes it.
            $iva += isset($s['total_details']['amount_tax']) ? $s['total_details']['amount_tax'] / 100 : ($c->total / 100) * 23 / 123;
        }
        $semIva = $receita - $iva;
        $usdEur = (float) config('contentmachine.relatorio.usd_eur');
        $servicos = (float) DB::table('custos')->whereBetween('created_at', [$inicio->utc(), $ate->utc()])->sum('custo') * $usdEur;
        $meta = rescue(fn () => $this->funil->calcular($inicio->utc(), $ate->utc()), null, report: false);
        $anuncios = (float) ($meta['gasto'] ?? 0);
        $taxas = $receita * (float) config('contentmachine.relatorio.taxa_stripe');
        $folga = $semIva - $anuncios - $servicos - $taxas;

        $dias = max(1, $inicio->diffInDays(min($ate, now(self::FUSO))) + 1);
        $orcamento = (float) config('contentmachine.relatorio.orcamento_diario');
        $servicosPorDia = $servicos / $dias;
        $gastoDiario = $orcamento + $servicosPorDia;

        return [
            'desde' => $inicio->toDateString(),
            'receita_com_iva' => round($receita, 2),
            'iva' => round($iva, 2),
            'receita_sem_iva' => round($semIva, 2),
            'anuncios' => round($anuncios, 2),
            'anuncios_ok' => (bool) ($meta['meta']['ok'] ?? false),
            'servicos' => round($servicos, 2),
            'taxas_stripe_estimadas' => round($taxas, 2),
            'folga' => round($folga, 2),
            'orcamento_diario' => $orcamento,
            'servicos_por_dia' => round($servicosPorDia, 2),
            'dias_de_folga' => $gastoDiario > 0 ? round(max(0, $folga) / $gastoDiario, 1) : null,
        ];
    }

    private static function hora(?string $quando): ?string
    {
        return $quando ? CarbonImmutable::parse($quando, 'UTC')->setTimezone(self::FUSO)->format('Y-m-d H:i') : null;
    }

    private static function ler(?string $origem): ?array
    {
        $o = $origem ? json_decode($origem, true) : null;

        return is_array($o) && $o !== [] ? $o : null;
    }
}
