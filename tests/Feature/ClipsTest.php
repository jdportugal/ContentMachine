<?php

namespace Tests\Feature;

use App\Livewire\Clips;
use App\Services\Shorts\LocalVideoEngine;
use App\Services\Shorts\MusicLibrary;
use App\Services\Shorts\ShortsPipeline;
use App\Services\Vault\VaultContract;
use App\Services\Vault\VaultRepository;
use Livewire\Livewire;
use Tests\TestCase;

class ClipsTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        // Every route requires a session now (see Authenticate in bootstrap/app.php).
        $this->comSessaoIniciada();
        $this->tmp = sys_get_temp_dir().'/cm-clips-'.uniqid();
        mkdir($this->tmp, 0775, true);
        config(['contentmachine.vault.path' => $this->tmp]);
        $this->app->singleton(VaultContract::class, fn () => new VaultRepository($this->tmp));

        // Motor local falso: escreve ficheiros de vídeo simulados, sem ffmpeg.
        $this->app->singleton(LocalVideoEngine::class, fn () => new FakeVideoEngine);

        // Biblioteca de música isolada (temp), para não depender de uploads reais.
        $this->app->singleton(MusicLibrary::class, fn () => new MusicLibrary($this->tmp.'/musicas'));
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->tmp);
        parent::tearDown();
    }

    public function test_pagina_responde_200(): void
    {
        $this->get('/clips')->assertOk()->assertSee('Clip Generator');
    }

    public function test_adicionar_fonte_e_criar_clip(): void
    {
        $vault = $this->app->make(VaultContract::class);

        Livewire::test(Clips::class)
            ->set('novaFonte', '/videos/aula.mp4')
            ->set('novaFonteTitulo', 'Aula')
            ->call('adicionarFonte')
            ->assertDispatched('toast', message: 'Video added.');

        $fontes = $vault->all('clips/fontes');
        $this->assertCount(1, $fontes);

        $slug = $fontes->first()->slug();
        $path = $fontes->first()->path;

        Livewire::test(Clips::class)
            ->set("clipInicio.$slug", '5')
            ->set("clipFim.$slug", '20')
            ->set("clipTitulo.$slug", 'Clip A')
            ->call('adicionarClip', $path, $slug)
            ->assertDispatched('toast', message: 'Clip created.');

        $this->assertCount(1, $vault->all('clips', recursive: false));
    }

    public function test_editar_e_regenerar_grava_short(): void
    {
        $vault = $this->app->make(VaultContract::class);
        $fonte = $vault->create('clips/fontes', [
            'titulo' => 'F', 'tipo' => 'clip-fonte', 'fonte' => '/videos/v.mp4', 'lingua' => 'pt', 'transcricao' => '',
        ]);
        $clip = $vault->create('clips', [
            'titulo' => 'C', 'tipo' => 'clip', 'fonte_path' => $fonte->path, 'fonte' => '/videos/v.mp4',
            'inicio' => 5, 'fim' => 20, 'estado' => 'rascunho', 'modo_palavra' => 'karaoke',
            'estilo' => ShortsPipeline::estiloPorDefeito(),
            'subtitle_data' => json_encode([['start' => 0, 'end' => 2, 'text' => 'original', 'words' => []]]),
            'clip_path' => '', 'output_path' => '', 'musica' => '',
        ]);

        Livewire::test(Clips::class)
            ->call('abrir', $clip->path)
            ->assertSet('clipAberto', $clip->path)
            ->set('segmentos.0.text', 'texto editado pelo utilizador')
            ->call('regenerar', $clip->path)
            ->assertDispatched('toast', message: 'Short rendered with the captions.');

        $atualizado = $vault->get($clip->path);
        $this->assertSame('pronto', $atualizado->get('estado'));

        // O subtitle_data guardado reflecte a edição.
        $this->assertStringContainsString('texto editado', (string) $atualizado->get('subtitle_data'));

        // O motor recebeu o texto editado ao gravar as legendas (regenerar sem re-transcrever).
        $engine = $this->app->make(LocalVideoEngine::class);
        $this->assertSame('texto editado pelo utilizador', $engine->ultimaLegenda[0]['text'] ?? null);

        // Cortou primeiro (clip_path em falta) e depois gravou → output existe.
        $this->assertNotEmpty($atualizado->get('clip_path'));
        $this->assertNotEmpty($atualizado->get('output_path'));
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

/** Motor local falso para testes: sem ffmpeg, escreve ficheiros simulados. */
class FakeVideoEngine extends LocalVideoEngine
{
    public array $ultimaLegenda = [];

    public function __construct()
    {
        parent::__construct();
    }

    public function resolveSource(string $ref, string $tempDir): string
    {
        return $ref;
    }

    public function probe(string $path): array
    {
        return ['duration' => 30.0, 'width' => 1080, 'height' => 1920, 'has_audio' => true];
    }

    public function split(string $source, float $startSec, float $endSec, string $dest): string
    {
        @mkdir(dirname($dest), 0775, true);
        file_put_contents($dest, 'RAW');

        return $dest;
    }

    public function burnSubtitles(string $clipPath, array $subtitleData, array $settings, string $wordMode, string $dest): string
    {
        $this->ultimaLegenda = $subtitleData;
        @mkdir(dirname($dest), 0775, true);
        file_put_contents($dest, 'FINAL');

        return $dest;
    }

    public function addMusic(string $videoPath, string $musicPath, array $settings, string $dest): string
    {
        @mkdir(dirname($dest), 0775, true);
        file_put_contents($dest, 'MUSIC');

        return $dest;
    }

    public function transcribe(string $videoPath, string $language = 'pt'): array
    {
        return [['start' => 0, 'end' => 1, 'text' => 'teste', 'words' => []]];
    }
}
