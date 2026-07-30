<?php

namespace Tests\Feature;

use App\Services\Monitoring\ApifyClient;
use App\Services\Monitoring\ApifyMonitoringFetcher;
use App\Services\Monitoring\MonitoringRefresher;
use App\Services\Monitoring\MonitoringStore;
use App\Services\Scoring\EngagementScorer;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApifyMonitoringTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'cache.default' => 'array',
            'services.apify.token' => 'test-token',
            'contentmachine.monitoring.apify.instagram' => 'apify~instagram-scraper',
            'contentmachine.monitoring.apify.tiktok' => 'clockworks~tiktok-scraper',
            'contentmachine.monitoring.apify.linkedin' => '',
        ]);
    }

    private function fetcher(): ApifyMonitoringFetcher
    {
        return new ApifyMonitoringFetcher(new ApifyClient, new EngagementScorer, app(MonitoringStore::class));
    }

    public function test_mapeia_posts_do_instagram(): void
    {
        Http::fake(['api.apify.com/*' => Http::response([
            ['id' => 'p1', 'type' => 'Video', 'url' => 'https://instagram.com/p/p1', 'caption' => "Legenda A\nsegunda linha",
                'likesCount' => 120, 'commentsCount' => 8, 'videoViewCount' => 3400, 'timestamp' => '2026-07-01T10:00:00.000Z', 'ownerFollowersCount' => 5000],
            ['id' => 'p2', 'type' => 'Sidecar', 'url' => 'https://instagram.com/p/p2', 'caption' => 'Carrossel',
                'likesCount' => 90, 'commentsCount' => 3, 'timestamp' => '2026-06-20T10:00:00.000Z'],
        ])]);

        $itens = $this->fetcher()->atualizar('instagram', 'https://instagram.com/aiwithjd/');

        $this->assertCount(2, $itens);
        $porId = collect($itens)->keyBy('id');
        $this->assertSame('reel', $porId['p1']['tipo']);         // Video → reel
        $this->assertSame('carrossel', $porId['p2']['tipo']);    // Sidecar → carrossel
        $this->assertSame(3400, $porId['p1']['views']);
        $this->assertSame(120, $porId['p1']['likes']);
        $this->assertSame('Legenda A', $porId['p1']['titulo']);  // 1.ª linha da legenda
        $this->assertSame('2026-07-01', $porId['p1']['publicado_em']);

        $canal = app(MonitoringStore::class)->canal('instagram');
        $this->assertSame(5000, $canal['subscribers']);
        $this->assertSame(2, $canal['posts']);
    }

    public function test_mapeia_videos_do_tiktok(): void
    {
        Http::fake(['api.apify.com/*' => Http::response([
            ['id' => 't1', 'webVideoUrl' => 'https://tiktok.com/@u/video/t1', 'text' => 'Meu vídeo',
                'playCount' => 10000, 'diggCount' => 800, 'commentCount' => 40, 'shareCount' => 120, 'collectCount' => 60,
                'createTimeISO' => '2026-07-05T00:00:00Z', 'authorMeta' => ['fans' => 12000]],
        ])]);

        $itens = $this->fetcher()->atualizar('tiktok', 'https://www.tiktok.com/@aiautomationwithjd');

        $this->assertCount(1, $itens);
        $i = $itens[0];
        $this->assertSame('vídeo', $i['tipo']);
        $this->assertSame(10000, $i['views']);
        $this->assertSame(800, $i['likes']);
        $this->assertSame(120, $i['partilhas']);
        $this->assertSame(60, $i['guardados']);
        $this->assertSame('2026-07-05', $i['publicado_em']);
        $this->assertArrayHasKey('score', $i);

        $this->assertSame(12000, app(MonitoringStore::class)->canal('tiktok')['subscribers']);
    }

    public function test_sem_token_nao_disponivel_e_recolha_vazia_sem_falhar(): void
    {
        config(['services.apify.token' => null]);

        $fetcher = $this->fetcher();
        $this->assertFalse($fetcher->disponivel('instagram'));
        // Cliente lança DriverNotConfigured; o fetcher trata e devolve vazio.
        $this->assertSame([], $fetcher->atualizar('instagram', 'https://instagram.com/x/'));
    }

    public function test_linkedin_sem_actor_configurado_devolve_vazio(): void
    {
        $this->assertFalse($this->fetcher()->disponivel('linkedin'));
        $this->assertSame([], $this->fetcher()->atualizar('linkedin', 'https://linkedin.com/in/x'));
    }

    public function test_refresher_encaminha_por_plataforma(): void
    {
        $refresher = app(MonitoringRefresher::class);

        $this->assertTrue($refresher->disponivel('youtube'));      // sempre (yt-dlp)
        $this->assertTrue($refresher->disponivel('instagram'));    // actor + token presentes
        $this->assertFalse($refresher->disponivel('linkedin'));    // sem actor
        $this->assertSame('yt-dlp', $refresher->fonte('youtube'));
        $this->assertSame('Apify', $refresher->fonte('tiktok'));
    }
}
