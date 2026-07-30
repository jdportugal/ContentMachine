<?php

namespace Tests\Feature;

use App\Services\Publicacoes\Rendering\KieClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KieClientPollingTest extends TestCase
{
    /**
     * A transient network blip while polling must NOT abort a generation that
     * kie is still completing — the poller keeps trying until success/timeout.
     */
    public function test_poll_survives_a_transient_connection_error(): void
    {
        config(['services.kie.key' => 'test-key', 'services.kie.base_url' => 'https://api.kie.ai']);

        $recordCalls = 0;
        Http::fake(function ($request) use (&$recordCalls) {
            if (str_contains($request->url(), 'createTask')) {
                return Http::response(['data' => ['taskId' => 'abc123']]);
            }
            // recordInfo — a DNS/connection blip on the first poll, success on the next.
            $recordCalls++;
            if ($recordCalls === 1) {
                throw new ConnectionException('cURL error 6: Could not resolve host: api.kie.ai');
            }

            return Http::response(['data' => [
                'state' => 'success',
                'resultJson' => json_encode(['resultUrls' => ['https://img.example/1.png']]),
            ]]);
        });

        $url = app(KieClient::class)->generate('a prompt', '1:1');

        $this->assertSame('https://img.example/1.png', $url);
        $this->assertSame(2, $recordCalls); // it retried the poll instead of aborting on the blip
    }

    /** A real 'fail' state still surfaces as an error (we don't swallow genuine failures). */
    public function test_poll_still_reports_a_genuine_failure(): void
    {
        config(['services.kie.key' => 'test-key', 'services.kie.base_url' => 'https://api.kie.ai']);

        Http::fake(function ($request) {
            if (str_contains($request->url(), 'createTask')) {
                return Http::response(['data' => ['taskId' => 'abc123']]);
            }

            return Http::response(['data' => ['state' => 'fail']]);
        });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/generation failed/');
        app(KieClient::class)->generate('a prompt', '1:1');
    }
}
