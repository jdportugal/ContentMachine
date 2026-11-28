<?php

namespace App\Models;

use App\Services\Ferramentas\Ferramentas;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One AI video (Ferramentas IA): the customer's upload → the AI frame (start or end, by tool), changed up to
 * editar_imagem times → approved → the Kling clip, made again up to refazer_video times.
 */
class FerramentaIa extends Model
{
    /** Uploaded; no video spent yet. */
    public const RASCUNHO = 'rascunho';

    /** Making the AI frame (the first one, or a change). */
    public const A_ENQUADRAR = 'a_enquadrar';

    /** The frames wait for the customer: change the AI one, or approve both. */
    public const A_APROVAR = 'a_aprovar';

    /** Making the clip (the first one, or a new version). */
    public const A_ANIMAR = 'a_animar';

    public const PRONTO = 'pronto';

    protected $table = 'ferramentas_ia';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['versoes' => 'array', 'analise' => 'array', 'opcoes' => 'array', 'creditos' => 'float'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pasta(): string
    {
        return 'ferramentas/'.$this->id;
    }

    /** The tool's texts and settings (Ferramentas::lista()). */
    public function tipo(): array
    {
        return Ferramentas::lista()[$this->ferramenta];
    }

    /** The frames the AI makes, in order: ['fim'] (the side move: built from a depth map), ['inicio'] (construção) or both (drone). */
    public function quadrosIa(): array
    {
        return $this->tipo()['ai'];
    }

    /** A camera move's direction (the tool's `direcao` keys): the customer's, else direita / the first one. */
    public function direcao(): string
    {
        $opcoes = array_keys($this->tipo()['direcao'] ?? []);
        $escolha = $this->opcoes['direcao'] ?? null;

        return in_array($escolha, $opcoes, true) ? $escolha : (in_array('direita', $opcoes, true) || ! $opcoes ? 'direita' : $opcoes[0]);
    }

    /** The remodel's type of works (Ferramentas::lista()['remodelacao']['obras']): interior unless chosen. Null for other tools. */
    public function obra(): ?string
    {
        $obras = $this->tipo()['obras'] ?? null;

        return $obras ? (isset($obras[$this->opcoes['obra'] ?? '']) ? $this->opcoes['obra'] : array_key_first($obras)) : null;
    }

    /** The clip length: the tool's, or its type of works' (a build from the ground is longer). */
    public function segundos(): int
    {
        return $this->tipo()['obras'][$this->obra()]['segundos'] ?? $this->tipo()['segundos'];
    }

    public function aTrabalhar(): bool
    {
        return in_array($this->estado, [self::A_ENQUADRAR, self::A_ANIMAR], true);
    }

    /** The photo check is still running (AnalisarFotoJob). */
    public function aAnalisar(): bool
    {
        // a queue that never ran it doesn't hold the customer back: after 2 minutes, no verdict
        return ($this->analise['estado'] ?? null) === 'a_analisar' && $this->updated_at?->gt(now()->subMinutes(2));
    }

    /** The photo check found it isn't a good start for this tool (only a warning). */
    public function fotoNaoIdeal(): bool
    {
        return ($this->analise['estado'] ?? null) === 'feito' && ! ($this->analise['adequada'] ?? true);
    }

    /** What this video costs in credits: its tool's, or its type of works' (Ferramentas::creditos). */
    public function preco(): float|int
    {
        return \App\Services\Ferramentas\Ferramentas::creditos($this->ferramenta, $this->obra());
    }

    public function edicoesRestantes(): int
    {
        return max(0, (int) config('contentmachine.ferramentas.editar_imagem') - $this->edicoes);
    }

    public function refeitosRestantes(): int
    {
        return max(0, (int) config('contentmachine.ferramentas.refazer_video') - $this->refeitos);
    }
}
