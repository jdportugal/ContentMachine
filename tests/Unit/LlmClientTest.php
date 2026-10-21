<?php

namespace Tests\Unit;

use App\Services\Aggregation\LlmClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

    /**
     * A dead primary provider used to fall through in silence — the report still got
     * written, just by the cheap model down the chain, with nothing to say so. Both the
     * refusal reason and the fallback itself must reach the log.
     */
    public function test_a_dead_primary_provider_is_logged_not_swallowed(): void
    {
        config(['services.anthropic.key' => 'sk-ant', 'services.openai.key' => 'sk-oai']);
        Http::fake([
            'api.anthropic.com/*' => Http::response(['error' => ['message' => 'API key is invalid.']], 401),
            'api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => 'GPT']]]]),
        ]);
        Log::spy();

        $this->assertSame('GPT', (new LlmClient)->texto('escreve'));

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $m) => str_contains($m, "'anthropic' refused (HTTP 401)") && str_contains($m, 'API key is invalid.'))
            ->once();
        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $m) => str_contains($m, "fell back to 'openai'") && str_contains($m, "'anthropic' produced nothing"))
            ->once();
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

    /**
     * «Search the web» has to mean it on the API path too: the Claude API gets the
     * server-side web_search tool, and the answer is every text block joined
     * (search results come back as their own blocks in between).
     */
    public function test_anthropic_with_tools_declares_web_search_and_joins_text_blocks(): void
    {
        config(['services.anthropic.key' => 'sk-ant']);
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [
            ['type' => 'text', 'text' => 'Looking that up. '],
            ['type' => 'server_tool_use', 'id' => 'x', 'name' => 'web_search', 'input' => ['query' => 'q']],
            ['type' => 'web_search_tool_result', 'tool_use_id' => 'x', 'content' => []],
            ['type' => 'text', 'text' => '**Hook** found it.'],
        ], 'usage' => ['input_tokens' => 1, 'output_tokens' => 1]])]);

        $this->assertSame('Looking that up. **Hook** found it.', (new LlmClient)->texto('pesquisa', comFerramentas: true));
        Http::assertSent(fn ($r) => $r['tools'][0]['type'] === 'web_search_20260209' && $r['tools'][0]['name'] === 'web_search');
    }

    public function test_anthropic_without_tools_sends_no_tools(): void
    {
        config(['services.anthropic.key' => 'sk-ant']);
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => 'ok']]])]);

        $this->assertSame('ok', (new LlmClient)->texto('escreve'));
        Http::assertSent(fn ($r) => ! array_key_exists('tools', $r->data()));
    }

    /** OpenAI with tools goes through the Responses API with its hosted web_search. */
    public function test_openai_with_tools_uses_responses_api_web_search(): void
    {
        config(['services.openai.key' => 'sk-oai']);
        Http::fake(['api.openai.com/v1/responses' => Http::response(['output' => [
            ['type' => 'web_search_call', 'id' => 'ws', 'status' => 'completed'],
            ['type' => 'message', 'content' => [['type' => 'output_text', 'text' => '**Hook** via GPT']]],
        ], 'usage' => ['input_tokens' => 1, 'output_tokens' => 1]])]);

        $this->assertSame('**Hook** via GPT', (new LlmClient)->texto('pesquisa', comFerramentas: true));
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/v1/responses') && $r['tools'][0]['type'] === 'web_search');
    }

    /** A topic script without web research is fiction — a provider that cannot search must not answer it. */
    public function test_apenas_com_web_drops_providers_that_cannot_search(): void
    {
        config(['services.tensorx.key' => 'tx-key', 'services.gemini.key' => 'g-key']);
        Http::fake();

        $llm = (new LlmClient)->apenasComWeb();

        $this->assertFalse($llm->disponivel());
        $this->assertNull($llm->texto('pesquisa', comFerramentas: true));
        Http::assertNothingSent();
    }

    public function test_no_keys_means_no_provider(): void
    {
        config(['contentmachine.aggregation.llm_provider' => 'none']);

        $this->assertFalse((new LlmClient)->disponivel());
    }
}
