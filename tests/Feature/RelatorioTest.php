<?php

namespace Tests\Feature;

use App\Jobs\GerarRelatorioJob;
use App\Livewire\Noticias;
use App\Services\Aggregation\LlmClient;
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

    // ───────────────────────── Tool tips (same pipeline, other write-up) ─────

    /** An LLM double that captures the prompt and answers with a fixed script. */
    private function llmEspiao(): object
    {
        $llm = new class extends LlmClient
        {
            public ?string $capturado = null;

            public bool $comWeb = false;

            public function disponivel(): bool
            {
                return true;
            }

            public function fornecedorAtivo(): ?string
            {
                return 'espiao';
            }

            public function texto(string $prompt, bool $comFerramentas = false, bool $json = false): ?string
            {
                $this->capturado = $prompt;
                $this->comWeb = $comFerramentas;

                return "**Burning tokens?**\n\nUse /compact.";
            }
        };
        $this->app->instance(LlmClient::class, $llm);

        return $llm;
    }

    public function test_dicas_pedem_guioes_de_utilizacao_e_nao_noticias(): void
    {
        $hoje = Carbon::today();
        $this->semearItem($hoje->toDateString(), 'youtube', 'a', ['ia']);
        $llm = $this->llmEspiao();

        $rel = app(RelatorioBuilder::class)->gerar($hoje, $hoje, 'dia', 'English', RelatorioBuilder::TIPO_DICAS);

        $this->assertSame(RelatorioBuilder::TIPO_DICAS, $rel['tipo']);
        $this->assertStringContainsString('Burning tokens?', $rel['redacao']);
        $this->assertStringContainsString('Tool tips —', $rel['titulo']);

        // The prompt must ask for usage tips, not for a news round-up.
        $this->assertStringContainsString('SHORT-FORM VIDEO SCRIPTS', (string) $llm->capturado);
        $this->assertStringContainsString('WHAT COUNTS AS A TIP', (string) $llm->capturado);
        $this->assertStringNotContainsString('AI-news editor', (string) $llm->capturado);
    }

    /** Without an LLM a tip could only be invented — say so instead of faking one. */
    public function test_dicas_sem_llm_dizem_que_falta_uma_chave_em_vez_de_inventar(): void
    {
        $hoje = Carbon::today();
        $this->semearItem($hoje->toDateString(), 'youtube', 'a', ['ia']);

        $rel = app(RelatorioBuilder::class)->gerar($hoje, $hoje, 'dia', 'English', RelatorioBuilder::TIPO_DICAS);

        $this->assertSame('sem-llm', $rel['redacao_metodo']);
        $this->assertStringContainsString('need an AI provider', $rel['redacao']);
        // The rest of the report still stands — it is collected, not written.
        $this->assertSame(1, $rel['total']);
        $this->assertNotEmpty($rel['topicos']);
    }

    // ───────────────────────── Topic scripts (typed topic, researched on the web) ─────

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
