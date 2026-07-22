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

    public function test_gerar_imagens_preenche_previews_com_svg(): void
    {
        Livewire::test(Oficina::class, ['tipo' => 'carrossel'])
            ->set('titulo', 'Com imagens')
            ->set('slides', [
                ['titulo' => 'Capa', 'texto' => 'A'],
                ['titulo' => 'Dois', 'texto' => 'B'],
            ])
            ->call('gerarImagens')
            ->assertCount('previews', 2)
            ->assertSee('<svg', false);
    }

    public function test_redigir_com_ia_usa_heuristica_quando_llm_indisponivel(): void
    {
        // LlmClient que nunca produz texto → força a heurística.
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
            ->assertHasNoErrors();

        $this->assertGreaterThanOrEqual(2, count($componente->get('slides')));

        // Um post (single) preenche a legenda.
        Livewire::test(Oficina::class, ['tipo' => 'post'])
            ->set('brief', 'Uma ideia directa.')
            ->call('redigirComIa')
            ->assertSet('legenda', 'Uma ideia directa.');
    }
}
