<?php

namespace Tests\Feature;

use App\Livewire\Clips;
use App\Livewire\Publicacoes\Oficina;
use App\Livewire\Publicacoes\Publicacoes;
use App\Livewire\Rascunhos;
use App\Models\ClipProject;
use App\Services\Vault\VaultContract;
use App\Services\Vault\VaultRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FluxoRascunhosTest extends TestCase
{
    use RefreshDatabase;

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
        $this->apagarPasta($this->tmp);
        parent::tearDown();
    }

    private function apagarPasta(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $f) {
            if ($f === '.' || $f === '..') {
                continue;
            }
            $p = $dir.'/'.$f;
            is_dir($p) ? $this->apagarPasta($p) : @unlink($p);
        }
        @rmdir($dir);
    }

    private function idDe(string $source, string $ref): string
    {
        return $source.'_'.md5($ref);
    }

    public function test_so_publicacoes_prontas_aparecem_em_rascunhos(): void
    {
        $vault = app(VaultContract::class);
        $vault->create('rascunhos', ['titulo' => 'A trabalhar', 'tipo' => 'post', 'estado' => 'rascunho'], 'corpo');
        $vault->create('rascunhos', ['titulo' => 'Pronta', 'tipo' => 'post', 'estado' => 'pronto'], 'corpo');

        Livewire::test(Rascunhos::class)
            ->assertSee('Pronta')
            ->assertDontSee('A trabalhar');
    }

    public function test_alternar_pronto_promove_e_reabre(): void
    {
        $vault = app(VaultContract::class);
        $nota = $vault->create('rascunhos', [
            'titulo' => 'X', 'tipo' => 'post', 'estado' => 'rascunho',
            'origem' => 'publicacoes/oficina',
        ], 'corpo');

        Livewire::test(Publicacoes::class)->call('alternarPronto', $nota->path);
        $this->assertSame('pronto', $vault->get($nota->path)->get('estado'));

        Livewire::test(Publicacoes::class)->call('alternarPronto', $nota->path);
        $this->assertSame('rascunho', $vault->get($nota->path)->get('estado'));
    }

    public function test_agendar_publicacao_pronta(): void
    {
        $vault = app(VaultContract::class);
        $nota = $vault->create('rascunhos', ['titulo' => 'A agendar', 'tipo' => 'post', 'estado' => 'pronto'], 'corpo');

        Livewire::test(Rascunhos::class)
            ->set('datas.'.$this->idDe('post', $nota->path), '2026-09-01')
            ->call('agendar', 'post', $nota->path);

        $actualizada = $vault->get($nota->path);
        $this->assertSame('agendado', $actualizada->get('estado'));
        $this->assertSame('2026-09-01', $actualizada->get('agendado_para'));
    }

    public function test_agendar_short_renderizado(): void
    {
        $vault = app(VaultContract::class);
        $clip = $vault->create('clips', ['titulo' => 'Short A', 'tipo' => 'clip', 'estado' => 'pronto'], 'corpo');

        Livewire::test(Rascunhos::class)
            ->assertSee('Short A')
            ->set('datas.'.$this->idDe('clip', $clip->path), '2026-09-02')
            ->call('agendar', 'clip', $clip->path);

        $this->assertSame('agendado', $vault->get($clip->path)->get('estado'));
    }

    public function test_agendar_e_desagendar_clip_animado(): void
    {
        $p = ClipProject::create([
            'type' => 'animation', 'input_kind' => 'text', 'title' => 'Anim X', 'status' => 'done',
        ]);

        Livewire::test(Rascunhos::class)
            ->assertSee('Anim X')
            ->set('datas.'.$this->idDe('animado', (string) $p->id), '2026-09-03')
            ->call('agendar', 'animado', (string) $p->id);

        $this->assertSame('2026-09-03', $p->fresh()->scheduled_for->toDateString());

        Livewire::test(Rascunhos::class)->call('desagendar', 'animado', (string) $p->id);
        $this->assertNull($p->fresh()->scheduled_for);
    }

    public function test_gerar_publicacao_semeia_o_brief_da_oficina(): void
    {
        $vault = app(VaultContract::class);
        $fonte = $vault->create('clips/fontes', [
            'titulo' => 'Aula', 'tipo' => 'clip-fonte', 'estado' => 'transcrita',
            'transcricao' => json_encode([['text' => 'Isto é o texto falado do vídeo.', 'start' => 0, 'end' => 2]]),
        ], 'corpo');

        Livewire::test(Clips::class)->call('gerarPublicacao', $fonte->path);

        $this->assertStringContainsString('texto falado', (string) session('oficina_brief'));

        Livewire::test(Oficina::class, ['tipo' => 'post'])
            ->assertSet('brief', 'Isto é o texto falado do vídeo.');
        $this->assertNull(session('oficina_brief'));
    }
}
