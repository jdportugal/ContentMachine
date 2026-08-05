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
}
