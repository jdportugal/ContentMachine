<?php

namespace Tests\Feature;

use App\Livewire\Monitorizacao;
use App\Services\Aggregation\YtDlpRunnerContract;
use App\Services\Monitoring\MonitoringStore;
use App\Services\Monitoring\YtDlpMonitoringDriver;
use App\Services\Monitoring\YtDlpMonitoringFetcher;
use App\Services\Scoring\EngagementScorer;
use App\Services\Settings\SettingsRepository;
use App\Services\Vault\VaultContract;
use App\Services\Vault\VaultRepository;
use Livewire\Livewire;
use Tests\Support\FakeYtDlpRunner;
use Tests\TestCase;

class MonitorizacaoYtDlpTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'contentmachine.monitoring.driver' => 'ytdlp']);
    }

    private function runner(): FakeYtDlpRunner
    {
        return new FakeYtDlpRunner(
            entradas: [
                ['id' => 'v1', 'webpage_url' => 'https://youtube.com/watch?v=v1', 'title' => 'A'],
                ['id' => 'v2', 'webpage_url' => 'https://youtube.com/shorts/v2', 'title' => 'B'],
            ],
            metadados: [
                'https://youtube.com/watch?v=v1' => [
                    'id' => 'v1', 'title' => 'Vídeo Longo', 'webpage_url' => 'https://youtube.com/watch?v=v1',
                    'duration' => 600, 'view_count' => 10000, 'like_count' => 800, 'comment_count' => 50, 'upload_date' => '20260701',
                    'channel_follower_count' => 1500,
                ],
                'https://youtube.com/shorts/v2' => [
                    'id' => 'v2', 'title' => 'Short Viral', 'webpage_url' => 'https://youtube.com/shorts/v2',
                    'duration' => 30, 'view_count' => 90000, 'like_count' => 7000, 'comment_count' => 200, 'upload_date' => '20260710',
                    'channel_follower_count' => 1500,
                ],
            ],
        );
    }

    private function fetcher(): YtDlpMonitoringFetcher
    {
        return new YtDlpMonitoringFetcher($this->runner(), new EngagementScorer, app(MonitoringStore::class));
    }

    public function test_recolhe_normaliza_e_pontua_do_youtube(): void
    {
        $itens = $this->fetcher()->atualizar('youtube', 'https://youtube.com/channel/X');

        $this->assertCount(2, $itens);

        $porId = collect($itens)->keyBy('id');
        $this->assertSame('vídeo', $porId['v1']['tipo']);        // 600s → vídeo
        $this->assertSame('short', $porId['v2']['tipo']);        // /shorts/ → short
        $this->assertSame(10000, $porId['v1']['views']);
        $this->assertSame('2026-07-01', $porId['v1']['publicado_em']);
        $this->assertArrayHasKey('score', $porId['v2']);          // pontuado
    }

    public function test_driver_le_do_store_e_deriva_resumo_e_melhores(): void
    {
        $this->fetcher()->atualizar('youtube', 'https://youtube.com/channel/X');

        $driver = new YtDlpMonitoringDriver('youtube', new EngagementScorer, app(MonitoringStore::class));

        $this->assertCount(2, $driver->conteudosRecentes(12));
        $resumo = $driver->resumo();
        $this->assertNotEmpty($resumo);
        $this->assertSame('Recent posts', $resumo[0]['label']);
        $this->assertSame('2', $resumo[0]['value']);
        // Melhor desempenho ordenado por score (o short viral pontua mais alto).
        $this->assertSame('v2', $driver->melhores(5)[0]['id']);
    }

    public function test_captura_estatisticas_de_canal(): void
    {
        $this->fetcher()->atualizar('youtube', 'https://youtube.com/channel/X');

        $canal = app(MonitoringStore::class)->canal('youtube');
        $this->assertSame(1500, $canal['subscribers']);   // do metadado
        $this->assertSame(2, $canal['posts']);            // nº de entradas únicas
    }

    public function test_estatisticas_totais_agrega_pelas_redes(): void
    {
        $this->fetcher()->atualizar('youtube', 'https://youtube.com/channel/X');

        $totais = app(\App\Services\Monitoring\MonitoringStats::class)->totais(['youtube', 'instagram']);

        $this->assertSame(1500, $totais['subscritores']);
        $this->assertSame(2, $totais['publicacoes']);
        $this->assertSame(100000, $totais['visualizacoes']);       // 10000 + 90000
        $this->assertSame(8050, $totais['interacoes']);            // (800+50)+(7000+200)
        $this->assertSame(1, $totais['redes']);                    // só o youtube foi recolhido
        $this->assertTrue($totais['temDados']);
    }

    public function test_url_vazio_devolve_vazio_sem_falhar(): void
    {
        $this->assertSame([], $this->fetcher()->atualizar('instagram', ''));
        $driver = new YtDlpMonitoringDriver('instagram', new EngagementScorer, app(MonitoringStore::class));
        $this->assertSame([], $driver->resumo());
    }

    public function test_botao_atualizar_recolhe_do_perfil_configurado(): void
    {
        // Vault temporário com um perfil de YouTube configurado.
        $tmp = sys_get_temp_dir().'/cm-mon-'.uniqid();
        mkdir($tmp.'/definicoes', 0775, true);
        config(['contentmachine.vault.path' => $tmp]);
        $this->app->singleton(VaultContract::class, fn () => new VaultRepository($tmp));
        app(SettingsRepository::class)->save(['perfis' => ['youtube' => ['url' => 'https://youtube.com/channel/X']]]);

        // Runner falso para a recolha.
        $this->app->instance(YtDlpRunnerContract::class, $this->runner());

        Livewire::test(Monitorizacao::class)
            ->set('rede', 'youtube')
            ->call('atualizar');

        $this->assertCount(2, app(MonitoringStore::class)->itens('youtube'));

        array_map('unlink', glob($tmp.'/definicoes/*') ?: []);
        @rmdir($tmp.'/definicoes');
        @rmdir($tmp);
    }

    /**
     * The /videos listing (up to 200 entries) used to fill every metadata slot,
     * so the /shorts tab never made the cut — shorts were simply absent.
     */
    public function test_shorts_get_a_fair_share_of_the_metadata_slots(): void
    {
        $videos = $shorts = $metadados = [];
        foreach (range(1, 6) as $i) {
            $u = "https://youtube.com/watch?v=v{$i}";
            $videos[] = ['id' => "v{$i}", 'webpage_url' => $u, 'title' => "V{$i}"];
            $metadados[$u] = ['id' => "v{$i}", 'title' => "V{$i}", 'webpage_url' => $u, 'duration' => 600, 'view_count' => 10, 'upload_date' => '20260701'];
        }
        foreach (range(1, 6) as $i) {
            $u = "https://youtube.com/shorts/s{$i}";
            $shorts[] = ['id' => "s{$i}", 'webpage_url' => $u, 'title' => "S{$i}"];
            $metadados[$u] = ['id' => "s{$i}", 'title' => "S{$i}", 'webpage_url' => $u, 'duration' => 30, 'view_count' => 10, 'upload_date' => '20260710'];
        }

        $runner = new FakeYtDlpRunner(metadados: $metadados, entradasPorNeedle: ['/shorts' => $shorts, '/videos' => $videos]);
        $fetcher = new YtDlpMonitoringFetcher($runner, new EngagementScorer, app(MonitoringStore::class));

        $itens = $fetcher->atualizar('youtube', 'https://youtube.com/channel/X', 4);

        $tipos = array_count_values(array_column($itens, 'tipo'));
        $this->assertSame(2, $tipos['vídeo'] ?? 0);
        $this->assertSame(2, $tipos['short'] ?? 0);
        // Ordered by real date after the tab-by-tab fetch: shorts (Jul 10) first.
        $this->assertSame('short', $itens[0]['tipo']);
    }

    public function test_pagina_mostra_heatmap_breakdown_e_tabela_de_tops(): void
    {
        // Timestamped items (real hour) so the best-time heatmap has data.
        app(MonitoringStore::class)->guardar('youtube', [
            ['id' => 'a', 'plataforma' => 'youtube', 'tipo' => 'short', 'titulo' => 'Short Viral', 'url' => '#', 'publicado_em' => '2026-08-21T18:05:00Z', 'views' => 900, 'likes' => 45, 'comentarios' => 5, 'partilhas' => 0, 'guardados' => 0, 'score' => 80],
            ['id' => 'b', 'plataforma' => 'youtube', 'tipo' => 'vídeo', 'titulo' => 'Vídeo Longo', 'url' => '#', 'publicado_em' => '2026-08-14T18:40:00Z', 'views' => 100, 'likes' => 10, 'comentarios' => 0, 'partilhas' => 0, 'guardados' => 0, 'score' => 40],
            ['id' => 'c', 'plataforma' => 'youtube', 'tipo' => 'short', 'titulo' => 'Outro', 'url' => '#', 'publicado_em' => '2026-08-16T10:00:00Z', 'views' => 50, 'likes' => 2, 'comentarios' => 0, 'partilhas' => 0, 'guardados' => 0, 'score' => 20],
        ], ['subscribers' => 1500]);

        Livewire::test(Monitorizacao::class)
            ->set('rede', 'youtube')
            ->assertSee('Best time to post')
            ->assertSee('Fri 18h')
            ->assertSee('Platform breakdown')
            ->assertSee('Top performing posts')
            ->assertSee('Engagement rate')
            ->assertSee('Best post')
            ->assertSee('Road to');
    }
}
