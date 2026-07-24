<?php

namespace Tests\Feature;

use App\Livewire\Clips;
use App\Livewire\Publicacoes\Oficina;
use App\Livewire\Publicacoes\Publicacoes;
use App\Livewire\Rascunhos;
use App\Services\Aggregation\LlmClient;
use App\Services\Clips\Store\ClipStore;
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
        $store = app(ClipStore::class);
        $p = $store->create([
            'type' => 'animation', 'input_kind' => 'text', 'title' => 'Anim X', 'status' => 'done',
        ]);

        Livewire::test(Rascunhos::class)
            ->assertSee('Anim X')
            ->set('datas.'.$this->idDe('animado', $p->id), '2026-09-03')
            ->call('agendar', 'animado', $p->id);

        $this->assertSame('2026-09-03', $store->find($p->id)->scheduled_for);

        Livewire::test(Rascunhos::class)->call('desagendar', 'animado', $p->id);
        $this->assertNull($store->find($p->id)->scheduled_for);
    }

    public function test_escolher_clips_com_ia_cria_clips_e_guarda_publicacoes(): void
    {
        $this->app->instance(LlmClient::class, new class extends LlmClient
        {
            public function disponivel(): bool
            {
                return true;
            }

            public function texto(string $prompt, bool $comFerramentas = false): ?string
            {
                return json_encode([
                    'segments' => [['title' => 'Short A', 'description' => 'd', 'start_time' => 0, 'end_time' => 2, 'tags' => ['x']]],
                    'publications' => [['titulo' => 'Post A', 'angulo' => 'Ângulo A']],
                ]);
            }
        });

        $vault = app(VaultContract::class);
        $fonte = $vault->create('clips/fontes', [
            'titulo' => 'Aula', 'tipo' => 'clip-fonte', 'estado' => 'transcrita',
            'transcricao' => json_encode([['text' => 'Olá mundo', 'start' => 0, 'end' => 2]]),
        ], 'corpo');

        Livewire::test(Clips::class)->call('sugerirIA', $fonte->path);

        $clips = $vault->all('clips')->filter(fn ($n) => $n->get('tipo') === 'clip');
        $this->assertCount(1, $clips);

        $pubs = json_decode((string) $vault->get($fonte->path)->get('publicacoes_sugeridas'), true);
        $this->assertSame('Post A', $pubs[0]['titulo']);
        $this->assertSame('Ângulo A', $pubs[0]['angulo']);
    }

    public function test_abrir_publicacao_sugerida_semeia_o_brief_da_oficina(): void
    {
        $vault = app(VaultContract::class);
        $fonte = $vault->create('clips/fontes', [
            'titulo' => 'Aula', 'tipo' => 'clip-fonte', 'estado' => 'transcrita',
            'publicacoes_sugeridas' => json_encode([
                ['titulo' => 'Cinco erros comuns', 'angulo' => 'Lista prática com o que evitar.'],
            ]),
        ], 'corpo');

        Livewire::test(Clips::class)->call('abrirPublicacao', $fonte->path, 0);

        $this->assertStringContainsString('Cinco erros comuns', (string) session('oficina_brief'));

        Livewire::test(Oficina::class, ['tipo' => 'post'])
            ->assertSet('brief', "Cinco erros comuns\n\nLista prática com o que evitar.");
        $this->assertNull(session('oficina_brief'));
    }
}
