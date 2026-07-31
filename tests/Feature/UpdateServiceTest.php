<?php

namespace Tests\Feature;

use App\Services\UpdateService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UpdateServiceTest extends TestCase
{
    private function ghcr(string $latest, string $mine): void
    {
        Http::fake([
            'ghcr.io/token*' => Http::response(['token' => 't']),
            'ghcr.io/v2/jdportugal/contentmachine/manifests/latest' => Http::response('', 200, ['Docker-Content-Digest' => $latest]),
            'ghcr.io/v2/jdportugal/contentmachine/manifests/abc1234' => Http::response('', 200, ['Docker-Content-Digest' => $mine]),
        ]);
        config([
            'contentmachine.update.version' => 'abc1234',
            'contentmachine.update.image' => 'ghcr.io/jdportugal/contentmachine',
        ]);
    }

    public function test_update_available_when_latest_digest_differs(): void
    {
        $this->ghcr(latest: 'sha256:NEW', mine: 'sha256:OLD');
        $this->assertTrue(app(UpdateService::class)->updateAvailable());
    }

    public function test_up_to_date_when_digests_match(): void
    {
        $this->ghcr(latest: 'sha256:SAME', mine: 'sha256:SAME');
        $this->assertFalse(app(UpdateService::class)->updateAvailable());
    }

    public function test_undetermined_on_a_dev_build(): void
    {
        config(['contentmachine.update.version' => 'dev']);
        Http::fake(); // must not even call the registry
        $this->assertNull(app(UpdateService::class)->updateAvailable());
        Http::assertNothingSent();
    }

    public function test_undetermined_when_the_registry_is_unreachable(): void
    {
        config([
            'contentmachine.update.version' => 'abc1234',
            'contentmachine.update.image' => 'ghcr.io/jdportugal/contentmachine',
        ]);
        Http::fake(['ghcr.io/*' => Http::response('nope', 500)]);
        $this->assertNull(app(UpdateService::class)->updateAvailable());
    }

    public function test_updatable_reflects_watchtower_config(): void
    {
        config(['contentmachine.update.watchtower_url' => '']);
        $this->assertFalse(app(UpdateService::class)->updatable());

        config(['contentmachine.update.watchtower_url' => 'http://watchtower:8080']);
        $this->assertTrue(app(UpdateService::class)->updatable());
    }

    public function test_trigger_update_posts_to_watchtower_with_the_token(): void
    {
        config([
            'contentmachine.update.watchtower_url' => 'http://watchtower:8080',
            'contentmachine.update.watchtower_token' => 'wt-secret',
        ]);
        Http::fake(['watchtower:8080/*' => Http::response('ok')]);

        $this->assertSame('triggered', app(UpdateService::class)->triggerUpdate());

        Http::assertSent(fn ($req) => $req->method() === 'POST'
            && $req->url() === 'http://watchtower:8080/v1/update'
            && $req->hasHeader('Authorization', 'Bearer wt-secret'));
    }

    public function test_trigger_update_is_a_no_op_without_watchtower(): void
    {
        config(['contentmachine.update.watchtower_url' => '']);
        Http::fake();
        $this->assertSame('not-wired', app(UpdateService::class)->triggerUpdate());
        Http::assertNothingSent();
    }

    public function test_trigger_update_reports_a_token_mismatch(): void
    {
        config([
            'contentmachine.update.watchtower_url' => 'http://watchtower:8080',
            'contentmachine.update.watchtower_token' => 'wrong',
        ]);
        Http::fake(['watchtower:8080/*' => Http::response('Unauthorized', 401)]);
        $this->assertSame('unauthorized', app(UpdateService::class)->triggerUpdate());
    }

    public function test_trigger_update_reports_a_disabled_api(): void
    {
        config([
            'contentmachine.update.watchtower_url' => 'http://watchtower:8080',
            'contentmachine.update.watchtower_token' => 't',
        ]);
        Http::fake(['watchtower:8080/*' => Http::response('Not Found', 404)]);
        $this->assertSame('unsupported', app(UpdateService::class)->triggerUpdate());
    }

    public function test_trigger_update_reports_an_unreachable_sidecar(): void
    {
        config([
            'contentmachine.update.watchtower_url' => 'http://watchtower:8080',
            'contentmachine.update.watchtower_token' => 't',
        ]);
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('cURL error 7: Connection refused'));
        $this->assertSame('unreachable', app(UpdateService::class)->triggerUpdate());
    }

    public function test_trigger_update_treats_a_dropped_reply_as_success(): void
    {
        // Watchtower recreates THIS container mid-request → the reply is lost. That is
        // the success path, not a failure.
        config([
            'contentmachine.update.watchtower_url' => 'http://watchtower:8080',
            'contentmachine.update.watchtower_token' => 't',
        ]);
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('Empty reply from server'));
        $this->assertSame('triggered', app(UpdateService::class)->triggerUpdate());
    }
}
