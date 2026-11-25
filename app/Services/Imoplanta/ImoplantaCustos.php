<?php

namespace App\Services\Imoplanta;

use App\Models\Imoplanta;
use App\Services\Costs\CostLedger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What each Imoplanta property really costs, against what its credit brought
 * in — for the owner to check the €50 / €40 / €30 tiers. Every spend is a
 * `custos` row (tipo 'imoplanta', peca = property id) tagged with its step:
 *
 *   extract        LLM reads the plans          (tokens × config price, USD)
 *   ai_change_3d   LLM applies a change request (tokens × config price, USD)
 *   references     the finishes board and the aerial reference photo, once per property (kie.ai, Nano Banana Pro)
 *   image_generate photoreal image (kie.ai)     (per-image price by model, USD — KieClient records it)
 *   image_redo     an image's AI change          (same)
 *   video          the walkthrough: one 5 s Kling clip per photo (kie.ai, per-clip price)
 *   stripe_fee     Stripe's fee on the purchase, spread over its credits (EUR)
 *
 * The 3D model and the shot renders are made in the customer's browser
 * (three.js): no server compute to record. Prices live in config contentmachine.imoplanta.custos (and custos.kie_modelos).
 * Customers never see any of this: it is read only by the admin page and the
 * `imoplanta:custos` command.
 */
class ImoplantaCustos
{
    public const PASSOS = ['extract', 'ai_change_3d', 'references', 'image_generate', 'image_redo', 'video', 'furniture_video', 'stripe_fee'];

    public function __construct(private CostLedger $ledger) {}

    /** Every following spend in this process belongs to this property and step. */
    public function contexto(int $imovel, string $passo): void
    {
        $this->ledger->contexto('imoplanta', (string) $imovel, ['passo' => $passo]);
    }

    public function limpar(): void
    {
        $this->ledger->contexto(null, null);
    }

    /** Records one LLM call from its runClaude() envelope (API usage fields, or the CLI's). */
    public function llm(array $envelope): void
    {
        $u = $envelope['_usage'] ?? null;
        $modelo = (string) ($envelope['_model'] ?? '');
        if ($u === null && isset($envelope['usage'])) {   // the claude CLI's own envelope
            $u = [
                'input' => (int) ($envelope['usage']['input_tokens'] ?? 0) + (int) ($envelope['usage']['cache_creation_input_tokens'] ?? 0),
                'output' => (int) ($envelope['usage']['output_tokens'] ?? 0),
                'cached' => (int) ($envelope['usage']['cache_read_input_tokens'] ?? 0),
            ];
            $modelo = (string) array_key_first($envelope['modelUsage'] ?? []) ?: 'claude-cli';
        }
        if (! is_array($u)) {
            return;
        }
        [$pIn, $pOut, $pCache] = $this->precoLlm($modelo);
        $custo = ($u['input'] * $pIn + $u['output'] * $pOut + $u['cached'] * $pCache) / 1_000_000;
        $fornecedor = (string) ($envelope['_provider'] ?? 'llm');
        $this->ledger->registar($fornecedor, $custo, $fornecedor === 'claude-cli' ? 'subscription — priced as the API' : '', [
            'modelo' => $modelo, 'tokens_entrada' => $u['input'], 'tokens_saida' => $u['output'], 'tokens_cache' => $u['cached'],
        ]);
    }

    /** USD per 1M tokens [input, output, cached] — the longest configured prefix of the model id, else '*'. */
    public function precoLlm(string $modelo): array
    {
        $tabela = (array) config('contentmachine.imoplanta.custos.llm_mtok');
        $melhor = '*';
        foreach (array_keys($tabela) as $prefixo) {
            if ($prefixo !== '*' && str_starts_with($modelo, $prefixo) && strlen($prefixo) > strlen($melhor === '*' ? '' : $melhor)) {
                $melhor = $prefixo;
            }
        }

        return array_map('floatval', array_pad((array) ($tabela[$melhor] ?? [0, 0, 0]), 3, 0));
    }

