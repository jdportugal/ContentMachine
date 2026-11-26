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
        return ['versoes' => 'array'];
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

    /** Which frame the AI makes: 'inicio' (construção) or 'fim'. */
    public function quadroIa(): string
    {
        return $this->tipo()['ai'];
    }

    public function aTrabalhar(): bool
    {
        return in_array($this->estado, [self::A_ENQUADRAR, self::A_ANIMAR], true);
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
