<?php

namespace Tests\Feature;

use App\Livewire\Noticias;
use App\Services\Aggregation\RelatorioBuilder;
use App\Services\Vault\VaultContract;
use App\Services\Vault\VaultRepository;
use Illuminate\Support\Carbon;
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
            ->set('modoRelatorio', 'dia')
            ->set('dataRelatorio', $hoje)
            ->call('criarRelatorio')
            ->assertSet('relatorio', fn ($v) => is_array($v) && $v['total'] === 1);

        $nota = $this->vault->get("noticias/relatorios/dia-{$hoje}.md");
        $this->assertNotNull($nota);
        $this->assertSame('relatorio', $nota->get('tipo'));
        $this->assertNotEmpty($nota->get('dados'));
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
