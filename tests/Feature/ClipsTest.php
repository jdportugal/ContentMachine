<?php

namespace Tests\Feature;

use App\Livewire\Clips;
use App\Services\Shorts\ShortsPipeline;
use App\Services\Vault\VaultContract;
use App\Services\Vault\VaultRepository;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ClipsTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir().'/cm-clips-'.uniqid();
        mkdir($this->tmp, 0775, true);
        config(['contentmachine.vault.path' => $this->tmp]);
        config(['services.shorts.base_url' => 'http://fake.test']);
        $this->app->singleton(VaultContract::class, fn () => new VaultRepository($this->tmp));
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->tmp);
        parent::tearDown();
    }

    public function test_pagina_responde_200(): void
    {
        $this->get('/clips')->assertOk()->assertSee('Gerador de Clips');
    }

    public function test_adicionar_fonte_e_criar_clip(): void
    {
        $vault = $this->app->make(VaultContract::class);

        Livewire::test(Clips::class)
            ->set('novaFonte', 'http://x/v.mp4')
            ->set('novaFonteTitulo', 'Aula')
            ->call('adicionarFonte')
            ->assertSet('mensagem', 'Fonte adicionada.');

        $fontes = $vault->all('clips/fontes');
        $this->assertCount(1, $fontes);

        $slug = $fontes->first()->slug();
        $path = $fontes->first()->path;

        Livewire::test(Clips::class)
            ->set("clipInicio.$slug", '5')
            ->set("clipFim.$slug", '20')
            ->set("clipTitulo.$slug", 'Clip A')
            ->call('adicionarClip', $path, $slug)
            ->assertSet('mensagem', 'Clip criado.');

        $this->assertCount(1, $vault->all('clips', recursive: false));
    }

    public function test_editar_e_regenerar_grava_short(): void
    {
        Http::fake([
            '*/split-video' => Http::response(['job_id' => 'split1', 'status' => 'pending']),
            '*/add-subtitles' => Http::response(['job_id' => 'burn1', 'status' => 'pending']),
            '*/job-status/*' => Http::response(['status' => 'completed']),
            '*/download/*' => Http::response('VIDEO', 200),
        ]);

        $vault = $this->app->make(VaultContract::class);
        $fonte = $vault->create('clips/fontes', [
            'titulo' => 'F', 'tipo' => 'clip-fonte', 'fonte' => 'http://x/v.mp4', 'lingua' => 'pt', 'transcricao' => '',
        ]);
        $clip = $vault->create('clips', [
            'titulo' => 'C', 'tipo' => 'clip', 'fonte_path' => $fonte->path, 'fonte' => 'http://x/v.mp4',
            'inicio' => 5, 'fim' => 20, 'estado' => 'rascunho', 'modo_palavra' => 'karaoke',
            'estilo' => ShortsPipeline::estiloPorDefeito(),
            'subtitle_data' => json_encode([['start' => 0, 'end' => 2, 'text' => 'original', 'words' => []]]),
            'split_job_id' => '', 'output_job_id' => '', 'output_path' => '',
        ]);

        Livewire::test(Clips::class)
            ->call('abrir', $clip->path)
            ->assertSet('clipAberto', $clip->path)
            ->set('segmentos.0.text', 'texto editado pelo utilizador')
            ->call('regenerar', $clip->path)
            ->assertSet('mensagem', 'Short gravado com as legendas.');

        $atualizado = $vault->get($clip->path);
        $this->assertSame('pronto', $atualizado->get('estado'));

        // O subtitle_data guardado reflecte a edição.
        $this->assertStringContainsString('texto editado', (string) $atualizado->get('subtitle_data'));

        // add-subtitles foi chamado com o texto editado.
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/add-subtitles')
            && $r->data()['subtitle_data'][0]['text'] === 'texto editado pelo utilizador');
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
            $p = $dir.'/'.$f;
            is_dir($p) ? $this->rrmdir($p) : unlink($p);
        }
        rmdir($dir);
    }
}
