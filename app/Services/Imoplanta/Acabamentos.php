<?php

namespace App\Services\Imoplanta;

use App\Models\Imoplanta;
use App\Services\Publicacoes\Rendering\KieClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * One set of finishes per property, so every photo of it shows the same floor wood, bathroom tiles, interior
 * doors and window frames. Before the first photo, a finishes board (one image with a sample of each) is made
 * with Nano Banana Pro; every photo then gets the board as a second reference image and the same finish list in
 * its prompt. The board is made once (under a lock: the photo jobs run in parallel) and kept for re-takes.
 * If the board fails, the photos still go out with the finish list alone.
 */
class Acabamentos
{
    /** Curated palettes: what each finish is, in the words the image model gets. */
    public const PALETAS = [
        'carvalho' => [
            'nome' => 'Carvalho natural',
            'chao' => 'wide-plank natural oak floor, matte, light honey tone, the same boards in every room except the bathrooms and the kitchen',
            'wc' => 'large-format 60×120 cm light warm-grey stone-look porcelain tiles on the bathroom floors and shower walls, thin pale joints',
            'cozinha' => 'matte white handle-less kitchen fronts, white quartz worktop, the same light porcelain on the kitchen floor',
            'portas' => 'flush full-height interior doors painted matte white, slim black lever handles, white frames, all identical',
            'caixilhos' => 'slim anthracite-grey (RAL 7016) aluminium window and sliding-door frames, identical everywhere',
            'fachada' => 'smooth white render, light grey granite cladding, anthracite metalwork',
        ],
        'nogueira' => [
            'nome' => 'Nogueira quente',
            'chao' => 'wide-plank warm walnut-tone wood floor, satin, the same boards in every room except the bathrooms and the kitchen',
            'wc' => 'large-format 60×120 cm beige travertine-look porcelain tiles on the bathroom floors and shower walls',
            'cozinha' => 'warm walnut kitchen fronts with slim black handles, beige stone worktop, the same beige porcelain on the kitchen floor',
            'portas' => 'flush full-height interior doors in walnut veneer, slim black lever handles, all identical',
            'caixilhos' => 'slim black aluminium window and sliding-door frames, identical everywhere',
            'fachada' => 'warm off-white render, beige limestone cladding, black metalwork',
        ],
        'freixo' => [
            'nome' => 'Freixo claro',
            'chao' => 'pale ash wood floor with a whitened matte finish, long planks, the same boards in every room except the bathrooms and the kitchen',
            'wc' => 'white matte 30×60 cm wall tiles and light concrete-look 60×60 cm floor tiles in the bathrooms',
            'cozinha' => 'light grey-green matte kitchen fronts with brushed brass pulls, white marble-look worktop, light concrete-look floor tiles',
            'portas' => 'flush full-height interior doors painted matte white, brushed steel lever handles, all identical',
            'caixilhos' => 'slim white aluminium window and sliding-door frames, identical everywhere',
            'fachada' => 'smooth white render, pale wood cladding, white metalwork',
        ],
    ];

    public function __construct(private KieClient $kie, private ImoplantaCustos $custos) {}

    /** This property's palette (its own, or one picked from its id: consistent, and varied across properties). */
    public function paleta(Imoplanta $p): array
    {
        $chaves = array_keys(self::PALETAS);

        return self::PALETAS[$p->acabamentos] ?? self::PALETAS[$chaves[$p->id % count($chaves)]];
    }

    /** The finish list for a photo prompt. */
    public function texto(Imoplanta $p): string
    {
        $a = $this->paleta($p);

        return "Floors: {$a['chao']}. Bathrooms: {$a['wc']}. Kitchen: {$a['cozinha']}. Interior doors: {$a['portas']}. "
            ."Window frames: {$a['caixilhos']}. Façade: {$a['fachada']}.".$this->doCliente($p);
    }

    /** The customer's own description and changes to the board: where they name a finish, they win over the palette. */
    private function doCliente(Imoplanta $p): string
    {
        $mudancas = array_map(fn ($x) => '«'.str_replace(['«', '»'], '"', $x).'»', (array) ($p->acabamentos_pedidos ?? []));

        return ($p->notas ? ' '.$p->notasCliente().' Where the owner names a finish or material, use theirs instead of the list above.' : '')
            .($mudancas ? ' The owner then changed the finishes (quoted, in Portuguese, in order; the later wins): '.implode('; ', $mudancas).'. These changes win over everything above.' : '');
    }

    /**
     * The board's path on the Imoplanta disk, made now if missing; null if it can't be made (no key, kie
     * failure) — the photos then use the finish list alone. $passo: the cost step to go back to afterwards.
     */
    public function garantir(Imoplanta $p, string $passo): ?string
    {
        $disk = Storage::disk(config('contentmachine.imoplanta.disk'));
        $pronto = fn () => ($f = $p->refresh()->acabamentos_img) && $disk->exists($f) ? $f : null;
        if ($f = $pronto()) {
            return $f;
        }
        if (! $this->kie->configurado()) {
            return null;
        }

        return Cache::lock('imoplanta-acabamentos-'.$p->id, 900)->block(900, function () use ($p, $disk, $pronto, $passo) {
            if ($f = $pronto()) {                       // another photo job made it while this one waited
                return $f;
            }
            $chave = $p->acabamentos && isset(self::PALETAS[$p->acabamentos])
                ? $p->acabamentos : array_keys(self::PALETAS)[$p->id % count(self::PALETAS)];
            try {
                $this->custos->contexto($p->id, 'references');
                $this->kie->passo = 'imoplanta_imagens';
                $prompt = $this->promptQuadro($p);
                $url = Fotografo::comAlternativa(fn () => $this->kie->submit($prompt, '16:9'), fn () => $this->kie->submitAlternativo($prompt, '16:9'), fn ($t) => $this->kie->poll($t));
                $f = $p->pasta().'/acabamentos-'.$chave.'.png';
                $disk->put($f, Http::timeout(120)->get($url)->throw()->body());
                $p->update(['acabamentos' => $chave, 'acabamentos_img' => $f]);

                return $f;
            } catch (\Throwable $e) {
                report($e);

                return null;
            } finally {
                $this->custos->contexto($p->id, $passo);
            }
        });
    }

    public function promptQuadro(Imoplanta $p): string
    {
        $a = $this->paleta($p);

        return <<<PROMPT
        A photorealistic interior-design FINISHES BOARD (material sample board) for a newly built home in Portugal, photographed from straight above on a neutral light-grey background, soft even daylight, no text, no labels, no people. Six neatly arranged real samples, each clearly separate:
        1. a large piece of the floor: {$a['chao']};
        2. a bathroom tile sample: {$a['wc']};
        3. a kitchen front with its worktop: {$a['cozinha']};
        4. a miniature of the interior door with its handle: {$a['portas']};
        5. a corner section of the window frame: {$a['caixilhos']};
        6. façade samples: {$a['fachada']}.
        Real materials with real texture (wood grain, stone veining, painted surfaces), true colours, sharp focus.{$this->doCliente($p)}
        PROMPT;
    }
}
