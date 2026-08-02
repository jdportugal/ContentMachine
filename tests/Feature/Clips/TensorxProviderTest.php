<?php

namespace Tests\Feature\Clips;

use App\Services\Clips\Api\RunsClaudeCli;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Exposes the trait's protected entry point for testing. */
class TensorxHarness
{
    use RunsClaudeCli;

    public function run(string $user, ?string $system = null, array $opts = []): array
    {
        return $this->runClaude($user, $system, $opts);
    }
}

class TensorxProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Keep retries to 1 so a failing call doesn't sleep on backoff.
        config(['contentmachine.clips.claude_attempts' => 1]);
        config(['services.tensorx.base_url' => 'https://api.tensorx.ai/v1']);
        config(['services.tensorx.model' => 'deepseek/deepseek-r1-0528']);
    }

    private function fakeTensorx(string $reply): void
    {
        Http::fake([
            'api.tensorx.ai/*' => Http::response(['choices' => [['message' => ['content' => $reply]]]], 200),
        ]);
    }

    public function test_tensorx_is_used_as_the_primary_when_selected(): void
    {
        config(['services.tensorx.key' => 'tx-key']);
        config(['services.anthropic.key' => '']);
        config(['contentmachine.clips.llm_primary' => 'tensorx']);
        $this->fakeTensorx('FROM_TENSORX');

        $out = (new TensorxHarness)->run('hi', 'sys');

        $this->assertSame('FROM_TENSORX', $out['result']);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'api.tensorx.ai')
            && $r['model'] === 'deepseek/deepseek-r1-0528'
            && $r->hasHeader('Authorization', 'Bearer tx-key'));
    }

    public function test_falls_back_to_tensorx_when_claude_api_fails(): void
    {
        config(['services.anthropic.key' => 'ant-key']); // primary = Claude API
        config(['services.tensorx.key' => 'tx-key']);
        config(['contentmachine.clips.llm_primary' => 'claude']);

        Http::fake([
            'api.anthropic.com/*' => Http::response('overloaded', 529),
            'api.tensorx.ai/*' => Http::response(['choices' => [['message' => ['content' => 'FALLBACK']]]], 200),
        ]);

        $out = (new TensorxHarness)->run('hi');

        $this->assertSame('FALLBACK', $out['result']);
    }

    public function test_without_tensorx_key_a_claude_failure_still_throws(): void
    {
        config(['services.anthropic.key' => 'ant-key']);
        config(['services.tensorx.key' => '']);
        config(['contentmachine.clips.llm_primary' => 'claude']);
        Http::fake(['api.anthropic.com/*' => Http::response('boom', 500)]);

        $this->expectException(\RuntimeException::class);
        (new TensorxHarness)->run('hi');
    }
}
