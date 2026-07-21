<?php

namespace App\Livewire;

use App\Services\Aggregation\NewsAggregator;
use App\Services\News\NewsManager;
use App\Services\Vault\VaultContract;
use App\Services\Vault\VaultNote;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Agregador de Notícias')]
class Noticias extends Component
{
    /** @var array<int,string> */
    public array $fontes = [];

    public ?string $guardado = null;

    /** Resumo da última agregação (contagens, dias, avisos). */
    public ?array $resumoAgregacao = null;

    /** Dia actualmente em foco na vista de itens agregados. */
    public string $diaSelecionado = '';

    public function mount(): void
    {
        $this->fontes = config('contentmachine.news.fontes', []);
    }

    public function guardarNoVault(NewsManager $news, VaultContract $vault): void
    {
        $relatorio = $news->relatorio($this->fontes);

        $corpo = "## Resumo\n\n{$relatorio['resumo']}\n\n## Destaques\n\n"
            .collect($relatorio['destaques'])->map(fn ($d) => "- **[{$d['fonte']}]** {$d['titulo']} — *{$d['angulo']}* (relevância {$d['relevancia']})")->implode("\n")
            ."\n\n## Ideias de guião\n\n"
            .collect($relatorio['ideias_guiao'])->map(fn ($i) => "- {$i}")->implode("\n");

        $nota = $vault->create('noticias', [
            'titulo' => $relatorio['titulo'],
            'tipo' => 'relatorio',
            'fontes' => $this->fontes,
            'estado' => 'arquivado',
            'tags' => ['noticias', 'relatorio'],
        ], $corpo);

        $this->guardado = $nota->title();
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

    public function render(NewsManager $news, VaultContract $vault)
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

        return view('livewire.noticias', [
            'fontesDisponiveis' => $news->fontes(),
            'relatorio' => $news->relatorio($this->fontes ?: $news->fontes()),
            'dias' => $dias,
            'diaAtivo' => $dia,
            'itensDoDia' => $itensDoDia,
            'topicosHtml' => $topicosDoDia?->html(),
        ]);
    }

    private function notaTopicos(Collection $notas, string $dia): ?VaultNote
    {
        return $notas->first(fn (VaultNote $n) => $n->get('tipo') === 'topicos' && (string) $n->get('data') === $dia);
    }
}
