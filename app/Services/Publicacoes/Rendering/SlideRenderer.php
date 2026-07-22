<?php

namespace App\Services\Publicacoes\Rendering;

use App\Services\Publicacoes\Dto\PublicacaoPlan;

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
}
