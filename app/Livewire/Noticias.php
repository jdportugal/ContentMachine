<?php

namespace App\Livewire;

use App\Services\Aggregation\NewsAggregator;
use App\Services\Aggregation\RelatorioBuilder;
use App\Services\Vault\VaultContract;
use App\Services\Vault\VaultNote;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Agregador de Notícias')]
class Noticias extends Component
{
    /** Resumo da última agregação (contagens, dias, avisos). */
    public ?array $resumoAgregacao = null;

    /** Dia actualmente em foco na vista de itens agregados. */
    public string $diaSelecionado = '';

    // ----- Relatório por período -----
    /** 'dia' | 'semana' */
    public string $modoRelatorio = 'dia';

    /** Data de referência do relatório (YYYY-MM-DD). */
    public string $dataRelatorio = '';

    /** Recolher conteúdo novo (vídeos de hoje) antes de redigir o relatório. */
    public bool $recolherPrimeiro = true;

    /** Relatório gerado nesta sessão (para mostrar). @var array<string,mixed>|null */
    public ?array $relatorio = null;

    public ?string $relatorioGuardado = null;

    /** Caminho (no vault) do relatório arquivado atualmente em vista. */
    public string $relatorioSelecionado = '';

    public function mount(VaultContract $vault): void
    {
        $this->dataRelatorio = now()->toDateString();

        // Abre no relatório mais recente já arquivado (se existir).
        $ultimo = $this->notaUltimoRelatorio($vault->all('noticias'));
        $this->relatorioSelecionado = $ultimo?->path ?? '';
        $this->relatorio = $this->dadosDe($ultimo);
    }

    /** Ao escolher outro relatório no seletor, carrega-o do vault para vista. */
    public function updatedRelatorioSelecionado(VaultContract $vault): void
    {
        $nota = $this->relatorioSelecionado !== '' ? $vault->get($this->relatorioSelecionado) : null;

        $this->relatorio = $nota && $nota->get('tipo') === 'relatorio'
            ? $this->dadosDe($nota)
            : null;
    }

    /**
     * Corre a agregação multi-plataforma e foca o dia mais recente.
     *
     * O fluxo interactivo corre de forma síncrona para poder mostrar o resumo
     * de imediato. Para agendar em fila (produção com worker), despacha-se o
     * AgregarConteudoJob e a página lê depois os resultados do vault.
     */
    public function agregarAgora(NewsAggregator $aggregator): void
    {
        $resumo = $aggregator->aggregate();

        $this->resumoAgregacao = $resumo;
        $this->diaSelecionado = $resumo['dias'][0] ?? $this->diaSelecionado;
    }

    public function focarDia(string $dia): void
    {
        $this->diaSelecionado = $dia;
    }

    /** Gera um relatório a partir dos itens agregados no período escolhido. */
    public function criarRelatorio(RelatorioBuilder $builder, VaultContract $vault, NewsAggregator $aggregator): void
    {
        // Recolhe primeiro conteúdo novo dos canais (apanha os vídeos de hoje).
        if ($this->recolherPrimeiro) {
            $this->resumoAgregacao = $aggregator->aggregate();
        }

        $ref = Carbon::parse($this->dataRelatorio !== '' ? $this->dataRelatorio : now()->toDateString());

        // 'semana' = janela dos últimos 7 dias até à data escolhida (não a
        // semana de calendário), para apanhar todo o conteúdo recente agregado.
        [$inicio, $fim] = $this->modoRelatorio === 'semana'
            ? [$ref->copy()->subDays(6)->startOfDay(), $ref->copy()->endOfDay()]
            : [$ref->copy()->startOfDay(), $ref->copy()->startOfDay()];

        $relatorio = $builder->gerar($inicio, $fim, $this->modoRelatorio);

        // Persiste no vault (frontmatter + JSON para re-exibição + corpo Markdown).
        $slug = $this->modoRelatorio === 'semana'
            ? 'semana-'.$inicio->toDateString()
            : 'dia-'.$inicio->toDateString();

        $nota = $vault->put("noticias/relatorios/{$slug}.md", [
            'titulo' => $relatorio['titulo'],
            'tipo' => 'relatorio',
            'modo' => $relatorio['modo'],
            'inicio' => $relatorio['inicio'],
            'fim' => $relatorio['fim'],
            'total' => $relatorio['total'],
            'gerado_em' => $relatorio['gerado_em'],
            'estado' => 'arquivado',
            'tags' => ['noticias', 'relatorio', $relatorio['modo']],
            'dados' => json_encode($relatorio, JSON_UNESCAPED_UNICODE),
        ], $builder->corpoMarkdown($relatorio));

        $this->relatorio = $relatorio;
        $this->relatorioGuardado = $nota->path;
        // Passa a vista para o relatório recém-arquivado.
        $this->relatorioSelecionado = $nota->path;
    }

