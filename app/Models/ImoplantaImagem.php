<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One planned shot of an Imoplanta property: its render (made by the customer's
 * browser with three.js and uploaded, RENDER pixels) and the photoreal image
 * made from it.
 */
class ImoplantaImagem extends Model
{
    /** Width × height of the browser render the photographer starts from (16:9). */
    public const RENDER = [1600, 900];

    /** Waiting for the browser to render and upload this shot. */
    public const A_RENDERIZAR = 'a_renderizar';

    public const A_FOTOGRAFAR = 'a_fotografar';

    public const A_ALTERAR = 'a_alterar';

    public const PRONTA = 'pronta';

    public const FALHOU = 'falhou';

    protected $table = 'imoplanta_imagens';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['camera' => 'array'];
    }

    public function imoplanta(): BelongsTo
    {
        return $this->belongsTo(Imoplanta::class);
    }

    public function aTrabalhar(): bool
    {
        return in_array($this->estado, [self::A_RENDERIZAR, self::A_FOTOGRAFAR, self::A_ALTERAR], true);
    }

    public function refeitosRestantes(): int
    {
        return max(0, (int) config('contentmachine.imoplanta.refazer_imagem') - $this->refeitos);
    }
}
