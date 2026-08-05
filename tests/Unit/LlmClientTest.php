<?php

namespace Tests\Unit;

use App\Services\Aggregation\LlmClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The news write-up degrades to a dry heuristic whenever no provider answers, so
 * the chain must include every LLM the app can be configured with.
 */
class LlmClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'contentmachine.aggregation.llm_provider' => 'auto',
            // No `claude` CLI: the deployed server has no interactive session either.
            'contentmachine.aggregation.claude_cli_bin' => '/nonexistent/claude',
            'services.anthropic.key' => null,
            'services.openai.key' => null,
            'services.gemini.key' => null,
            'services.tensorx.key' => null,
        ]);
    }

    /** A deploy whose only key is Tensorix must still WRITE (not fall to the heuristic). */
    public function test_tensorx_alone_is_a_usable_provider(): void
    {
        config(['services.tensorx.key' => 'tx-key', 'services.tensorx.model' => 'deepseek/deepseek-r1-0528']);
        Http::fake(['*/chat/completions' => Http::response(['choices' => [['message' => ['content' => 'As notícias da semana']]]])]);

        $llm = new LlmClient;

        $this->assertTrue($llm->disponivel());
        $this->assertSame('As notícias da semana', $llm->texto('escreve'));
        $this->assertSame('tensorx', $llm->fornecedorAtivo());
    }

    /** The house order: Claude → GPT → DeepSeek (Tensorix). */
    public function test_chain_order_is_claude_then_gpt_then_deepseek(): void
    {
        config([
            'services.anthropic.key' => 'sk-ant',
            'services.openai.key' => 'sk-oai',
            'services.tensorx.key' => 'tx-key',
        ]);
        Http::fake([
            'api.anthropic.com/*' => Http::response([], 500),                    // Claude down…
            'api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => 'GPT']]]]),
            '*' => Http::response(['choices' => [['message' => ['content' => 'DeepSeek']]]]),
        ]);

        // Claude is tried first, fails, and GPT (not DeepSeek) answers next.
        $llm = new LlmClient;
        $this->assertSame('GPT', $llm->texto('escreve'));
        $this->assertSame('openai', $llm->fornecedorAtivo());
    }

    /** With Claude AND GPT down it still lands on DeepSeek instead of giving up. */
    public function test_chain_reaches_deepseek_when_the_two_before_it_fail(): void
    {
        config([
            'services.anthropic.key' => 'sk-ant',
            'services.openai.key' => 'sk-oai',
            'services.tensorx.key' => 'tx-key',
        ]);
        Http::fake([
            'api.anthropic.com/*' => Http::response([], 500),
            'api.openai.com/*' => Http::response([], 500),
            '*' => Http::response(['choices' => [['message' => ['content' => 'DeepSeek']]]]),
        ]);

        $this->assertSame('DeepSeek', (new LlmClient)->texto('escreve'));
    }

    /** An explicitly configured provider goes first, but the others remain as fallback. */
    public function test_configured_provider_wins_and_still_falls_back(): void
    {
        config([
            'contentmachine.aggregation.llm_provider' => 'tensorx',
            'services.openai.key' => 'sk-oai',
            'services.tensorx.key' => 'tx-key',
        ]);
        Http::fake([
            'api.tensorx.ai/*' => Http::response(['choices' => [['message' => ['content' => 'DeepSeek']]]]),
            'api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => 'GPT']]]]),
        ]);

        $this->assertSame('DeepSeek', (new LlmClient)->texto('escreve'));
    }

    /** …and when the chosen one is down, the rest of the chain still answers. */
    public function test_configured_provider_down_falls_through_to_the_others(): void
    {
        config([
            'contentmachine.aggregation.llm_provider' => 'tensorx',
            'services.openai.key' => 'sk-oai',
            'services.tensorx.key' => 'tx-key',
        ]);
        Http::fake([
            'api.tensorx.ai/*' => Http::response([], 500),
            'api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => 'GPT']]]]),
        ]);

        $this->assertSame('GPT', (new LlmClient)->texto('escreve'));
    }

    public function test_no_keys_means_no_provider(): void
    {
        config(['contentmachine.aggregation.llm_provider' => 'none']);

        $this->assertFalse((new LlmClient)->disponivel());
    }
}