    /** Stripe's fee on the purchase this property's credit came from, per credit. */
    public function stripe(Imoplanta $p): void
    {
        $compra = $p->compra_id ? DB::table('compras')->find($p->compra_id) : null;
        if (! $compra || $compra->videos < 1) {
            return;
        }
        $taxa = $compra->valor / 100 * (float) config('contentmachine.imoplanta.custos.stripe_pct') / 100
            + (float) config('contentmachine.imoplanta.custos.stripe_fixo');
        $this->contexto($p->id, 'stripe_fee');
        $this->ledger->registar('stripe', $taxa / $compra->videos, $compra->plano, ['moeda' => 'EUR', 'unidades' => 1]);
        $this->limpar();
    }

    /**
     * Every property with its revenue (its credit's share of the pack price),
     * cost per step and margin — all in EUR.
     *
     * @return Collection<int, array{id:int,nome:string,cliente:string,plano:string,receita:float,passos:array<string,float>,custo:float,margem:float,margem_pct:?float,refeitos_3d:int,refeitos_imagens:int,imagens:int}>
     */
    public function porImovel(): Collection
    {
        $usd = (float) config('contentmachine.imoplanta.custos.eur_por_usd');
        $custos = DB::table('custos')->where('tipo', 'imoplanta')
            ->selectRaw("peca, COALESCE(passo, 'outro') AS passo, SUM(CASE WHEN moeda = 'EUR' THEN custo ELSE custo * ? END) AS eur", [$usd])
            ->groupBy('peca', 'passo')->get()->groupBy('peca');

        return DB::table('imoplantas')->leftJoin('compras', 'compras.id', '=', 'imoplantas.compra_id')
            ->leftJoin('users', 'users.id', '=', 'imoplantas.user_id')
            ->orderByDesc('imoplantas.id')
            ->get(['imoplantas.id', 'imoplantas.nome', 'imoplantas.refeitos_3d', 'users.email', 'compras.plano', 'compras.valor', 'compras.videos'])
            ->map(function ($p) use ($custos) {
                $passos = array_fill_keys(self::PASSOS, 0.0);
                foreach ($custos->get((string) $p->id, []) as $c) {
                    $passos[$c->passo] = ($passos[$c->passo] ?? 0) + (float) $c->eur;
                }
                $custo = array_sum($passos);
                $receita = $p->valor && $p->videos ? $p->valor / 100 / $p->videos : 0.0;
                $imagens = DB::table('imoplanta_imagens')->where('imoplanta_id', $p->id);

                return [
                    'id' => (int) $p->id, 'nome' => $p->nome, 'cliente' => (string) $p->email,
                    'plano' => $p->plano ?? 'admin', 'receita' => round($receita, 2),
                    'passos' => array_map(fn ($v) => round($v, 4), $passos), 'custo' => round($custo, 4),
                    'margem' => round($receita - $custo, 2), 'margem_pct' => $receita > 0 ? round(($receita - $custo) / $receita * 100, 1) : null,
                    'refeitos_3d' => (int) $p->refeitos_3d, 'refeitos_imagens' => (int) (clone $imagens)->sum('refeitos'), 'imagens' => (clone $imagens)->count(),
                ];
            });
    }

    /**
     * Averages per tier (properties, revenue, cost, margin) and per step (mean
     * cost of that step across properties), plus the overall mean property.
     *
     * @return array{tiers: array<string,array{imoveis:int,receita:float,custo:float,margem:float}>, passos: array<string,float>, media: array{imoveis:int,custo:float,receita:float,margem:float}}
     */
    public function resumo(?Collection $linhas = null): array
    {
        $linhas ??= $this->porImovel();
        $media = fn (Collection $l) => [
            'imoveis' => $l->count(),
            'receita' => round((float) $l->avg('receita'), 2),
            'custo' => round((float) $l->avg('custo'), 4),
            'margem' => round((float) $l->avg('margem'), 2),
        ];

        return [
            'tiers' => $linhas->groupBy('plano')->map($media)->sortKeys()->all(),
            'passos' => collect(self::PASSOS)->mapWithKeys(fn ($s) => [$s => round((float) $linhas->avg(fn ($l) => $l['passos'][$s] ?? 0), 4)])->all(),
            'media' => $media($linhas),
        ];
    }
}
