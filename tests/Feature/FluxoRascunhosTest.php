<?php

namespace Tests\Feature;

use App\Livewire\Publicacoes\Oficina;
use App\Livewire\Rascunhos;
use App\Services\Vault\VaultContract;
use App\Services\Vault\VaultRepository;
use Livewire\Livewire;
use Tests\TestCase;

class FluxoRascunhosTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        // Aponta o vault para uma pasta temporária, sem tocar nos exemplos.
        $this->tmp = sys_get_temp_dir().'/cm-fluxo-'.uniqid();
        mkdir($this->tmp, 0775, true);
        config(['contentmachine.vault.path' => $this->tmp]);
        $this->app->singleton(VaultContract::class, fn () => new VaultRepository($this->tmp));
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tmp.'/rascunhos/*.md') ?: []);
        parent::tearDown();
    }

    public function test_criar_rascunho_grava_no_vault(): void
    {
        Livewire::test(Oficina::class, ['tipo' => 'post'])
            ->set('titulo', 'Peça de teste')
            ->set('legenda', 'Corpo da peça de teste.')
            ->set('plataforma', 'instagram')
            ->call('criarRascunho')
            ->assertSet('guardado', 'Peça de teste');

        $notas = app(VaultContract::class)->all('rascunhos');
        $this->assertCount(1, $notas);
        $this->assertSame('post', $notas->first()->get('tipo'));
    }

    public function test_agendar_rascunho_actualiza_estado(): void
    {
        $vault = app(VaultContract::class);
        $nota = $vault->create('rascunhos', ['titulo' => 'A agendar', 'tipo' => 'post', 'estado' => 'rascunho'], 'corpo');

        Livewire::test(Rascunhos::class)
            ->set('datas.'.$nota->slug(), '2026-09-01')
            ->call('agendar', $nota->path);

        $actualizada = $vault->get($nota->path);
        $this->assertSame('agendado', $actualizada->get('estado'));
        $this->assertSame('2026-09-01', $actualizada->get('agendado_para'));
    }
}
