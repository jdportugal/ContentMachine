<?php

namespace Tests\Unit;

use App\Services\Aggregation\LlmClient;
use App\Services\Publicacoes\PublicacaoKinds;
use App\Services\Publicacoes\PublicacaoPlanner;
use Tests\TestCase;

class PublicacaoPlannerTest extends TestCase
{
    /** Último prompt recebido pelo LlmClient falso (para asserções de injeção). */
    private ?string $ultimoPrompt = null;

    private function planner(?string $resposta): PublicacaoPlanner
    {
        // LlmClient falso: regista o prompt e devolve a resposta canónica (ou null).
        $test = $this;
        $llm = new class($resposta, $test) extends LlmClient
        {
            public function __construct(private ?string $resposta, private PublicacaoPlannerTest $test) {}

            public function texto(string $prompt, bool $comFerramentas = false, bool $json = false): ?string
            {
                $this->test->registarPrompt($prompt);

                return $this->resposta;
            }
        };

        return new PublicacaoPlanner($llm, new PublicacaoKinds);
    }

    public function registarPrompt(string $prompt): void
    {
        $this->ultimoPrompt = $prompt;
    }

    public function test_usa_o_json_da_ia_quando_disponivel(): void
    {
        $json = <<<'JSON'
        ```json
        {
          "titulo": "5 termos para começar",
          "legenda": "Um guia breve.",
          "tags": ["ia", "glossario"],
          "slides": [
            {"ordem": 1, "titulo": "Capa", "texto": "5 termos essenciais"},
            {"ordem": 2, "titulo": "Token", "texto": "A unidade mínima de texto."},
            {"ordem": 3, "titulo": "Prompt", "texto": "A instrução que damos ao modelo."}
          ]
        }
        ```
        JSON;

        $plano = $this->planner($json)->planear('carrossel', 'termos de IA', 'instagram');

        $this->assertSame('5 termos para começar', $plano->titulo);
        $this->assertCount(3, $plano->slides);
        $this->assertSame('Token', $plano->slides[1]->titulo);
        $this->assertSame([1, 2, 3], array_map(fn ($s) => $s->ordem, $plano->slides));
        $this->assertContains('ia', $plano->tags);
    }

    public function test_heuristica_quando_a_ia_nao_responde(): void
    {
        $plano = $this->planner(null)->planear(
            'carrossel',
            "Primeira ideia importante. Segunda ideia. Terceira ideia relevante.",
            'linkedin'
        );

        // Carrossel exige >= 2 cartões e respeita o máximo do tipo.
        $this->assertGreaterThanOrEqual(2, count($plano->slides));
        $this->assertLessThanOrEqual(10, count($plano->slides));
        $this->assertNotSame('', $plano->titulo);
        $this->assertNotSame('', $plano->slides[0]->titulo);
    }

    public function test_heuristica_single_produz_um_cartao(): void
    {
        $plano = $this->planner(null)->planear('post', 'Uma ideia simples e directa.', 'instagram');

        $this->assertCount(1, $plano->slides);
        $this->assertNotSame('', $plano->legenda);
    }

    public function test_injeta_sistema_de_design_no_prompt(): void
    {
        $tmp = sys_get_temp_dir().'/cm-ds-planner-'.uniqid().'.md';
        file_put_contents($tmp, "# Marca\n\nAssinatura secreta: RUBRICA-XYZ.");
        config(['contentmachine.design_system.path' => $tmp]);

        try {
            // Um brief não vazio força o caminho da IA (que constrói o prompt).
            $this->planner('resposta inválida')->planear('post', 'um tema', 'instagram');

            $this->assertNotNull($this->ultimoPrompt);
            $this->assertStringContainsString('DESIGN SYSTEM', (string) $this->ultimoPrompt);
            $this->assertStringContainsString('RUBRICA-XYZ', (string) $this->ultimoPrompt);
        } finally {
            @unlink($tmp);
        }
    }

    public function test_prompt_sem_sistema_de_design_quando_inexistente(): void
    {
        config(['contentmachine.design_system.path' => sys_get_temp_dir().'/cm-nao-existe-'.uniqid().'.md']);

        $this->planner('resposta inválida')->planear('post', 'um tema', 'instagram');

        $this->assertNotNull($this->ultimoPrompt);
        $this->assertStringNotContainsString('DESIGN SYSTEM', (string) $this->ultimoPrompt);
    }
}
