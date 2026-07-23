<?php

namespace App\Livewire;

use App\Jobs\AgregarConteudoJob;
use App\Jobs\GerarRelatorioJob;
use App\Services\Vault\VaultContract;
use App\Services\Vault\VaultNote;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
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

    /** Geração em curso na fila (controla a sondagem). */
    public bool $aGerar = false;

    public ?string $relatorioToken = null;

    public ?string $avisoRelatorio = null;

    /** Recolha (agregação) em curso na fila. */
    public bool $aAgregar = false;

    public ?string $agregacaoToken = null;

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
     * Corre numa FILA (worker): a recolha faz dezenas de chamadas yt-dlp e
     * estouraria o max_execution_time no pedido web. A página sonda o resultado.
     */
    public function agregarAgora(): void
    {
        $this->agregacaoToken = (string) Str::uuid();
        $this->aAgregar = true;
        $this->dispatch('loader-show', message: 'A vasculhar os canais… (requer «php artisan queue:work»)');

        AgregarConteudoJob::dispatch($this->agregacaoToken);

        $this->verificarAgregacao();
    }

    /** Sondado (wire:poll) enquanto $aAgregar: mostra o resumo quando o worker termina. */
    public function verificarAgregacao(): void
    {
        if (! $this->aAgregar || $this->agregacaoToken === null) {
            return;
        }

        $resumo = Cache::get(AgregarConteudoJob::key($this->agregacaoToken));
        if ($resumo === null) {
            return;
        }

        $this->aAgregar = false;
        $this->dispatch('loader-hide');
        Cache::forget(AgregarConteudoJob::key($this->agregacaoToken));

        if (! empty($resumo['erro'])) {
            $this->avisoRelatorio = 'A recolha falhou. Verifique se o worker está a correr («php artisan queue:work»).';

            return;
        }

        $this->resumoAgregacao = $resumo;
        $this->diaSelecionado = $resumo['dias'][0] ?? $this->diaSelecionado;
    }

    public function focarDia(string $dia): void
    {
        $this->diaSelecionado = $dia;
    }

    /**
     * Dispara a geração do relatório numa FILA (worker) e passa a sondar o
     * resultado — a redação com IA/pesquisa web demora e estouraria o
     * max_execution_time se corresse no pedido web. Mesmo padrão da oficina.
     */
    public function criarRelatorio(): void
    {
        $this->relatorioToken = (string) Str::uuid();
        $this->aGerar = true;
        $this->avisoRelatorio = null;
        $this->dispatch('loader-show', message: 'A recolher e redigir o relatório… (requer «php artisan queue:work»)');

        GerarRelatorioJob::dispatch(
            $this->modoRelatorio,
            $this->dataRelatorio,
            $this->recolherPrimeiro,
            $this->relatorioToken,
        );

        $this->verificarRelatorio(app(VaultContract::class));
    }

    /** Sondado (wire:poll) enquanto $aGerar: carrega o relatório quando o worker termina. */
    public function verificarRelatorio(VaultContract $vault): void
    {
        if (! $this->aGerar || $this->relatorioToken === null) {
            return;
        }

        $r = Cache::get(GerarRelatorioJob::key($this->relatorioToken));
        if ($r === null) {
            return;
        }

        $this->aGerar = false;
        $this->dispatch('loader-hide');
        Cache::forget(GerarRelatorioJob::key($this->relatorioToken));

        if (! empty($r['erro'])) {
            $this->avisoRelatorio = 'A geração falhou. Verifique se o worker está a correr («php artisan queue:work») e tente de novo.';

            return;
        }

        $nota = $vault->get($r['path']);
        $this->relatorio = $this->dadosDe($nota);
        $this->relatorioGuardado = $r['path'];
        // Passa a vista para o relatório recém-arquivado.
        $this->relatorioSelecionado = $r['path'];
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
