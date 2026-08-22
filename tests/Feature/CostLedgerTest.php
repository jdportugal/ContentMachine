<?php

namespace Tests\Feature;

use App\Services\Aggregation\LlmClient;
use App\Services\Costs\CostLedger;
use App\Services\Publicacoes\Rendering\KieClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CostLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_regista_e_soma_por_peca(): void
    {
        $ledger = app(CostLedger::class);

        $ledger->contexto('clip', 'clip-1');
        $ledger->registar('kie.ai', 0.04, 'image');
        $ledger->registar('elevenlabs', 0.10, '333 chars');
        $ledger->contexto('clip', 'clip-2');
        $ledger->registar('kie.ai', 0.04, 'image');
        $ledger->registar('kie.ai', 0.0, 'free — ignored');

        $totais = $ledger->totaisPorPeca('clip');
        $this->assertEqualsWithDelta(0.14, $totais['clip-1'], 0.0001);
        $this->assertEqualsWithDelta(0.04, $totais['clip-2'], 0.0001);
        $this->assertSame(['elevenlabs' => 0.1, 'kie.ai' => 0.04], app(CostLedger::class)->detalheDe('clip', 'clip-1'));
    }

    public function test_cada_imagem_kie_entra_no_livro_de_custos(): void
    {
        config(['services.kie.key' => 'k', 'contentmachine.custos.kie_imagem' => 0.05]);
        Http::fake([
            '*createTask*' => Http::response(['data' => ['taskId' => 't1']]),
            '*recordInfo*' => Http::response(['data' => ['state' => 'success', 'resultJson' => json_encode(['resultUrls' => ['https://kie/img.png']])]]),
        ]);

        app(CostLedger::class)->contexto('publicacao', 'nota-x');
        (new KieClient)->generate('a card', '1:1');

        $this->assertEqualsWithDelta(0.05, app(CostLedger::class)->totaisPorPeca('publicacao')['nota-x'], 0.0001);
    }

    public function test_tokens_da_api_anthropic_entram_no_livro(): void
    {
        config(['contentmachine.custos.llm_mtok.anthropic' => [10.0, 20.0]]);
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['text' => 'ok']],
            'usage' => ['input_tokens' => 1_000_000, 'output_tokens' => 500_000],
        ])]);

        app(CostLedger::class)->contexto('publicacao', 'nota-y');
        $llm = new class extends LlmClient
        {
            public function chamaAnthropic(string $prompt): ?string
            {
                $r = (new \ReflectionMethod(LlmClient::class, 'anthropic'))->invoke($this, 'chave', $prompt);

                return $r;
            }
        };
        $llm->chamaAnthropic('hello');

        // 1M in × $10/M + 0.5M out × $20/M = 10 + 10 = $20
        $this->assertEqualsWithDelta(20.0, app(CostLedger::class)->totaisPorPeca('publicacao')['nota-y'], 0.001);
    }
}
