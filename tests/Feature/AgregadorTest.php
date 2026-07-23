<?php

namespace Tests\Feature;

use App\Jobs\AgregarConteudoJob;
use App\Livewire\Noticias;
use App\Services\Aggregation\NewsAggregator;
use App\Services\Aggregation\YtDlpRunnerContract;
use App\Services\Settings\SettingsRepository;
use App\Services\Vault\VaultContract;
use App\Services\Vault\VaultRepository;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Support\FakeYtDlpRunner;
use Tests\TestCase;

class AgregadorTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir().'/cm-agg-'.uniqid();
        mkdir($this->tmp, 0775, true);
        config(['contentmachine.vault.path' => $this->tmp]);
        $this->app->singleton(VaultContract::class, fn () => new VaultRepository($this->tmp));

        $meta = $this->itemFixture();
        $url = $meta['webpage_url'];

        // Runner falso: YouTube devolve um item; Instagram/LinkedIn nada (degrada).
        $this->app->instance(YtDlpRunnerContract::class, new FakeYtDlpRunner(
            metadados: [$url => $meta],
            vtt: file_get_contents(__DIR__.'/../Fixtures/captions.en.vtt'),
            entradasPorNeedle: ['youtube.com' => [['id' => $meta['id'], 'url' => $url]]],
        ));

        // Configura só canais de YouTube e Instagram.
        app(SettingsRepository::class)->save([
            'canais' => [
                'youtube' => ['https://www.youtube.com/@nicksaraev'],
                'instagram' => ['https://www.instagram.com/nick_saraev/'],
                'tiktok' => [],
                'linkedin' => [],
            ],
        ]);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->tmp);
        parent::tearDown();
    }

    /** @return array<string,mixed> */
    private function itemFixture(): array
    {
        return json_decode(file_get_contents(__DIR__.'/../Fixtures/yt-dlp-item.json'), true);
    }

    public function test_agrega_e_organiza_no_vault_por_dia(): void
    {
        $resumo = app(NewsAggregator::class)->aggregate();

        $this->assertSame(1, $resumo['total']);
        $this->assertSame(['2026-07-14'], $resumo['dias']);
        $this->assertSame(1, $resumo['por_plataforma']['youtube']);

        // Item arquivado no dia correcto, com frontmatter e transcrição.
        $vault = app(VaultContract::class);
        $nota = $vault->get('noticias/2026-07-14/youtube-8rvquzlraqo.md');

        $this->assertNotNull($nota);
        $this->assertSame('youtube', $nota->get('plataforma'));
        $this->assertSame('2026-07-14', $nota->get('data'));
        $this->assertSame('Nick Saraev', $nota->get('canal'));
        $this->assertNotEmpty($nota->get('tags'));
        $this->assertStringContainsString('the way the most productive people', $nota->body);
    }

    public function test_gera_nota_de_topicos_do_dia(): void
    {
        app(NewsAggregator::class)->aggregate();

        $topicos = app(VaultContract::class)->get('noticias/2026-07-14/topicos.md');

        $this->assertNotNull($topicos);
        $this->assertSame('topicos', $topicos->get('tipo'));
        $this->assertSame('heuristica', $topicos->get('metodo'));
        $this->assertStringContainsString('Tópicos cobertos', $topicos->body);
    }

    public function test_plataformas_sem_credenciais_geram_aviso(): void
    {
        $resumo = app(NewsAggregator::class)->aggregate();

        $this->assertNotEmpty($resumo['avisos']);
        $this->assertTrue(
            collect($resumo['avisos'])->contains(fn ($a) => str_contains($a, 'Instagram') && str_contains($a, 'Apify')),
            'Esperava um aviso de Instagram ignorado (só YouTube; resto precisa de Apify).'
        );
    }

    public function test_pagina_noticias_responde_200(): void
    {
        $this->get('/noticias')
            ->assertOk()
            ->assertSee('Agregar agora');
    }

    public function test_botao_agregar_agora_corre_e_mostra_dia(): void
    {
        Livewire::test(Noticias::class)
            ->call('agregarAgora')
            ->assertSet('diaSelecionado', '2026-07-14')
            ->assertSee('Nick Saraev');
    }

    public function test_agregar_agora_despacha_job_e_nao_bloqueia(): void
    {
        Queue::fake();

        Livewire::test(Noticias::class)
            ->call('agregarAgora')
            ->assertSet('aAgregar', true);

        Queue::assertPushed(AgregarConteudoJob::class);
    }

    private function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $f) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            $path = $dir.'/'.$f;
            is_dir($path) ? $this->rrmdir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
