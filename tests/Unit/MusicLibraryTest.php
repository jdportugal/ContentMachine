<?php

namespace Tests\Unit;

use App\Services\Shorts\MusicLibrary;
use PHPUnit\Framework\TestCase;

class MusicLibraryTest extends TestCase
{
    private string $dir;

    private string $src;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/cm-music-'.uniqid();
        $this->src = sys_get_temp_dir().'/cm-src-'.uniqid();
        mkdir($this->src, 0775, true);
    }

    protected function tearDown(): void
    {
        foreach ([$this->dir, $this->src] as $d) {
            if (is_dir($d)) {
                array_map('unlink', glob($d.'/*') ?: []);
                rmdir($d);
            }
        }
        parent::tearDown();
    }

    private function ficheiro(string $nome): string
    {
        $p = $this->src.'/'.$nome;
        file_put_contents($p, 'AUDIO');

        return $p;
    }

    public function test_biblioteca_vazia_devolve_null_no_random(): void
    {
        $lib = new MusicLibrary($this->dir);
        $this->assertTrue($lib->isEmpty());
        $this->assertNull($lib->randomPath());
        $this->assertSame([], $lib->all());
    }

    public function test_adiciona_lista_e_encontra_por_nome(): void
    {
        $lib = new MusicLibrary($this->dir);
        $nome = $lib->add($this->ficheiro('A Minha Música.mp3'), 'A Minha Música.mp3');

        $this->assertSame('a-minha-musica.mp3', $nome); // nome seguro (slug)
        $this->assertCount(1, $lib->all());
        $this->assertNotNull($lib->pathFor($nome));
        $this->assertFalse($lib->isEmpty());
    }

    public function test_nomes_duplicados_ficam_unicos(): void
    {
        $lib = new MusicLibrary($this->dir);
        $a = $lib->add($this->ficheiro('musica.mp3'), 'musica.mp3');
        $b = $lib->add($this->ficheiro('musica.mp3'), 'musica.mp3');

        $this->assertNotSame($a, $b);
        $this->assertCount(2, $lib->all());
    }

    public function test_ignora_extensoes_nao_audio(): void
    {
        $lib = new MusicLibrary($this->dir);
        // grava um ficheiro não-áudio diretamente na pasta
        file_put_contents($lib->dir().'/notas.txt', 'x');
        $lib->add($this->ficheiro('trilha.wav'), 'trilha.wav');

        $this->assertCount(1, $lib->all()); // só a faixa .wav conta
    }

    public function test_random_devolve_uma_das_faixas(): void
    {
        $lib = new MusicLibrary($this->dir);
        $lib->add($this->ficheiro('um.mp3'), 'um.mp3');
        $lib->add($this->ficheiro('dois.mp3'), 'dois.mp3');

        $caminhos = array_column($lib->all(), 'path');
        $this->assertContains($lib->randomPath(), $caminhos);
    }

    public function test_remove_faixa(): void
    {
        $lib = new MusicLibrary($this->dir);
        $nome = $lib->add($this->ficheiro('x.mp3'), 'x.mp3');

        $this->assertTrue($lib->remove($nome));
        $this->assertTrue($lib->isEmpty());
        $this->assertFalse($lib->remove($nome)); // já não existe
    }
}
