<?php

namespace App\Services\Publicacoes\Rendering;

use App\Services\Publicacoes\Dto\PublicacaoPlan;
use App\Services\Publicacoes\Dto\SlidePlano;

/**
 * Draws a plan's cards into images. Each implementation is a "driver":
 *   - SvgSlideRenderer: deterministic SVG drawing, offline (default).
 *   - KieSlideRenderer: generation via kie.ai (requires a key), like AdsMaker.
 *
 * Returns one string per card. Convention: a string starting with «<svg»
 * is inline markup; otherwise it is an image URL (or data-URI).
 */
interface SlideRenderer
{
    /**
     * @param  array<string,mixed>  $kind  type definition (config publicacoes.tipos)
     * @return array<int,string>  one image per card, in card order
     */
    public function render(PublicacaoPlan $plan, array $kind): array;

    /**
     * Draws ONE card. If $refImagem (bytes) and $instrucao are given, the driver
     * regenerates from the existing image (image→image edit); otherwise
     * composes again from the card text.
     *
     * @param  array<string,mixed>  $kind
     * @param  string|null  $refImagem  reference image bytes (or null)
     * @param  int  $ordem  1-based; 1 = cover
     */
    public function renderCartao(SlidePlano $slide, array $kind, ?string $refImagem = null, string $instrucao = '', int $ordem = 1, int $total = 1): string;
}
