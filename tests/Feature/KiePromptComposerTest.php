<?php

namespace Tests\Feature;

use App\Services\Publicacoes\Dto\SlidePlano;
use App\Services\Publicacoes\Rendering\KiePromptComposer;
use Tests\TestCase;

class KiePromptComposerTest extends TestCase
{
    private function composer(): KiePromptComposer
    {
        // Points the design system at a non-existent path → default aesthetic.
        config(['contentmachine.design_system.path' => sys_get_temp_dir().'/nao-existe-'.uniqid().'.md']);

        return app(KiePromptComposer::class);
    }

    public function test_prompt_inclui_texto_exacto_e_estilo(): void
    {
        $composer = $this->composer();
        $prompt = $composer->paraCartao(new SlidePlano(1, 'Título Certo', 'Corpo do cartão'), [
            'proporcao' => '1:1', 'capa' => true, 'ordem' => 1, 'total' => 1,
        ]);

        $this->assertStringContainsString('Título Certo', $prompt);
        $this->assertStringContainsString('Corpo do cartão', $prompt);
        // The brand style directive (design's or the default) makes it into the prompt.
        $this->assertStringContainsString($composer->estiloMarca(), $prompt);
        $this->assertStringContainsString('Invariable rules', $prompt);
    }

    public function test_posicao_e_contexto_nunca_texto_a_desenhar(): void
    {
        $prompt = $this->composer()->paraCartao(new SlidePlano(3, 'Meio', 'x'), [
            'ordem' => 3, 'total' => 7,
        ]);

        // The position appears as explicit CONTEXT, not as text to compose.
        $this->assertStringContainsString('do not draw', $prompt);
        $this->assertStringContainsString('NO page numbering', $prompt);
        $this->assertStringContainsString('card 3 of 7', $prompt);
    }

    public function test_prompt_traz_coerencia_dos_cartoes_anteriores(): void
    {
        $prompt = $this->composer()->paraCartao(new SlidePlano(3, 'Terceiro', 'x'), [
            'ordem' => 3, 'total' => 5, 'postTitulo' => 'Guia',
            'anteriores' => ['Capa', 'Primeiro ponto'],
        ]);

        $this->assertStringContainsString('Capa', $prompt);
        $this->assertStringContainsString('Primeiro ponto', $prompt);
        $this->assertStringContainsString('same visual identity', mb_strtolower($prompt));
    }

    public function test_prompt_descreve_imagens_anexas(): void
    {
        $prompt = $this->composer()->paraCartao(new SlidePlano(1, 'Capa', 'x'), [
            'ordem' => 1, 'total' => 3, 'anexos' => ['logótipo Brand Machine', 'foto do produto'],
        ]);

        $this->assertStringContainsString('logótipo Brand Machine', $prompt);
        $this->assertStringContainsString('foto do produto', $prompt);
        $this->assertStringContainsString('attached to this card', $prompt);
    }
}
