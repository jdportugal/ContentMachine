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
}
