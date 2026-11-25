<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One Imoplanta property: its plan images, the house description read from
 * them (see ImoplantaCasa for the schema), the planned shots and their images.
 * The 3D model is that description: the browser builds it with three.js, so
 * the model is ready as soon as the description is.
 *
 * Flow: (a_pagar: plans saved, waiting for payment) → a_ler → modelo ⇄ a_alterar → imagens (then each image: a_renderizar,
 * i.e. waiting for the browser's render → a_fotografar → pronta ⇄ a_alterar).
 * Reading can end in `falhou` (with `erro`), an image too; tentarDeNovo()
 * resumes from the last good point at no cost. `versao` counts the house
 * descriptions so far: the page rebuilds the 3D view when it changes.
 */
class Imoplanta extends Model
{
    public const A_PAGAR = 'a_pagar';

    public const A_LER = 'a_ler';

    public const A_ALTERAR = 'a_alterar';

    public const MODELO = 'modelo';

    public const IMAGENS = 'imagens';

    /** Step 2: the model and cameras are fixed; the finishes board and each storey's 3D floor plan are made and reviewed. */
    public const REFERENCIAS = 'referencias';

    public const FALHOU = 'falhou';

    /** Status → what the customer reads while it works. */
    public const ETAPAS = [
        self::A_LER => 'A ler as plantas',
        self::A_ALTERAR => 'A aplicar a alteração',
    ];

    /** What kind of home the plans are for (asked when the property is created). */
    public const TIPOLOGIAS = [
        'isolada' => 'Moradia isolada',
        'geminada' => 'Moradia geminada',
        'banda' => 'Moradia em banda',
        'apartamento' => 'Apartamento',
    ];

    /** Floor-to-floor height of the building around a flat, in metres (the 3D model uses the same). */
    public const ANDAR = 3.0;

    protected $table = 'imoplantas';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'plantas' => 'array',
            'referencias' => 'array',
            'casa' => 'array',
            'cameras' => 'array',
            'pedidos' => 'array',
            'video_clips' => 'array',
            'aereos' => 'array',
            'acabamentos_pedidos' => 'array',
            'extras' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function imagens(): HasMany
    {
        return $this->hasMany(ImoplantaImagem::class)->orderBy('numero');
    }

    /** Where its files live on the Imoplanta disk. */
    public function pasta(): string
    {
        return 'imoplanta/'.$this->id;
    }

    public function aTrabalhar(): bool
    {
        return isset(self::ETAPAS[$this->estado]) || $this->video_estado === 'a_fazer'
            || collect($this->aereos ?? [])->contains(fn ($a) => ($a['estado'] ?? null) === 'a_fazer')
            || $this->extra('planta') === 'a_fazer'
            || $this->quadro_estado === 'a_fazer'
            || ($this->estado === self::REFERENCIAS && ! $this->referenciasProntas() && ! $this->referenciasFalharam())
            || $this->imagens->contains(fn (ImoplantaImagem $i) => $i->aTrabalhar());
    }

    public function refeitos3dRestantes(): int
    {
        return max(0, (int) config('contentmachine.imoplanta.refazer_3d') - $this->refeitos_3d);
    }

    /** The 3D model and the shot plan can change until images are made. */
    public function tipologiaRotulo(): string
    {
        $r = self::TIPOLOGIAS[$this->tipologia] ?? self::TIPOLOGIAS['isolada'];

        return $this->tipologia === 'apartamento' ? $r.' · '.($this->piso ? $this->piso.'.º andar' : 'rés-do-chão') : $r;
    }

    /** The house description as the browser builds it: plus the type and floor (the building around a flat). */
    public function casaParaVer(): ?array
    {
        return $this->casa ? ['tipo' => $this->tipologia, 'piso' => (int) $this->piso] + $this->casa : null;
    }

    /** One line for the plan reader: what kind of home this is and what that means for the model. */
    /** @return list<string> the images the plan reader gets: the plans, then the customer's other references */
    public function imagensParaLer(): array
    {
        return [...($this->plantas ?? []), ...($this->referencias ?? [])];
    }

    public function contextoPlantas(): string
    {
        $refs = count($this->referencias ?? []);

        return $this->contextoTipologia().($refs === 0 ? '' : "\n\nThe first ".count($this->plantas ?? []).' attached '
            .'image(s) are the plans. The last '.$refs.' are other references the customer uploaded for this property '
            .'(elevations, sections, photos of the house or the site, material samples): use them for storey and sill '
            .'heights, the roof, the size and position of openings, the façade and the surroundings. The plans rule the '
            .'layout of the rooms.').(($n = $this->notasCliente()) === '' ? '' : "\n\n".$n.' The plans rule the layout of the rooms.');
    }

    /**
     * What the customer told us about the home (their listing link and notes), as one line for the AI prompts;
     * empty when they told us nothing. It is their text: the prompts quote it, they don't obey it.
     */
    public function notasCliente(): string
    {
        $partes = array_filter([
            $this->notas ? 'The owner describes the home (quoted, in Portuguese): «'.str_replace(['«', '»'], '"', $this->notas).'».' : null,
            $this->anuncio ? 'Its listing or project page: '.$this->anuncio.'.' : null,
        ]);

        return implode(' ', $partes);
    }

    private function contextoTipologia(): string
    {
        return match ($this->tipologia) {
            'geminada' => 'The property is a SEMI-DETACHED house (moradia geminada): one of its side walls is a party wall '
                .'shared with the neighbouring house (no openings on it). Include that neighbour in "vizinhos", touching '
                .'the party wall, with about the same depth and height as this house.',
            'banda' => 'The property is a TERRACED house (moradia em banda): its two side walls are party walls shared with '
                .'the neighbouring houses of the row (no openings on them). Include both neighbours in "vizinhos", touching '
                .'those walls, with about the same depth and height as this house.',
            'apartamento' => 'The property is an APARTMENT on floor '.(int) $this->piso.' (0 = ground floor) of a '
                .'multi-storey residential building. Describe ONLY the apartment, as one level with "cota": 0.15 (the 3D '
                .'model lifts it to its floor and builds the rest of the building around it); its outer walls are the '
                .'building façade. Balconies are "terracos". "cobertura": [] (the floors above cover it). "lote": the '
                .'building footprint plus the pavement around it; no garden or pool unless the drawings show them.',
            default => 'The property is a DETACHED house (moradia isolada): free on all sides; neighbouring buildings, if '
                .'the site plan shows them, stand apart from it.',
        };
    }

    /** A storey's top view: 'render' (the browser's shot) or 'img' (the realistic one); null when not made. */
    public function aereo(int $nivel, string $qual = 'img'): ?string
    {
        return ($this->aereos ?? [])[$nivel][$qual] ?? null;
    }

    /** Sets one storey's top-view file, re-reading the row first so another storey's job isn't overwritten. */
    public function guardarAereo(int $nivel, string $qual, string|int|null $caminho): void
    {
        $this->guardarJson('aereos', $nivel, $qual, $caminho);
    }

    /** One of step 4's add-ons ('planta', 'video'): a field of it ('estado' by default); null when not asked. */
    public function extra(string $chave, string $campo = 'estado'): mixed
    {
        return ($this->extras ?? [])[$chave][$campo] ?? null;
    }

    public function guardarExtra(string $chave, string $campo, mixed $valor): void
    {
        $this->guardarJson('extras', $chave, $campo, $valor);
    }

    /** Sets $coluna[$chave][$campo] (null removes it), re-reading the row first so a parallel job's field isn't overwritten. */
    private function guardarJson(string $coluna, string|int $chave, string $campo, mixed $valor): void
    {
        DB::transaction(function () use ($coluna, $chave, $campo, $valor) {
            $a = self::whereKey($this->id)->lockForUpdate()->value($coluna);
            $a = is_string($a) ? (json_decode($a, true) ?: []) : (array) $a;
            $a[$chave] = array_filter([...($a[$chave] ?? []), $campo => $valor], fn ($x) => $x !== null);
            $this->forceFill([$coluna => $a])->save();
        });
    }

    /** Step 2 is done: the finishes board and every storey's realistic 3D floor plan exist, nothing is being changed. */
    public function referenciasProntas(): bool
    {
        return $this->acabamentos_img && $this->quadro_estado === null
            && collect($this->niveisComPlanta())->every(fn ($n) => $this->aereo($n) && ($this->aereos[$n]['estado'] ?? null) === null);
    }

    public function referenciasFalharam(): bool
    {
        return $this->quadro_estado === 'falhou' || collect($this->aereos ?? [])->contains(fn ($a) => ($a['estado'] ?? null) === 'falhou');
    }

    /** Changes left to the finishes board, or to storey $nivel's 3D floor plan. */
    public function refeitosReferencia(?int $nivel = null): int
    {
        $usados = $nivel === null ? (int) $this->acabamentos_refeitos : (int) (($this->aereos ?? [])[$nivel]['refeitos'] ?? 0);

        return max(0, (int) config('contentmachine.imoplanta.refazer_referencias') - $usados);
    }

    /** The storeys a top view is made of: those with rooms or walls. @return list<int> */
    public function niveisComPlanta(): array
    {
        return array_keys(array_filter($this->casa['niveis'] ?? [], fn ($n) => ! empty($n['paredes'])));
    }

    public function editavel(): bool
    {
        return $this->estado === self::MODELO;
    }

    /** Who may see it: its owner, or an admin. */
    public function visivelPara(User $user): bool
    {
        return $user->is_admin || $this->user_id === $user->id;
    }
}
