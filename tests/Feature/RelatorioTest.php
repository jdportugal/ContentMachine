<?php

namespace Tests\Feature;

use App\Jobs\GerarRelatorioJob;
use App\Livewire\Noticias;
use App\Services\Aggregation\RelatorioBuilder;
use App\Services\Vault\VaultContract;
use App\Services\Vault\VaultRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class RelatorioTest extends TestCase
{
    private string $tmp;

    private VaultContract $vault;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir().'/cm-relatorio-'.uniqid();
        mkdir($this->tmp, 0775, true);
        config(['contentmachine.vault.path' => $this->tmp]);
        $this->vault = new VaultRepository($this->tmp);
        $this->app->instance(VaultContract::class, $this->vault);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->tmp);
        parent::tearDown();
    }

    private function semearItem(string $dia, string $plataforma, string $id, array $tags, array $fontes = []): void
    {
        $this->vault->put("noticias/{$dia}/{$plataforma}-{$id}.md", [
            'titulo' => "Vídeo {$id}",
            'tipo' => 'item_agregado',
            'plataforma' => $plataforma,
            'canal' => '@canal',
            'data' => $dia,
            'url' => "https://exemplo/{$id}",
            'thumbnail' => '',
            'tags' => $tags,
            'fontes' => $fontes,
        ], '## Transcrição\n\ntexto');
    }

    public function test_relatorio_do_dia_reune_itens_desse_dia(): void
    {
        $hoje = Carbon::today();
        $this->semearItem($hoje->toDateString(), 'youtube', 'a', ['ia', 'automacao'], ['https://fonte/1']);
        $this->semearItem($hoje->toDateString(), 'tiktok', 'b', ['ia', 'clips']);
        $this->semearItem($hoje->copy()->subDays(3)->toDateString(), 'youtube', 'c', ['antigo']);

        $rel = app(RelatorioBuilder::class)->gerar($hoje, $hoje, 'dia');

        $this->assertSame(2, $rel['total']);
        $this->assertSame('dia', $rel['modo']);
        $this->assertNotEmpty($rel['destaques']);
        $this->assertNotEmpty($rel['topicos']);
        $this->assertStringContainsString('2 item', $rel['resumo']);
        // Redação: texto escrito (não vazio) sobre o que os canais cobrem.
        $this->assertNotEmpty($rel['redacao']);
        $this->assertStringContainsString('peça(s)', $rel['redacao']);
        // O item com fonte citada deve pontuar mais alto nos destaques.
        $this->assertSame('youtube', $rel['destaques'][0]['plataforma']);
    }

    public function test_relatorio_da_semana_abrange_varios_dias(): void
    {
        $ref = Carbon::today();
        // Semeia no início e no fim da semana — ambos garantidamente no período.
        $this->semearItem($ref->copy()->startOfWeek()->toDateString(), 'youtube', 'a', ['ia']);
        $this->semearItem($ref->copy()->endOfWeek()->toDateString(), 'tiktok', 'b', ['ia']);
        // Fora da semana: não deve entrar.
        $this->semearItem($ref->copy()->startOfWeek()->subDay()->toDateString(), 'youtube', 'c', ['fora']);

        $rel = app(RelatorioBuilder::class)->gerar(
            $ref->copy()->startOfWeek(),
            $ref->copy()->endOfWeek(),
            'semana'
        );

        $this->assertSame(2, $rel['total']);
        $this->assertSame('semana', $rel['modo']);
    }

    public function test_criar_relatorio_persiste_nota_no_vault(): void
    {
        $hoje = Carbon::today()->toDateString();
        $this->semearItem($hoje, 'youtube', 'a', ['ia'], ['https://fonte/1']);

        Livewire::test(Noticias::class)
            ->set('recolherPrimeiro', false) // não ir à rede durante o teste
            ->set('modoRelatorio', 'dia')
            ->set('dataRelatorio', $hoje)
            ->call('criarRelatorio')
            ->assertSet('relatorio', fn ($v) => is_array($v) && $v['total'] === 1 && filled($v['redacao']))
            // Ao criar, a vista passa a apontar para o relatório recém-arquivado.
            ->assertSet('relatorioSelecionado', "noticias/relatorios/dia-{$hoje}.md");

        $nota = $this->vault->get("noticias/relatorios/dia-{$hoje}.md");
        $this->assertNotNull($nota);
        $this->assertSame('relatorio', $nota->get('tipo'));
        $this->assertNotEmpty($nota->get('dados'));
    }

    public function test_criar_relatorio_despacha_job_e_fica_a_gerar(): void
    {
        Queue::fake();

        Livewire::test(Noticias::class)
            ->set('recolherPrimeiro', false)
            ->set('modoRelatorio', 'dia')
            ->set('dataRelatorio', Carbon::today()->toDateString())
            ->call('criarRelatorio')
            // Não bloqueia o pedido web: fica a gerar e sonda o worker.
            ->assertSet('aGerar', true);

        Queue::assertPushed(GerarRelatorioJob::class);
    }

    /** Grava um relatório arquivado diretamente no vault (sem passar pela geração). */
    private function semearRelatorio(string $slug, string $modo, string $inicio, string $titulo, int $total): void
    {
        $this->vault->put("noticias/relatorios/{$slug}.md", [
            'titulo' => $titulo,
            'tipo' => 'relatorio',
            'modo' => $modo,
            'inicio' => $inicio,
            'fim' => $inicio,
            'total' => $total,
            'gerado_em' => $inicio.'T09:00:00+00:00',
            'estado' => 'arquivado',
            'tags' => ['noticias', 'relatorio', $modo],
            'dados' => json_encode(['titulo' => $titulo, 'modo' => $modo, 'total' => $total, 'resumo' => 'x'], JSON_UNESCAPED_UNICODE),
        ], "# {$titulo}");
    }

    public function test_seletor_lista_relatorios_arquivados_e_abre_no_mais_recente(): void
    {
        $this->semearRelatorio('dia-2026-07-20', 'dia', '2026-07-20', 'Antigo', 3);
        $this->semearRelatorio('dia-2026-07-22', 'dia', '2026-07-22', 'Recente', 12);

        Livewire::test(Noticias::class)
            // Abre no mais recente por defeito.
            ->assertSet('relatorioSelecionado', 'noticias/relatorios/dia-2026-07-22.md')
            ->assertViewHas('relatoriosPassados', fn ($lista) => count($lista) === 2
                && $lista[0]['path'] === 'noticias/relatorios/dia-2026-07-22.md')
            // Mostra o mais recente.
            ->assertViewHas('relatorio', fn ($r) => is_array($r) && $r['titulo'] === 'Recente');
    }

    public function test_selecionar_relatorio_antigo_carrega_esse_relatorio(): void
    {
        $this->semearRelatorio('dia-2026-07-20', 'dia', '2026-07-20', 'Antigo', 3);
        $this->semearRelatorio('dia-2026-07-22', 'dia', '2026-07-22', 'Recente', 12);

        Livewire::test(Noticias::class)
            ->set('relatorioSelecionado', 'noticias/relatorios/dia-2026-07-20.md')
            ->assertViewHas('relatorio', fn ($r) => is_array($r) && $r['titulo'] === 'Antigo' && $r['total'] === 3);
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
