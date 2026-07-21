<?php

namespace App\Livewire;

use App\Services\News\NewsManager;
use App\Services\Vault\VaultContract;
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

    public function render(NewsManager $news)
    {
        return view('livewire.noticias', [
            'fontesDisponiveis' => $news->fontes(),
            'relatorio' => $news->relatorio($this->fontes ?: $news->fontes()),
        ]);
    }
}