    public function render(VaultContract $vault)
    {
        $notas = $vault->all('noticias');

        $itens = $notas->filter(fn (VaultNote $n) => $n->get('tipo') === 'item_agregado');
        $dias = $itens->map(fn (VaultNote $n) => (string) $n->get('data'))
            ->filter()
            ->unique()
            ->sortDesc()
            ->values();

        $dia = $this->diaSelecionado !== '' && $dias->contains($this->diaSelecionado)
            ? $this->diaSelecionado
            : (string) $dias->first();

        $itensDoDia = $itens->filter(fn (VaultNote $n) => (string) $n->get('data') === $dia)->values();
        $topicosDoDia = $this->notaTopicos($notas, $dia);

        // O relatório em vista é a propriedade pública $relatorio (partilhada
        // automaticamente com a view pelo Livewire); aqui só listamos os
        // arquivados para o seletor.
        return view('livewire.noticias', [
            'dias' => $dias,
            'diaAtivo' => $dia,
            'itensDoDia' => $itensDoDia,
            'topicosHtml' => $topicosDoDia?->html(),
            'relatoriosPassados' => $this->relatoriosPassados($notas),
        ]);
    }

    private function notaTopicos(Collection $notas, string $dia): ?VaultNote
    {
        return $notas->first(fn (VaultNote $n) => $n->get('tipo') === 'topicos' && (string) $n->get('data') === $dia);
    }

    /** Todas as notas de relatório arquivadas, da mais recente para a mais antiga. */
    private function notasRelatorios(Collection $notas): Collection
    {
        return $notas
            ->filter(fn (VaultNote $n) => $n->get('tipo') === 'relatorio' && filled($n->get('dados')))
            ->sortByDesc(fn (VaultNote $n) => (string) $n->get('gerado_em', $n->get('inicio', '')))
            ->values();
    }

    private function notaUltimoRelatorio(Collection $notas): ?VaultNote
    {
        return $this->notasRelatorios($notas)->first();
    }

    /**
     * Opções para o seletor de relatórios anteriores: caminho + rótulo legível.
     *
     * @return array<int,array{path:string,rotulo:string}>
     */
    private function relatoriosPassados(Collection $notas): array
    {
        return $this->notasRelatorios($notas)
            ->map(fn (VaultNote $n) => ['path' => $n->path, 'rotulo' => $this->rotuloRelatorio($n)])
            ->all();
    }

    /** Rótulo curto para o seletor, ex.: «Dia · 22 jul 2026 · 12 item(s)». */
    private function rotuloRelatorio(VaultNote $n): string
    {
        $modo = Str::ucfirst((string) $n->get('modo', 'dia'));
        $inicio = (string) $n->get('inicio', '');
        $data = $inicio !== '' ? Carbon::parse($inicio)->translatedFormat('d M Y') : '—';
        $total = (int) $n->get('total', 0);

        return "{$modo} · {$data} · {$total} item(s)";
    }

    /** @return array<string,mixed>|null */
    private function dadosDe(?VaultNote $nota): ?array
    {
        if (! $nota) {
            return null;
        }

        $dados = json_decode((string) $nota->get('dados'), true);

        return is_array($dados) ? $dados : null;
    }
}
