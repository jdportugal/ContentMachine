<?php

namespace App\Services\Publicacoes\Rendering;

use App\Services\Publicacoes\Dto\PublicacaoPlan;
use App\Services\Publicacoes\Dto\SlidePlano;
use App\Services\Support\DriverNotConfiguredException;

/**
 * Production driver: generates each card with the kie.ai API (nano-banana-pro),
 * like AdsMaker. Returns image URLs.
 *
 * Each card's text prompt comes from the KiePromptComposer (shared with the
 * workshop, which shows/edits it). If the workshop sends an already composed/edited
 * prompt (kind['_prompts'][i]), it is used as-is; otherwise it is composed here.
 *
 * Carousel coherence: each card receives as visual reference (image_input)
 * the global references + its own attachments + ALL the pages already generated before
 * it — to keep the same identity throughout the piece.
 *
 * Requires KIE_API_KEY. Without a key, the app uses the SvgSlideRenderer (see
 * AppServiceProvider), so this driver is not exercised offline.
 */
class KieSlideRenderer implements SlideRenderer
{
    /** kie.ai accepts at most 8 reference images (image_input) per request. */
    private const MAX_IMAGE_INPUT = 8;

    public function __construct(
        private readonly KieClient $kie,
        private readonly KiePromptComposer $composer,
    ) {}

    public function render(PublicacaoPlan $plan, array $kind): array
    {
        $this->exigirChave();

        $proporcao = (string) ($kind['proporcao'] ?? '1:1');
        $total = count($plan->slides);

        $baseRefs = $this->kie->carregarReferencias($kind['_refs'] ?? []);
        $anexosPaths = (array) ($kind['_anexos'] ?? []);   // [i => [path, …]]
        $anexosDescr = (array) ($kind['_anexosDescr'] ?? []); // [i => [description, …]]
        $prompts = (array) ($kind['_prompts'] ?? []);      // [i => edited prompt]

        $urls = [];
        $anteriores = [];        // kie URLs of already-generated pages (visual coherence)
        $anterioresTitulos = []; // previous titles (textual coherence in the prompt)

        foreach ($plan->slides as $i => $slide) {
            $anexoUrls = $this->kie->carregarReferencias($anexosPaths[$i] ?? []);

            $prompt = trim((string) ($prompts[$i] ?? '')) !== ''
                ? (string) $prompts[$i]
                : $this->composer->paraCartao($slide, [
                    'proporcao' => $proporcao,
                    'capa' => $i === 0,
                    'ordem' => $i + 1,
                    'total' => $total,
                    'postTitulo' => $plan->titulo,
                    'anteriores' => $anterioresTitulos,
                    'anexos' => array_values((array) ($anexosDescr[$i] ?? [])),
                ]);

            $url = $this->kie->generate(
                $prompt,
                $proporcao,
                $this->limitarInput($anexoUrls, $baseRefs, $anteriores),
            );

            $urls[] = $url;
            $anteriores[] = $url;
            $anterioresTitulos[] = $slide->titulo;
        }

        return $urls;
    }

    public function renderCartao(SlidePlano $slide, array $kind, ?string $refImagem = null, string $instrucao = '', int $ordem = 1, int $total = 1): string
    {
        $this->exigirChave();

        $proporcao = (string) ($kind['proporcao'] ?? '1:1');
        $baseRefs = $this->kie->carregarReferencias($kind['_refs'] ?? []);
        $anexoUrls = $this->kie->carregarReferencias((array) ($kind['_anexos'] ?? []));

        // Image→image edit: keeps the card and applies the instruction (+ references).
        if ($refImagem !== null && trim($instrucao) !== '') {
            $url = $this->kie->upload($refImagem, 'atual.png');

            return $this->kie->generate(
                $this->composer->paraEdicao($slide, $instrucao),
                $proporcao,
                // The current image (being edited) is the top priority.
                $this->limitarInput([$url], array_merge($anexoUrls, $baseRefs), []),
            );
        }

        // Compose again: use the prompt sent by the workshop, or compose it.
        $prompt = trim((string) ($kind['_prompt'] ?? '')) !== ''
            ? (string) $kind['_prompt']
            : $this->composer->paraCartao($slide, [
                'proporcao' => $proporcao,
                'capa' => $ordem === 1,
                'ordem' => $ordem,
                'total' => $total,
                'anexos' => array_values((array) ($kind['_anexosDescr'] ?? [])),
            ]);

        return $this->kie->generate($prompt, $proporcao, $this->limitarInput($anexoUrls, $baseRefs, []));
    }

    /**
     * Limits the image_input to the kie.ai maximum (8), in priority order:
     * card attachments → cover/identity anchor → global references →
     * most recent previous pages. This keeps coherence (cover + the
     * closest pages) without exceeding the API limit.
     *
     * @param  array<int,string>  $anexos     card-specific images
     * @param  array<int,string>  $globais    references applied to the whole piece
     * @param  array<int,string>  $anteriores already-generated pages (0 = cover)
     * @return array<int,string>
     */
    private function limitarInput(array $anexos, array $globais, array $anteriores): array
    {
        $capa = $anteriores !== [] ? [$anteriores[0]] : [];
        $recentes = array_reverse(array_slice($anteriores, 1)); // most recent first

        $ordenado = array_merge($anexos, $capa, $globais, $recentes);

        return array_slice(array_values(array_unique($ordenado)), 0, self::MAX_IMAGE_INPUT);
    }

    private function exigirChave(): void
    {
        if (! $this->kie->configurado()) {
            throw DriverNotConfiguredException::for('kie', 'KIE_API_KEY');
        }
    }
}
