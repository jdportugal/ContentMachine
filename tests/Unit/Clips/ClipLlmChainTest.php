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
}
