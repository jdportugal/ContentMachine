<?php

namespace Tests\Feature;

use App\Services\Monitoring\MonitoringAnalytics;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MonitoringAnalyticsTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_serie_buckets_views_and_posts_by_day(): void
    {
        Carbon::setTestNow('2026-07-31 12:00:00');

        $itens = [
            ['tipo' => 'short', 'views' => 100, 'publicado_em' => '2026-07-31'],
            ['tipo' => 'short', 'views' => 50, 'publicado_em' => '2026-07-31'],
            ['tipo' => 'vídeo', 'views' => 200, 'publicado_em' => '2026-07-30'],
            ['tipo' => 'vídeo', 'views' => 999, 'publicado_em' => '2020-01-01'], // outside the 14-day window
        ];

        $serie = (new MonitoringAnalytics)->serie($itens, 'dia', 14);

        $this->assertCount(14, $serie);
        $hoje = end($serie);
        $this->assertSame(150, $hoje['views']);
        $this->assertSame(2, $hoje['posts']);

        // The far-past item is excluded entirely.
        $this->assertSame(150 + 200, array_sum(array_column($serie, 'views')));
    }

    public function test_medias_por_tipo_averages_and_subscribers_per_post(): void
    {
        $itens = [
            ['tipo' => 'short', 'views' => 100, 'likes' => 10],
            ['tipo' => 'short', 'views' => 300, 'likes' => 30],
            ['tipo' => 'vídeo', 'views' => 1000, 'likes' => 50],
        ];

        $medias = (new MonitoringAnalytics)->mediasPorTipo($itens, 6000);

        // Sorted by post count, descending → 'short' (2) before 'vídeo' (1).
        $this->assertSame('short', $medias[0]['tipo']);
        $this->assertSame(2, $medias[0]['posts']);
        $this->assertSame(200, $medias[0]['views_med']);
        $this->assertSame(20, $medias[0]['likes_med']);
        $this->assertSame(10.0, $medias[0]['engajamento']); // 10/100 and 30/300 → 10%
        $this->assertSame(3000, $medias[0]['subs_por']);     // 6000 / 2 posts

        $this->assertSame('vídeo', $medias[1]['tipo']);
        $this->assertSame(6000, $medias[1]['subs_por']);     // 6000 / 1 post
    }

    public function test_subs_per_post_is_null_without_a_subscriber_count(): void
    {
        $medias = (new MonitoringAnalytics)->mediasPorTipo([['tipo' => 'short', 'views' => 10]], 0);
        $this->assertNull($medias[0]['subs_por']);
    }

    public function test_serie_buckets_every_metric(): void
    {
        Carbon::setTestNow('2026-07-31 12:00:00');
        $serie = (new MonitoringAnalytics)->serie([
            ['tipo' => 'short', 'views' => 100, 'likes' => 9, 'comentarios' => 3, 'partilhas' => 2, 'guardados' => 5, 'publicado_em' => '2026-07-31'],
        ], 'dia', 14);

        $hoje = end($serie);
        $this->assertSame(9, $hoje['likes']);
        $this->assertSame(3, $hoje['comentarios']);
        $this->assertSame(2, $hoje['partilhas']);
        $this->assertSame(5, $hoje['guardados']);
    }

    public function test_curve_path_builds_a_smooth_line_and_closed_area(): void
    {
        $paths = MonitoringAnalytics::curvePath([0, 5, 2, 8, 4]);

        $this->assertStringStartsWith('M ', $paths['line']);
        $this->assertStringContainsString(' C ', $paths['line']); // cubic bézier segments = smooth
        $this->assertStringEndsWith('Z', $paths['area']);         // area closes to the baseline

        $this->assertSame(['line' => '', 'area' => ''], MonitoringAnalytics::curvePath([]));
    }
}
