<?php

namespace App\Services\Publicacoes\Rendering;

use App\Services\Publicacoes\Dto\PublicacaoPlan;
use App\Services\Publicacoes\Dto\SlidePlano;

/**
 * Desenha os cartões de um plano em imagens. Cada implementação é um "driver":
 *   - SvgSlideRenderer: desenho SVG determinístico, offline (por omissão).
 *   - KieSlideRenderer: geração via kie.ai (requer chave), à imagem do AdsMaker.
 *
 * Devolve uma string por cartão. Convenção: uma string que começa por «<svg»
 * é markup inline; caso contrário é um URL (ou data-URI) de imagem.
 */
interface SlideRenderer
{
    /**
     * @param  array<string,mixed>  $kind  definição do tipo (config publicacoes.tipos)
     * @return array<int,string>  uma imagem por cartão, pela ordem dos cartões
     */
    public function render(PublicacaoPlan $plan, array $kind): array;

    /**
     * Desenha UM cartão. Se $refImagem (bytes) e $instrucao forem dados, o driver
     * regenera a partir da imagem existente (edição imagem→imagem); caso
     * contrário compõe de novo a partir do texto do cartão.
     *
     * @param  array<string,mixed>  $kind
     * @param  string|null  $refImagem  bytes da imagem de referência (ou null)
     * @param  int  $ordem  1-based; 1 = capa
     */
    public function renderCartao(SlidePlano $slide, array $kind, ?string $refImagem = null, string $instrucao = '', int $ordem = 1, int $total = 1): string;
}
