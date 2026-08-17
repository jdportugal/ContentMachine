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

    /** ...and to the UI, so "no data" is distinguishable from "it broke". */
    public function test_the_refresh_button_reports_why_it_failed(): void
    {
        Http::fake(['api.apify.com/*' => Http::response('nope', 401)]);
        $this->definirPerfil('instagram', 'https://instagram.com/x/');

        Livewire::test(Monitorizacao::class)
            ->set('rede', 'instagram')
            ->call('atualizar')
            ->assertDispatched('toast', fn ($event, $params) => $params['type'] === 'erro'
                && str_contains($params['message'], 'Apify failed'));
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

    public function test_collect_all_button_reports_a_per_network_summary(): void
    {
        $this->definirPerfil('instagram', 'https://instagram.com/x/');

        Http::fake(['api.apify.com/*' => Http::response([
            ['id' => 'p1', 'url' => 'https://instagram.com/p/1', 'likesCount' => 1],
        ])]);

        Livewire::test(Monitorizacao::class)
            ->call('atualizarTodas')
            ->assertDispatched('toast', fn ($event, $params) => str_contains($params['message'], 'instagram 1'));
    }

    public function test_collect_all_without_any_profile_url_says_so(): void
    {
        Livewire::test(Monitorizacao::class)
            ->call('atualizarTodas')
            ->assertDispatched('toast', fn ($event, $params) => $params['type'] === 'erro'
                && str_contains($params['message'], 'No profile URLs set'));
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

    /** The nightly run must actually be registered, not just the command exist. */
    public function test_collection_is_scheduled_nightly_at_midnight(): void
    {
        $evento = $this->eventoAgendado();

        $this->assertNotNull($evento, 'monitoring:collect is not scheduled');
        $this->assertSame('0 0 * * *', $evento->expression);
    }

    /**
     * "Midnight" must mean the operator's midnight. Two links have to hold: the
     * scheduled run follows app.timezone, and app.timezone is settable from the
     * environment. It used to be hardcoded to UTC, so a Lisbon user's nightly
     * collection fired at 01:00 local for half the year.
     */
    public function test_the_nightly_run_follows_the_app_timezone(): void
    {
        $this->assertSame(config('app.timezone'), $this->eventoAgendado()->timezone);
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
