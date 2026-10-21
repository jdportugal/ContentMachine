<?php

namespace Tests\Feature;

use App\Livewire\Monitorizacao;
use App\Services\Monitoring\ApifyMonitoringFetcher;
use App\Services\Monitoring\MonitoringRefresher;
use App\Services\Monitoring\MonitoringStore;
use App\Services\Settings\SettingsRepository;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Collecting social performance: the failure must be visible and must not
 * destroy what was already collected, plus the all-networks button and the
 * nightly command.
 */
class MonitoringCollectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.apify.token' => 'tok',
            'contentmachine.monitoring.apify.instagram' => 'apify~instagram-scraper',
            'contentmachine.monitoring.driver' => 'real',
        ]);
    }

    private function fetcher(): ApifyMonitoringFetcher
    {
        return app(ApifyMonitoringFetcher::class);
    }

    /** Settings are saved as a nested array merged into the project's note. */
    private function definirPerfil(string $plataforma, string $url): void
    {
        app(SettingsRepository::class)
            ->save(['perfis' => [$plataforma => ['url' => $url]]]);
    }

    // ── the data-loss bug ────────────────────────────────────────────────

    /**
     * guardar() replaces the whole cached entry, so writing an empty result after
     * a failed run erased the last good collection — the dashboard went blank and
     * looked like the profile had stopped posting.
     */
    public function test_a_failed_collection_keeps_the_previously_collected_data(): void
    {
        $store = app(MonitoringStore::class);
        $store->guardar('instagram', [['id' => 'old', 'plataforma' => 'instagram', 'views' => 10]], ['subscribers' => 5]);

        Http::fake(['api.apify.com/*' => Http::response('boom', 500)]);

        $this->assertSame([], $this->fetcher()->atualizar('instagram', 'https://instagram.com/x/'));

        // The good data is still there.
        $guardado = $store->itens('instagram');
        $this->assertSame('old', $guardado[0]['id']);
    }

    /** The reason must reach the log — it used to be swallowed by a bare catch. */
    public function test_a_failed_collection_is_logged_with_the_platform_and_actor(): void
    {
        Http::fake(['api.apify.com/*' => Http::response('nope', 401)]);

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $m) => str_contains($m, 'instagram')
                && str_contains($m, 'apify~instagram-scraper'));

        $this->fetcher()->atualizar('instagram', 'https://instagram.com/x/');
    }

    /**
     * Actors report failures as dataset rows ({"error": "not_found"}). Mapping
     * one produced a fake post (md5 id, zero metrics) and the "successful" save
     * overwrote the last good collection with garbage.
     */
    public function test_error_rows_are_not_posts_and_fail_the_collection(): void
    {
        $store = app(MonitoringStore::class);
        $store->guardar('instagram', [['id' => 'old', 'plataforma' => 'instagram', 'views' => 10]], ['subscribers' => 5]);

        Http::fake(['api.apify.com/*' => Http::response([
            ['url' => 'https://instagram.com/x/', 'username' => 'x', 'error' => 'not_found', 'errorDescription' => 'Post does not exist'],
        ])]);
        Log::shouldReceive('warning')->once()->withArgs(fn (string $m) => str_contains($m, 'not_found'));

        $fetcher = $this->fetcher();
        $this->assertSame([], $fetcher->atualizar('instagram', 'https://instagram.com/x/'));

        $this->assertStringContainsString('not_found', (string) $fetcher->ultimoErro());
        $this->assertSame('old', $store->itens('instagram')[0]['id']); // old data intact
    }

    /** Post rows carry no follower count — the profile actor fills followers + real total posts. */
    public function test_instagram_followers_come_from_the_profile_actor(): void
    {
        Http::fake([
            'api.apify.com/v2/acts/apify~instagram-scraper/*' => Http::response([
                ['id' => 'p1', 'url' => 'https://instagram.com/p/1', 'type' => 'Video', 'likesCount' => 3,
                    'videoPlayCount' => 33, 'videoViewCount' => 11, 'timestamp' => '2026-08-22T02:00:43.000Z'],
            ]),
            'api.apify.com/v2/acts/apify~instagram-profile-scraper/*' => Http::response([
                ['username' => 'x', 'followersCount' => 42, 'postsCount' => 17],
            ]),
        ]);

        $this->fetcher()->atualizar('instagram', 'https://instagram.com/x/');

        $canal = app(MonitoringStore::class)->canal('instagram');
        $this->assertSame(42, $canal['subscribers']);
        $this->assertSame(17, $canal['posts']);

        $item = app(MonitoringStore::class)->itens('instagram')[0];
        $this->assertSame(33, $item['views']);                          // plays beat videoViewCount
        $this->assertStringContainsString('T', $item['publicado_em']);  // hour kept (best-time chart)
    }

    public function test_a_successful_collection_stores_and_reports_no_error(): void
    {
        Http::fake(['api.apify.com/*' => Http::response([
            ['id' => 'p1', 'url' => 'https://instagram.com/p/1', 'type' => 'Video', 'likesCount' => 3],
        ])]);

        $itens = $this->fetcher()->atualizar('instagram', 'https://instagram.com/x/');

        $this->assertCount(1, $itens);
        $this->assertNull($this->fetcher()->ultimoErro());
        $this->assertSame('p1', app(MonitoringStore::class)->itens('instagram')[0]['id']);
    }

    // ── collect every network ────────────────────────────────────────────

    public function test_collect_all_runs_every_configured_network_and_one_failure_does_not_stop_the_rest(): void
    {
        config(['contentmachine.monitoring.apify.tiktok' => 'clockworks~tiktok-scraper']);

        Http::fake([
            'api.apify.com/v2/acts/apify~instagram-scraper/*' => Http::response('boom', 500),
            'api.apify.com/v2/acts/clockworks~tiktok-scraper/*' => Http::response([
                ['id' => 't1', 'webVideoUrl' => 'https://tiktok.com/@a/video/1', 'playCount' => 9],
            ]),
        ]);

        $resultado = app(MonitoringRefresher::class)->atualizarTodas([
            'instagram' => 'https://instagram.com/x/',
            'tiktok' => 'https://tiktok.com/@a',
            'linkedin' => '',   // no URL — skipped entirely, not reported as a failure
        ]);

        $this->assertFalse($resultado['instagram']['ok']);
        $this->assertNotNull($resultado['instagram']['error']);

        $this->assertTrue($resultado['tiktok']['ok']);
        $this->assertSame(1, $resultado['tiktok']['count']);

        $this->assertArrayNotHasKey('linkedin', $resultado);
    }

    // ── the nightly command ──────────────────────────────────────────────

    public function test_the_collect_command_runs_and_reports_per_network(): void
    {
        $this->definirPerfil('instagram', 'https://instagram.com/x/');

        Http::fake(['api.apify.com/*' => Http::response([
            ['id' => 'p1', 'url' => 'https://instagram.com/p/1', 'likesCount' => 1],
        ])]);

        $this->artisan('monitoring:collect')
            ->expectsOutputToContain('instagram: 1 posts')
            ->assertExitCode(0);
    }

    public function test_the_collect_command_exits_non_zero_when_a_network_fails(): void
    {
        $this->definirPerfil('instagram', 'https://instagram.com/x/');

        Http::fake(['api.apify.com/*' => Http::response('boom', 500)]);

        $this->artisan('monitoring:collect')->assertExitCode(1);
    }

    public function test_the_app_timezone_can_be_set_from_the_environment(): void
    {
        $_ENV['APP_TIMEZONE'] = $_SERVER['APP_TIMEZONE'] = 'Europe/Lisbon';
        putenv('APP_TIMEZONE=Europe/Lisbon');

        try {
            // Re-evaluate the config file with the variable present.
            $config = include base_path('config/app.php');
            $this->assertSame('Europe/Lisbon', $config['timezone']);
        } finally {
            unset($_ENV['APP_TIMEZONE'], $_SERVER['APP_TIMEZONE']);
            putenv('APP_TIMEZONE');
        }

        // ...and defaults to UTC when absent, so existing installs are unchanged.
        $this->assertSame('UTC', (include base_path('config/app.php'))['timezone']);
    }

    private function eventoAgendado(): ?Event
    {
        return collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains((string) $e->command, 'monitoring:collect'));
    }
}
