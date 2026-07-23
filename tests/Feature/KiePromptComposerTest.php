<?php

namespace Tests\Feature;

use App\Services\Publicacoes\Dto\SlidePlano;
use App\Services\Publicacoes\Rendering\KiePromptComposer;
use Tests\TestCase;

class KiePromptComposerTest extends TestCase
{
    private function composer(): KiePromptComposer
    {
        // Aponta o sistema de design para um caminho inexistente → estética por omissão.
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
        // A diretriz de estilo de marca (seja a do design, seja a de omissão) entra no prompt.
        $this->assertStringContainsString($composer->estiloMarca(), $prompt);
        $this->assertStringContainsString('Regras invariáveis', $prompt);
    }

    public function test_posicao_e_contexto_nunca_texto_a_desenhar(): void
    {
        $prompt = $this->composer()->paraCartao(new SlidePlano(3, 'Meio', 'x'), [
            'ordem' => 3, 'total' => 7,
        ]);

        // A posição aparece como CONTEXTO explícito, não como texto a compor.
        $this->assertStringContainsString('não desenhar', $prompt);
        $this->assertStringContainsString('SEM numeração de página', $prompt);
        $this->assertStringContainsString('cartão 3 de 7', $prompt);
    }

    public function test_prompt_traz_coerencia_dos_cartoes_anteriores(): void
    {
        $prompt = $this->composer()->paraCartao(new SlidePlano(3, 'Terceiro', 'x'), [
            'ordem' => 3, 'total' => 5, 'postTitulo' => 'Guia',
            'anteriores' => ['Capa', 'Primeiro ponto'],
        ]);

        $this->assertStringContainsString('Capa', $prompt);
        $this->assertStringContainsString('Primeiro ponto', $prompt);
        $this->assertStringContainsString('mesma identidade visual', mb_strtolower($prompt));
    }

    public function test_prompt_descreve_imagens_anexas(): void
    {
        $prompt = $this->composer()->paraCartao(new SlidePlano(1, 'Capa', 'x'), [
            'ordem' => 1, 'total' => 3, 'anexos' => ['logótipo IATECA', 'foto do produto'],
        ]);

        $this->assertStringContainsString('logótipo IATECA', $prompt);
        $this->assertStringContainsString('foto do produto', $prompt);
        $this->assertStringContainsString('referência anexas', $prompt);
    }
}
