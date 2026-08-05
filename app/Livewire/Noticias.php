<?php

namespace App\Livewire;

use App\Jobs\AgregarConteudoJob;
use App\Jobs\GerarRelatorioJob;
use App\Services\Projects\ProjectLanguage;
use App\Services\Settings\SettingsRepository;
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
#[Title('News Aggregator')]
class Noticias extends Component
{
    /** Summary of the last aggregation (counts, days, warnings). */
    public ?array $resumoAgregacao = null;

    /** Day currently in focus in the aggregated items view. */
    public string $diaSelecionado = '';

    // ----- Report by period -----
    /** 'dia' | 'semana' */
    public string $modoRelatorio = 'dia';

    /** Reference date of the report (YYYY-MM-DD). */
    public string $dataRelatorio = '';

    /** Output language for the report write-up (defaults to the project's own). */
    public string $idiomaRelatorio = '';

    /** Report generated in this session (to display). @var array<string,mixed>|null */
    public ?array $relatorio = null;

    public ?string $relatorioGuardado = null;

    /** Path (in the vault) of the archived report currently in view. */
    public string $relatorioSelecionado = '';

    /** Generation in progress in the queue (controls polling). */
    public bool $aGerar = false;

    public ?string $relatorioToken = null;

    public ?string $avisoRelatorio = null;

    /** Collection (aggregation) in progress in the queue. */
    public bool $aAgregar = false;

    public ?string $agregacaoToken = null;

    /** Platforms with configured channels, available to aggregate. @var array<int,string> */
    public array $plataformasDisponiveis = [];

    /** Platforms selected for the next aggregation (defaults to all available). @var array<int,string> */
    public array $plataformasSelecionadas = [];

    public function mount(VaultContract $vault, SettingsRepository $definicoes): void
    {
        $canais = (array) $definicoes->get('canais', []);
        $this->plataformasDisponiveis = array_values(array_filter(
            array_keys($canais),
            fn ($p) => array_filter((array) ($canais[$p] ?? [])) !== [],
        ));
        $this->plataformasSelecionadas = $this->plataformasDisponiveis;

        // A reload mid-run must not show an idle "Aggregate now" button: rejoin
        // the running collection and keep polling it.
        $this->juntarAoQueCorre();

        $this->dataRelatorio = now()->toDateString();
        $this->idiomaRelatorio = ProjectLanguage::name(); // still switchable in the form

        // Opens on the most recent already-archived report (if any).
        $ultimo = $this->notaUltimoRelatorio($vault->all('noticias'));
        $this->relatorioSelecionado = $ultimo?->path ?? '';
        $this->relatorio = $this->dadosDe($ultimo);
    }

    /** When choosing another report in the selector, loads it from the vault for viewing. */
    public function updatedRelatorioSelecionado(VaultContract $vault): void
    {
        $nota = $this->relatorioSelecionado !== '' ? $vault->get($this->relatorioSelecionado) : null;

        $this->relatorio = $nota && $nota->get('tipo') === 'relatorio'
            ? $this->dadosDe($nota)
            : null;
    }

    /**
     * Runs the multi-platform aggregation and focuses on the most recent day.
     *
     * Runs in a QUEUE (worker): the collection makes dozens of yt-dlp calls and
     * would blow the max_execution_time in the web request. The page polls the result.
     */
    public function agregarAgora(): void
    {
        if ($this->aAgregar || $this->plataformasSelecionadas === []) {
            return;
        }

        $token = (string) Str::uuid();

        // One aggregation per project at a time. If another tab (or an earlier
        // visit to this page) already started one, join that run instead of
        // stacking a second yt-dlp storm onto the same vault.
        if (! AgregarConteudoJob::reservar($token)) {
            $this->juntarAoQueCorre();
            $this->dispatch('toast', message: 'A collection is already running — waiting for it to finish.');

            return;
        }

        $this->agregacaoToken = $token;
        $this->aAgregar = true;
        $this->dispatch('loader-show', message: 'Scanning the channels…');

        AgregarConteudoJob::dispatch($token, array_values($this->plataformasSelecionadas));

        $this->verificarAgregacao();
    }

    /** Picks up an aggregation already in flight, so the page polls it instead of starting another. */
    private function juntarAoQueCorre(): void
    {
        if ($token = AgregarConteudoJob::emCurso()) {
            $this->agregacaoToken = $token;
            $this->aAgregar = true;
            $this->dispatch('loader-show', message: 'Scanning the channels…');
        }
    }

    /** Toggles a platform in/out of the aggregation selection. */
    public function alternarPlataforma(string $plataforma): void
    {
        $this->plataformasSelecionadas = in_array($plataforma, $this->plataformasSelecionadas, true)
            ? array_values(array_diff($this->plataformasSelecionadas, [$plataforma]))
            : [...$this->plataformasSelecionadas, $plataforma];
    }

    /** Polled (wire:poll) while $aAgregar: shows the summary when the worker finishes. */
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
        // The summary is left in cache (it expires on its own): a second tab
        // watching the same run still needs to read it.

        if (! empty($resumo['erro'])) {
            $this->avisoRelatorio = 'The collection failed. Check that the worker is running («php artisan queue:work»).';

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
     * Triggers report generation in a QUEUE (worker) and starts polling the
     * result — writing with AI/web search takes time and would blow the
     * max_execution_time if it ran in the web request. Same pattern as the workshop.
     */
    public function criarRelatorio(): void
    {
        $this->relatorioToken = (string) Str::uuid();
        $this->aGerar = true;
        $this->avisoRelatorio = null;
        $this->dispatch('loader-show', message: 'Collecting and writing the report…');

        GerarRelatorioJob::dispatch(
            $this->modoRelatorio,
            $this->dataRelatorio,
            $this->relatorioToken,
            $this->idiomaRelatorio,
        );

        $this->verificarRelatorio(app(VaultContract::class));
    }

    /** Polled (wire:poll) while $aGerar: loads the report when the worker finishes. */
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
            $this->avisoRelatorio = 'The generation failed. Check that the worker is running («php artisan queue:work») and try again.';

            return;
        }

        $nota = $vault->get($r['path']);
        $this->relatorio = $this->dadosDe($nota);
        $this->relatorioGuardado = $r['path'];
        // Switches the view to the just-archived report.
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

        // The report in view is the public property $relatorio (shared
        // automatically with the view by Livewire); here we only list the
        // archived ones for the selector.
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

    /** All archived report notes, from the most recent to the oldest. */
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
     * Options for the previous-reports selector: path + readable label.
     *
     * @return array<int,array{path:string,rotulo:string}>
     */
    private function relatoriosPassados(Collection $notas): array
    {
        return $this->notasRelatorios($notas)
            ->map(fn (VaultNote $n) => ['path' => $n->path, 'rotulo' => $this->rotuloRelatorio($n)])
            ->all();
    }

    /** Short label for the selector, e.g. «Dia · 22 jul 2026 · 12 item(s)». */
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
