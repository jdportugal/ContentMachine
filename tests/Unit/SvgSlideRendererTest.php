<?php

namespace Tests\Unit;

use App\Services\Publicacoes\Dto\PublicacaoPlan;
use App\Services\Publicacoes\Dto\SlidePlano;
use App\Services\Publicacoes\PublicacaoKinds;
use App\Services\Publicacoes\Rendering\SvgSlideRenderer;
use Tests\TestCase;

class SvgSlideRendererTest extends TestCase
{
    private function kind(string $tipo): array
    {
        return (new PublicacaoKinds)->get($tipo);
    }

    public function test_desenha_um_svg_por_cartao_do_carrossel(): void
    {
        $plano = new PublicacaoPlan('Guia', 'legenda', ['ia'], [
            new SlidePlano(1, 'Capa do guia', 'Promessa'),
            new SlidePlano(2, 'Segundo cartão', 'Conteúdo'),
            new SlidePlano(3, 'Terceiro cartão', 'Mais conteúdo'),
        ]);

        $svgs = (new SvgSlideRenderer)->render($plano, $this->kind('carrossel'));

        $this->assertCount(3, $svgs);
        foreach ($svgs as $svg) {
            $this->assertStringStartsWith('<svg', $svg);
            $this->assertStringContainsString('</svg>', $svg);
        }
        // Proporção 4:5 → viewBox 1080x1350.
        $this->assertStringContainsString('viewBox="0 0 1080 1350"', $svgs[0]);
        $this->assertStringContainsString('Segundo cartão', $svgs[1]);
    }

    public function test_single_desenha_um_unico_svg_quadrado(): void
    {
        $plano = new PublicacaoPlan('Sabia que', 'corpo', [], [new SlidePlano(1, 'Sabia que', 'corpo')]);

        $svgs = (new SvgSlideRenderer)->render($plano, $this->kind('post'));

        $this->assertCount(1, $svgs);
        $this->assertStringContainsString('viewBox="0 0 1080 1080"', $svgs[0]);
    }

    public function test_escapa_caracteres_especiais_no_texto(): void
    {
        $plano = new PublicacaoPlan('T', 'l', [], [new SlidePlano(1, 'A & B <script>', 'x')]);

        $svgs = (new SvgSlideRenderer)->render($plano, $this->kind('post'));

        $this->assertStringNotContainsString('<script>', $svgs[0]);
        $this->assertStringContainsString('&amp;', $svgs[0]);
    }
}
