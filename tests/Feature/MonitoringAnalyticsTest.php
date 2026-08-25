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

    public function test_serie_buckets_by_week_keyed_on_monday(): void
    {
        Carbon::setTestNow('2026-08-23 12:00:00'); // a Sunday — week starts Mon 17/08

        $serie = (new MonitoringAnalytics)->serie([
            ['tipo' => 'x', 'views' => 100, 'publicado_em' => '2026-08-17T09:00:00Z'], // Mon, this week
            ['tipo' => 'x', 'views' => 50, 'publicado_em' => '2026-08-23'],            // Sun, same week
            ['tipo' => 'x', 'views' => 30, 'publicado_em' => '2026-08-14'],            // previous week
        ], 'semana');

        $this->assertCount(12, $serie);
        $atual = end($serie);
        $this->assertSame('17/08', $atual['label']);
        $this->assertSame(150, $atual['views']);
        $this->assertSame(2, $atual['posts']);
        $this->assertSame(30, $serie[10]['views']); // the week before
    }

    public function test_week_over_week_compares_rolling_7_day_windows(): void
    {
        Carbon::setTestNow('2026-08-23 12:00:00');

        $wow = (new MonitoringAnalytics)->semanaSobreSemana([
            ['views' => 200, 'likes' => 10, 'publicado_em' => '2026-08-22'], // last 7 days
            ['views' => 100, 'likes' => 0, 'publicado_em' => '2026-08-19'],  // last 7 days
            ['views' => 150, 'likes' => 5, 'publicado_em' => '2026-08-12'],  // the 7 before
            ['views' => 999, 'likes' => 9, 'publicado_em' => '2026-08-01'],  // outside both windows
        ]);

        $this->assertSame(300, $wow['views']['atual']);
        $this->assertSame(150, $wow['views']['anterior']);
        $this->assertSame(100.0, $wow['views']['delta']);
        $this->assertSame(100.0, $wow['posts']['delta']);   // 2 posts vs 1
        $this->assertNull($wow['comentarios']['delta']);    // 0 before → no honest %
    }

    public function test_engagement_rate_is_total_interactions_over_total_views(): void
    {
        $a = new MonitoringAnalytics;

        $this->assertSame(10.0, $a->engajamento([
            ['views' => 100, 'likes' => 5, 'comentarios' => 3, 'partilhas' => 1, 'guardados' => 1],
            ['views' => 100, 'likes' => 10],
        ]));
        $this->assertNull($a->engajamento([['views' => 0, 'likes' => 5]]));
        $this->assertNull($a->engajamento([]));

        $this->assertSame(5.0, MonitoringAnalytics::erItem(['views' => 200, 'likes' => 10]));
        $this->assertNull(MonitoringAnalytics::erItem(['likes' => 10]));
    }

    public function test_best_hours_skips_date_only_items_and_ranks_slots(): void
    {
        $a = new MonitoringAnalytics;

        // Date-only timestamps (no hour) must not fabricate a midnight column.
        $this->assertNull($a->melhoresHoras([
            ['publicado_em' => '2026-08-21', 'likes' => 5],
            ['publicado_em' => '2026-08-20', 'likes' => 5],
            ['publicado_em' => '2026-08-19', 'likes' => 5],
        ]));

        // 2026-08-21 is a Friday.
        $hm = $a->melhoresHoras([
            ['publicado_em' => '2026-08-21T18:05:00Z', 'likes' => 6],
            ['publicado_em' => '2026-08-14T18:40:00Z', 'likes' => 4],
            ['publicado_em' => '2026-08-16T10:00:00Z', 'likes' => 2], // Sunday
        ]);

        $this->assertSame('interactions', $hm['metrica']);
        $this->assertSame('Fri 18h', $hm['melhores'][0]['label']);
        $this->assertSame(10, $hm['melhores'][0]['valor']);
        $this->assertSame(4, $hm['grade'][4][18]['nivel']);  // Friday 18h is the hottest cell
        $this->assertSame(0, $hm['grade'][0][0]['nivel']);   // empty cell stays level 0
    }

    public function test_follower_prediction_from_history_trend_and_posting_pace(): void
    {
        Carbon::setTestNow('2026-08-23 12:00:00');
        $a = new MonitoringAnalytics;

        $historico = [
            ['data' => '2026-08-13', 'redes' => ['youtube' => 100]],
            ['data' => '2026-08-20', 'redes' => ['youtube' => 170]],
        ];
        $itens = [
            ['tipo' => 'short', 'publicado_em' => '2026-08-22'],
            ['tipo' => 'short', 'publicado_em' => '2026-08-20'],
            ['tipo' => 'vídeo', 'publicado_em' => '2026-08-15'],
            ['tipo' => 'vídeo', 'publicado_em' => '2026-01-01'], // outside the 14-day pace window
        ];

        $p = $a->previsaoSeguidores($historico, 'youtube', 200, $itens);

        // +100 followers over 10 days → +10/day → 800 to go → 80 days.
        $this->assertSame(1000, $p['alvo']);
        $this->assertSame(800, $p['faltam']);
        $this->assertSame(10.0, $p['crescimentoDia']);
        $this->assertSame(80, $p['dias']);
        // 3 posts in 14 days → ceil(80 × 3/14) = 18 posts, mix scaled 2:1.
        $this->assertSame(18, $p['posts']);
        $this->assertSame(['short' => 12, 'vídeo' => 6], $p['mix']);

        // Flat trend → no honest ETA; past 1000 → next milestone.
        $flat = $a->previsaoSeguidores([['data' => '2026-08-20', 'redes' => ['youtube' => 200]]], 'youtube', 200, $itens);
        $this->assertNull($flat['dias']);
        $this->assertSame(2000, $a->previsaoSeguidores($historico, 'youtube', 1500, $itens)['alvo']);

        // No earlier per-network point (or none at all) → null.
        $this->assertNull($a->previsaoSeguidores([['data' => '2026-08-23', 'redes' => ['youtube' => 200]]], 'youtube', 200, $itens));
        $this->assertNull($a->previsaoSeguidores([['data' => '2026-08-20', 'redes' => []]], 'youtube', 200, $itens));
        $this->assertNull($a->previsaoSeguidores($historico, 'youtube', 0, $itens));
    }

    public function test_platform_breakdown_totals_and_sorts_by_views(): void
    {
        $linhas = (new MonitoringAnalytics)->porPlataforma([
            'youtube' => ['itens' => [['views' => 100, 'likes' => 10]], 'canal' => ['subscribers' => 500]],
            'instagram' => ['itens' => [['views' => 900, 'likes' => 45], ['views' => 100, 'likes' => 5]], 'canal' => []],
            'tiktok' => ['itens' => [], 'canal' => []], // never collected → no row
        ]);

        $this->assertCount(2, $linhas);
        $this->assertSame('instagram', $linhas[0]['plataforma']);
        $this->assertSame(1000, $linhas[0]['views']);
        $this->assertSame(2, $linhas[0]['posts']);
        $this->assertSame(5.0, $linhas[0]['er']);
        $this->assertSame(500, $linhas[1]['seguidores']);
    }
}
