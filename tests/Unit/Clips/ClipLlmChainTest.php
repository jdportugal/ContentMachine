<?php

namespace Tests\Unit\Clips;

use App\Services\Clips\Api\RunsClaudeCli;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The clip pipeline must use whichever provider is configured, in the house
 * order: Claude → GPT → DeepSeek (Tensorix), the chosen one first.
 */
class ClipLlmChainTest extends TestCase
{
    private function runner(): object
    {
        return new class
        {
            use RunsClaudeCli;

            public function run(): array
            {
                return $this->runClaude('planeia', 'sistema');
            }
        };
    }

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'contentmachine.clips.claude_binary' => '/nonexistent/claude', // no CLI, as on a server
            'contentmachine.clips.claude_attempts' => 1,
            'contentmachine.clips.llm_primary' => '',
            'services.anthropic.key' => null,
            'services.openai.key' => null,
            'services.tensorx.key' => null,
        ]);
    }

    public function test_gpt_serves_the_clips_when_it_is_the_only_key(): void
    {
        config(['services.openai.key' => 'sk-oai']);
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => '{"scenes":[]}']]]])]);

        $this->assertSame('{"scenes":[]}', $this->runner()->run()['result']);
    }

    public function test_claude_down_falls_through_to_gpt_before_deepseek(): void
    {
        config(['services.anthropic.key' => 'sk-ant', 'services.openai.key' => 'sk-oai', 'services.tensorx.key' => 'tx']);
        Http::fake([
            'api.anthropic.com/*' => Http::response([], 500),
            'api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => 'GPT']]]]),
            '*' => Http::response(['choices' => [['message' => ['content' => 'DeepSeek']]]]),
        ]);

        $this->assertSame('GPT', $this->runner()->run()['result']);
    }

    public function test_the_provider_chosen_in_settings_goes_first(): void
    {
        config([
            'contentmachine.clips.llm_primary' => 'tensorx',
            'services.openai.key' => 'sk-oai',
            'services.tensorx.key' => 'tx',
        ]);
        Http::fake([
            'api.tensorx.ai/*' => Http::response(['choices' => [['message' => ['content' => 'DeepSeek']]]]),
            'api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => 'GPT']]]]),
        ]);

        $this->assertSame('DeepSeek', $this->runner()->run()['result']);
    }

    public function test_without_any_key_it_says_so_instead_of_hanging_on_a_missing_cli(): void
    {
        $this->expectExceptionMessage('No LLM is configured for clips');

        $this->runner()->run();
    }

    /**
     * A failing CLI reports WHY inside its JSON envelope, after a long `usage`
     * block. Reporting a blind prefix of the raw output truncates mid-word and
     * hides the reason — which is exactly what made a real failure undiagnosable.
     */
    public function test_a_failing_cli_reports_the_reason_not_a_truncated_prefix(): void
    {
        $envelope = json_encode([
            'is_error' => true,
            'duration_api_ms' => 0,
            'num_turns' => 1,
            'stop_reason' => 'stop_sequence',
            'session_id' => 'c9d458ae-0000-0000-0000-000000000000',
            'total_cost_usd' => 0,
            // Long enough to push everything useful past a 200-char prefix.
            'usage' => ['output_tokens_details' => ['thinking_tokens' => 0], 'padding' => str_repeat('x', 300)],
            'subtype' => 'error_during_execution',
            'result' => 'Credit balance is too low to run this request.',
        ]);

        $bin = sys_get_temp_dir().'/fake-claude-'.uniqid().'.sh';
        file_put_contents($bin, "#!/bin/sh\ncat <<'JSON'\n{$envelope}\nJSON\nexit 1\n");
        chmod($bin, 0755);

        config([
            'contentmachine.clips.claude_binary' => $bin,
            'contentmachine.clips.claude_attempts' => 1,
        ]);

        try {
            $this->runner()->run();
            $this->fail('The failing CLI should have thrown.');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('Credit balance is too low', $e->getMessage());
            $this->assertStringContainsString('error_during_execution', $e->getMessage());
            $this->assertStringContainsString('stop_reason=stop_sequence', $e->getMessage());
        } finally {
            @unlink($bin);
        }
    }

    // ── which Anthropic credential goes down which road ──────────────────

    /**
     * The two Anthropic credentials are not interchangeable, and getting it wrong
     * is silent: an OAuth token sent as `x-api-key` is rejected 401, the chain
     * counts that as a normal failure and quietly serves the whole pipeline from
     * GPT instead — which is how a studio that reports itself as running on Claude
     * ended up with a library of GPT-written components.
     */
    public function test_an_oauth_token_is_sent_as_a_bearer_token_not_an_api_key(): void
    {
        $headers = $this->headersFor('sk-ant-oat01-abc');

        $this->assertSame('Bearer sk-ant-oat01-abc', $headers['Authorization']);
        $this->assertSame('oauth-2025-04-20', $headers['anthropic-beta']);
        $this->assertArrayNotHasKey('x-api-key', $headers);
    }

    public function test_a_real_api_key_still_goes_on_the_api_key_header(): void
    {
        $headers = $this->headersFor('sk-ant-api03-abc');

        $this->assertSame('sk-ant-api03-abc', $headers['x-api-key']);
        $this->assertArrayNotHasKey('Authorization', $headers);
    }

    /**
     * An OAuth token authenticates against the Messages API but is rate-limited to
     * a flat 429 there — it is the CLI's own credential, so with a CLI present the
     * call belongs on the CLI. Without one (a container) it still tries the API, so
     * the failure surfaces instead of being hidden.
     */
    public function test_an_oauth_token_routes_to_the_cli_only_when_one_is_installed(): void
    {
        $bin = sys_get_temp_dir().'/fake-claude-'.uniqid().'.sh';
        file_put_contents($bin, "#!/bin/sh\nexit 0\n");
        chmod($bin, 0755);

        try {
            config(['services.anthropic.key' => 'sk-ant-oat01-abc', 'contentmachine.clips.claude_binary' => $bin]);
            $this->assertFalse($this->usesApi(), 'an OAuth token with a CLI present must use the CLI');

            config(['contentmachine.clips.claude_binary' => '/nonexistent/claude']);
            $this->assertTrue($this->usesApi(), 'with no CLI it must still try the API so the error is reported');

            config(['services.anthropic.key' => 'sk-ant-api03-abc', 'contentmachine.clips.claude_binary' => $bin]);
            $this->assertTrue($this->usesApi(), 'a real API key always uses the API');
        } finally {
            @unlink($bin);
        }
    }

    /** @return array<string,string> */
    private function headersFor(string $key): array
    {
        $r = $this->runner();
        $m = new \ReflectionMethod($r, 'cabecalhosAnthropic');
        $m->setAccessible(true);

        return $m->invoke($r, $key);
    }

    private function usesApi(): bool
    {
        $r = $this->runner();
        $m = new \ReflectionMethod($r, 'usaApiClaude');
        $m->setAccessible(true);

        return (bool) $m->invoke($r);
    }

    /**
     * The fallback providers used to send no output ceiling at all, so they ran on
     * THEIR default completion cap — a couple of thousand tokens, nowhere near a
     * whole TSX component. Raising CLIPS_MAX_TOKENS, which is what the truncation
     * error tells you to do, changed nothing for them.
     */
    public function test_the_fallback_providers_are_sent_the_configured_output_ceiling(): void
    {
        config(['services.openai.key' => 'sk-oai', 'contentmachine.clips.max_tokens' => 24000]);
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => 'ok']]]])]);

        $this->runner()->run();

        Http::assertSent(fn ($r) => ($r->data()['max_tokens'] ?? null) === 24000);
    }

    /** A cut-off answer must fail as a truncation, naming the knob — not as bad JSON downstream. */
    public function test_a_truncated_gpt_answer_names_the_ceiling(): void
    {
        config(['services.openai.key' => 'sk-oai', 'contentmachine.clips.max_tokens' => 24000]);
        Http::fake(['api.openai.com/*' => Http::response([
            'choices' => [['finish_reason' => 'length', 'message' => ['content' => '{"tsx":"half a comp']]],
        ])]);

        $this->expectExceptionMessageMatches('/truncated.*max_tokens=24000.*CLIPS_MAX_TOKENS/s');
        $this->runner()->run();
    }

    /**
     * Attribution: the chain falls through silently, so an unparseable answer has
     * to say who actually wrote it.
     */
    public function test_the_envelope_records_which_provider_answered(): void
    {
        config(['services.anthropic.key' => 'sk-ant', 'services.openai.key' => 'sk-oai']);
        Http::fake([
            'api.anthropic.com/*' => Http::response([], 500),
            'api.openai.com/*' => Http::response(['choices' => [['finish_reason' => 'stop', 'message' => ['content' => 'GPT']]]]),
        ]);

        $envelope = $this->runner()->run();

        $this->assertSame('openai', $envelope['_provider']);
        $this->assertSame('stop', $envelope['stop_reason']);
    }
}
