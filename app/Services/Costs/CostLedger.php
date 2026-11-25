<?php

namespace App\Services\Costs;

use App\Services\Projects\ProjectContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ledger of what each piece of content COSTS in provider spend (kie.ai images,
 * API LLM tokens, voiceover characters). Jobs declare which piece they are
 * working on (contexto), the provider clients report spends as they happen
 * (registar), and the lists read the totals per piece.
 *
 * Registered as a singleton so the context set at the top of a queued job is
 * seen by every client resolved during that job. Claude CLI calls (subscription,
 * no marginal cost) are deliberately NOT recorded.
 */
class CostLedger
{
    private ?string $tipo = null;

    private ?string $peca = null;

    /** Columns every following spend carries (e.g. the Imoplanta step), until the next contexto(). */
    private array $marcas = [];

    /** Optional columns a spend may fill (see the Imoplanta migration). */
    private const EXTRAS = ['passo', 'modelo', 'tokens_entrada', 'tokens_saida', 'tokens_cache', 'unidades', 'moeda'];

    /**
     * Declare the piece the following spends belong to (null clears it), and
     * optionally columns they all carry — `['passo' => 'render']` — so a client
     * that knows nothing of the piece (KieClient) still records the step.
     */
    public function contexto(?string $tipo, ?string $peca, array $marcas = []): void
    {
        $this->tipo = $tipo;
        $this->peca = $peca;
        $this->marcas = $marcas;
    }

    /**
     * Record one spend against the current piece (USD unless `moeda` says
     * otherwise). Zero/negative is ignored. $extra fills the optional columns
     * (model, tokens, units…) and wins over the context's marks.
     */
    public function registar(string $servico, float $custo, string $detalhe = '', array $extra = []): void
    {
        if ($custo <= 0 || ! $this->pronto()) {
            return;
        }
        $linha = [
            'projeto' => app(ProjectContext::class)->current()->slug,
            'tipo' => $this->tipo ?? 'geral',
            'peca' => $this->peca ?? '',
            'servico' => $servico,
            'custo' => round($custo, 5),
            'detalhe' => $detalhe,
            'created_at' => now(),
        ];
        $extra = array_intersect_key(array_merge($this->marcas, $extra), array_flip(self::EXTRAS));
        if ($extra !== [] && $this->temExtras()) {
            $linha += $extra;
        }
        DB::table('custos')->insert($linha);
    }

    private ?bool $temExtras = null;

    /** The optional columns arrived with Imoplanta; an older schema just records without them. */
    private function temExtras(): bool
    {
        return $this->temExtras ??= Schema::hasColumn('custos', 'passo');
    }

    /** The ledger must NEVER break content work: no table (fresh install, tests
     * without migrations) simply means nothing is recorded or shown. */
    private function pronto(): bool
    {
        try {
            return Schema::hasTable('custos');
        } catch (\Throwable) {
            return false;
        }
    }

    /** Total USD per piece of the given type, for the active project. @return array<string,float> */
    public function totaisPorPeca(string $tipo): array
    {
        if (! $this->pronto()) {
            return [];
        }

        return DB::table('custos')
            ->where('projeto', app(ProjectContext::class)->current()->slug)
            ->where('tipo', $tipo)
            ->groupBy('peca')
            ->selectRaw('peca, SUM(custo) AS total')
            ->pluck('total', 'peca')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /** Per-service breakdown of one piece. @return array<string,float> */
    public function detalheDe(string $tipo, string $peca): array
    {
        if (! $this->pronto()) {
            return [];
        }

        return DB::table('custos')
            ->where('projeto', app(ProjectContext::class)->current()->slug)
            ->where(['tipo' => $tipo, 'peca' => $peca])
            ->groupBy('servico')
            ->selectRaw('servico, SUM(custo) AS total')
            ->pluck('total', 'servico')
            ->map(fn ($v) => (float) $v)
            ->all();
    }
}
