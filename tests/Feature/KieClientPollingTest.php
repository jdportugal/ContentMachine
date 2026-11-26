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

    /** Kling 3.0 is priced per second (pro, 1080p): a clip is booked at its length × that price, not a flat fee. */
    public function test_a_kling_3_clip_is_booked_at_seconds_times_the_per_second_price(): void
    {
        config(['services.kie.key' => 'test-key', 'services.kie.base_url' => 'https://api.kie.ai']);
        $kie = app(KieClient::class);
        $this->assertEqualsWithDelta(1.51, $kie->precoKling(10, true), 0.0001);
        $this->assertEqualsWithDelta(2.265, $kie->precoKling(15, true), 0.0001);
        $this->assertEqualsWithDelta(1.01, $kie->precoKling(10, false), 0.0001);

        Http::fake(['*' => Http::response(['data' => ['state' => 'success', 'model' => 'kling-3.0/video',
            'resultJson' => json_encode(['resultUrls' => ['https://v.example/1.mp4']])]])]);
        $custos = [];
        $this->mock(\App\Services\Costs\CostLedger::class, function ($m) use (&$custos) {
            $m->shouldReceive('registar')->andReturnUsing(function ($servico, $custo) use (&$custos) { $custos[] = $custo; });
        });

        $this->assertSame('https://v.example/1.mp4', app(KieClient::class)->pollVideo('t1', 1.51));
        $this->assertSame([1.51], $custos);
    }
}
