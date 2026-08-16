<?php

namespace App\Services\Publicacoes\Rendering;

use App\Services\DesignSystem\DesignSystemRepository;
use App\Services\Publicacoes\Dto\SlidePlano;
use Illuminate\Support\Str;

/**
 * Composes the text prompt sent to kie.ai for each card. It lives in a shared
 * service so that the workshop can SHOW and EDIT the exact prompt the
 * renderer sends — the same composition is used on both sides.
 *
 * Three concerns this composer solves:
 *  - Carousel coherence: injects the piece title and the titles of the previous
 *    cards as CONTEXT (so the model keeps the same visual line).
 *  - Attachments: describes the reference images assigned to this card, so the
 *    text «makes sense» alongside them (the images themselves go as image_input).
 *  - No numbering on the card: the position (N of M) is only context and must NEVER
 *    be drawn — avoids the «3 out of 7» artefact printed on the image.
 */
class KiePromptComposer
{
    public function __construct(private readonly DesignSystemRepository $design) {}

    /**
     * From-scratch composition prompt for a card.
     *
     * @param  array{
     *   proporcao?:string, capa?:bool, ordem?:int, total?:int,
     *   postTitulo?:string, anteriores?:array<int,string>, anexos?:array<int,string>
     * }  $ctx
     */
    public function paraCartao(SlidePlano $s, array $ctx = []): string
    {
        $proporcao = (string) ($ctx['proporcao'] ?? '1:1');
        $capa = (bool) ($ctx['capa'] ?? false);
        $ordem = (int) ($ctx['ordem'] ?? 1);
        $total = (int) ($ctx['total'] ?? 1);
        $anteriores = array_values(array_filter($ctx['anteriores'] ?? [], fn ($t) => trim((string) $t) !== ''));
        $anexos = array_values(array_filter($ctx['anexos'] ?? [], fn ($d) => trim((string) $d) !== ''));

        $papel = $capa
            ? 'It is the COVER of the carousel: large, central, highly legible title.'
            : 'It is a CONTENT card: title at the top and the text below, with breathing room.';

        // The position is CONTEXT — never text to be drawn.
        $contexto = $total > 1
            ? "CONTEXT (do not draw): this is card {$ordem} of {$total} of a cohesive carousel"
                .($ctx['postTitulo'] ?? '' ? " titled «{$ctx['postTitulo']}»" : '').'.'
            : '';
        if ($anteriores !== []) {
            $contexto .= "\nPrevious cards (to keep the SAME visual identity, palette and composition): "
                .implode(' · ', array_map(fn ($t) => '«'.$t.'»', $anteriores)).'.';
        }

        $blocoAnexos = $anexos !== []
            ? "\n\nReference images attached to this card (use them as a visual guide and integrate them coherently): "
                .implode('; ', $anexos).'.'
            : '';

        $texto = $s->texto !== '' ? 'SUPPORTING TEXT (exact): «'.$s->texto.'»' : '';
        $estilo = $this->estiloMarca();

        return trim(<<<PROMPT
        Card for social media. Aspect ratio {$proporcao}. {$papel}
        {$contexto}

        {$estilo}{$blocoAnexos}

        Invariable rules: NO third-party brand logos, NO emojis, NO watermarks,
        NO page numbering or counters (do not write «{$ordem}/{$total}», «{$ordem} of {$total}» or anything of the sort on the card).
        Compose the following text, written EXACTLY like this, in European Portuguese:
        TITLE: «{$s->titulo}»
        {$texto}

        Text rule: render ALL text as real, sharp, well-spaced letters and WITHOUT spelling
        errors. Do not invent, translate or alter words. The text is the central element of the card.
        PROMPT);
    }

    /** Image→image edit prompt: starts from the given image and applies only the change. */
    public function paraEdicao(SlidePlano $s, string $instrucao): string
    {
        return trim(<<<PROMPT
        Edit the provided image of this card, keeping the SAME brand visual identity, the same
        composition and the same legible, error-free text.
        Apply ONLY this requested change: «{$instrucao}».
        Keep the title «{$s->titulo}» correct and legible. No emojis, no third-party logos,
        no page numbering.
        PROMPT);
    }

    /**
     * Style directive from the Design System (design.md + tokens). Without
     * a configured system, falls back to the default aesthetic.
     */
    public function estiloMarca(): string
    {
        $md = trim($this->design->read());
        $tokens = $this->design->readTokens();

        if ($md === '' && ! $tokens) {
            return 'Aesthetic: old book page / engraving. Cream parchment background, dark brown '
                .'ink, subtle fillet and ornaments in old gold, elegant centered '
                .'serif typography. Sober and cultured, without people or photo frames.';
        }

        $linhas = ['BRAND STYLE — apply this visual identity STRICTLY to the card:'];

        if ($md !== '') {
            $linhas[] = Str::limit($md, 1600);
        }

        if ($tokens) {
            $c = $tokens['colors'] ?? [];
            $f = $tokens['fonts'] ?? [];
            $partes = array_filter([
                (isset($c['bg'], $c['textOnBg']) ? "Palette: background {$c['bg']}, text {$c['textOnBg']}" : '')
                    .(isset($c['accent']) ? ", accent {$c['accent']}" : '').(isset($c['accent2']) ? " / {$c['accent2']}" : '').'.',
                isset($f['display'], $f['body']) ? "Typography: titles like «{$f['display']}», body like «{$f['body']}»." : '',
                ! empty($tokens['texture']['kind']) ? "Background/texture: {$tokens['texture']['kind']}." : '',
            ]);
            if ($partes !== []) {
                $linhas[] = 'Concrete tokens — '.implode(' ', $partes);
            }
        }

        return implode("\n\n", $linhas);
    }
}
