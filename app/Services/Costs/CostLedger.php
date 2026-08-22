<?php

namespace App\Services\Costs;

use App\Services\Projects\ProjectContext;
use Illuminate\Support\Facades\DB;

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

    /** Declare the piece the following spends belong to (null clears it). */
    public function contexto(?string $tipo, ?string $peca): void
    {
        $this->tipo = $tipo;
        $this->peca = $peca;
    }

    /** Record one spend (USD) against the current piece. Zero/negative is ignored. */
    public function registar(string $servico, float $custo, string $detalhe = ''): void
    {
        if ($custo <= 0 || ! $this->pronto()) {
            return;
        }
        DB::table('custos')->insert([
            'projeto' => app(ProjectContext::class)->current()->slug,
            'tipo' => $this->tipo ?? 'geral',
            'peca' => $this->peca ?? '',
            'servico' => $servico,
            'custo' => round($custo, 5),
            'detalhe' => $detalhe,
            'created_at' => now(),
        ]);
    }

    /** The ledger must NEVER break content work: no table (fresh install, tests
     * without migrations) simply means nothing is recorded or shown. */
    private function pronto(): bool
    {
        try {
            return \Illuminate\Support\Facades\Schema::hasTable('custos');
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
