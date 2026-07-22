<?php

namespace Tests\Feature;

use App\Livewire\Publicacoes\Oficina;
use App\Services\Aggregation\LlmClient;
use App\Services\Vault\VaultContract;
use App\Services\Vault\VaultRepository;
use Livewire\Livewire;
use Tests\TestCase;

class OficinaTest extends TestCase
{
    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir().'/cm-oficina-'.uniqid();
        mkdir($this->tmp, 0775, true);
        config(['contentmachine.vault.path' => $this->tmp]);
        $this->app->singleton(VaultContract::class, fn () => new VaultRepository($this->tmp));
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tmp.'/rascunhos/*.md') ?: []);
        parent::tearDown();
    }

    public function test_tipo_desconhecido_devolve_404(): void
    {
        $this->get('/publicacoes/inexistente')->assertNotFound();
    }

    public function test_carrossel_grava_nota_com_varios_cartoes(): void
    {
        Livewire::test(Oficina::class, ['tipo' => 'carrossel'])
            ->set('titulo', 'Guia de teste')
            ->set('slides', [
                ['titulo' => 'Capa', 'texto' => 'A promessa'],
                ['titulo' => 'Ideia', 'texto' => 'O desenvolvimento'],
                ['titulo' => 'Remate', 'texto' => 'A síntese'],
            ])
            ->call('criarRascunho')
            ->assertSet('guardado', 'Guia de teste');

        $nota = app(VaultContract::class)->all('rascunhos')->first();
        $this->assertSame('carrossel', $nota->get('tipo'));
        $this->assertSame('carousel', $nota->get('formato'));
        $this->assertSame(3, $nota->get('cartoes'));
        $this->assertStringContainsString('## Capa', $nota->body);
        $this->assertStringContainsString('---', $nota->body);
    }

    public function test_gerar_imagens_preenche_previews_com_ficheiros(): void
    {
        // Fila síncrona nos testes → o job corre já e as imagens ficam prontas.
        $componente = Livewire::test(Oficina::class, ['tipo' => 'carrossel'])
            ->set('titulo', 'Com imagens')
            ->set('slides', [
                ['titulo' => 'Capa', 'texto' => 'A'],
                ['titulo' => 'Dois', 'texto' => 'B'],
            ])
            ->call('gerarImagens')
            ->assertSet('aGerar', false);

        $img = $componente->get('img');
        $this->assertCount(2, $img);
        $this->assertStringContainsString('media/publicacoes/', $img[0]);
        $this->assertStringContainsString('media/publicacoes/', $img[1]);
    }

    public function test_editar_publicacao_existente_actualiza_a_mesma_nota(): void
    {
        $vault = app(VaultContract::class);
        $nota = $vault->create('rascunhos', [
            'titulo' => 'Original', 'tipo' => 'carrossel', 'formato' => 'carousel',
            'plataforma' => 'instagram', 'estado' => 'rascunho', 'origem' => 'publicacoes/oficina', 'cartoes' => 2,
        ], "## Capa\n\nA promessa\n\n---\n\n## Dois\n\nO conteúdo");

        $c = Livewire::withQueryParams(['nota' => $nota->slug()])
            ->test(Oficina::class, ['tipo' => 'carrossel'])
            ->assertSet('titulo', 'Original')
            ->assertSet('notaPath', $nota->path);

        // O corpo foi reconstruído em cartões editáveis.
        $this->assertSame('Capa', $c->get('slides')[0]['titulo']);
        $this->assertSame('O conteúdo', $c->get('slides')[1]['texto']);

        $c->set('titulo', 'Alterado')->call('criarRascunho')->assertSet('guardado', 'Alterado');

        // Actualizou a MESMA nota (não criou outra).
        $notas = $vault->all('rascunhos');
        $this->assertCount(1, $notas);
        $this->assertSame('Alterado', $notas->first()->get('titulo'));
        $this->assertSame('carrossel', $notas->first()->get('tipo'));
    }

    public function test_index_lista_publicacoes_criadas(): void
    {
        app(VaultContract::class)->create('rascunhos', [
            'titulo' => 'Minha peça', 'tipo' => 'post', 'origem' => 'publicacoes/oficina',
        ], 'corpo');

        Livewire::test(\App\Livewire\Publicacoes\Publicacoes::class)
            ->assertSee('Minha peça')
            ->assertSee('Publicações criadas');
    }

    public function test_gerar_imagens_despacha_job(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        Livewire::test(Oficina::class, ['tipo' => 'carrossel'])
            ->set('titulo', 'Fila')
            ->set('slides', [['titulo' => 'Capa', 'texto' => 'A'], ['titulo' => 'Dois', 'texto' => 'B']])
            ->call('gerarImagens')
            ->assertSet('aGerar', true);

        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\GerarImagensJob::class);
    }

    public function test_regenerar_cartao_despacha_job_e_marca_em_curso(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        $c = Livewire::test(Oficina::class, ['tipo' => 'carrossel'])
            ->set('slides', [['titulo' => 'Capa', 'texto' => 'A'], ['titulo' => 'Dois', 'texto' => 'B']])
            ->call('regenerarCartao', 0);

        $this->assertArrayHasKey(0, $c->get('gerando'));
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\RegenerarCartaoJob::class);
    }

    public function test_restaurar_versao_troca_a_imagem_actual(): void
    {
        $c = Livewire::test(Oficina::class, ['tipo' => 'carrossel'])
            ->set('img', [0 => 'media/publicacoes/actual/1.png'])
            ->set('hist', [0 => ['media/publicacoes/antiga/1.png']])
            ->call('restaurarVersao', 0, 'media/publicacoes/antiga/1.png');

        $this->assertSame('media/publicacoes/antiga/1.png', $c->get('img')[0]);
        $this->assertContains('media/publicacoes/actual/1.png', $c->get('hist')[0]);
    }

    public function test_redigir_com_ia_usa_heuristica_quando_llm_indisponivel(): void
    {
        // LlmClient que nunca produz texto → força a heurística.
        // Fila é síncrona nos testes, por isso o job corre já e o resultado
        // é aplicado no mesmo pedido (redigirComIa → verificarPlano).
        $this->app->bind(LlmClient::class, fn () => new class extends LlmClient
        {
            public function texto(string $prompt, bool $comFerramentas = false): ?string
            {
                return null;
            }
        });

        $componente = Livewire::test(Oficina::class, ['tipo' => 'carrossel'])
            ->set('brief', "Primeira ideia. Segunda ideia. Terceira ideia.")
            ->call('redigirComIa')
            ->assertHasNoErrors()
            ->assertSet('aRedigir', false); // resolvido de imediato (fila sync)

        $this->assertGreaterThanOrEqual(2, count($componente->get('slides')));

        // Um post (single) preenche a legenda.
        Livewire::test(Oficina::class, ['tipo' => 'post'])
            ->set('brief', 'Uma ideia directa.')
            ->call('redigirComIa')
            ->assertSet('legenda', 'Uma ideia directa.');
    }

    public function test_redigir_com_ia_despacha_job_de_planeamento(): void
    {
        \Illuminate\Support\Facades\Queue::fake();

        Livewire::test(Oficina::class, ['tipo' => 'carrossel'])
            ->set('brief', 'Um tema qualquer.')
            ->call('redigirComIa')
            ->assertSet('aRedigir', true); // job em fila, ainda por processar

        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\PlanearPublicacaoJob::class);
    }
}
