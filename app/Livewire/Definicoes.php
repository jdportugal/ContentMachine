<?php

namespace App\Livewire;

use App\Services\Projects\ProjectContext;
use App\Services\Projects\ProjectRepository;
use App\Services\Settings\SettingsRepository;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Settings')]
class Definicoes extends Component
{
    /** @var array<string,string> */
    public array $geral = [];

    /** @var array<string,array<string,string>> */
    public array $perfis = [];

    /** Aggregator sources as text (one per line), per source. @var array<string,string> */
    public array $fontes = [];

    /** Channels to aggregate — list of URLs per platform. @var array<string,array<int,string>> */
    public array $canais = [];

    /** @var array<string,string> */
    public array $shorts = [];

    /** API keys (local). @var array<string,string> */
    public array $chaves = [];

    /** Service/model config. @var array<string,string> */
    public array $modelos = [];

    /** Blotato connected-account ids per platform. @var array<string,string> */
    public array $blotato = [];

    /** Active project's language (stored in the project registry, not the vault). */
    public string $idioma = 'en';

    /** Active settings tab: geral | fontes | social | motor | chaves. */
    public string $secao = 'geral';

    public ?string $guardado = null;

    public function mount(SettingsRepository $definicoes, ProjectContext $projeto): void
    {
        $tudo = $definicoes->all();

        $this->idioma = $projeto->current()->language ?: 'en';
        $this->geral = $tudo['geral'];
        $this->perfis = $tudo['perfis'];
        $this->shorts = $tudo['shorts'];
        $this->chaves = $tudo['chaves'];
        $this->modelos = $tudo['modelos'];
        $this->blotato = $tudo['blotato'];
        $this->fontes = collect($tudo['agregador'])
            ->map(fn (array $lista) => implode("\n", $lista))
            ->all();
        // Each platform has a list of URLs; ensure ≥1 line to write into.
        $this->canais = collect($tudo['canais'])
            ->map(fn (array $lista) => $lista === [] ? [''] : array_values($lista))
            ->all();
    }

    public function guardar(SettingsRepository $definicoes, ProjectContext $projeto, ProjectRepository $projetos): void
    {
        // Language lives in the project registry (not the vault) — update it there.
        $idioma = in_array($this->idioma, ['en', 'pt'], true) ? $this->idioma : 'en';
        $projetos->update($projeto->current()->slug, ['language' => $idioma]);
        app()->setLocale($idioma);

        $definicoes->save([
            'geral' => $this->geral,
            'perfis' => $this->perfis,
            'agregador' => $this->emListas($this->fontes),
            'canais' => collect($this->canais)
                ->map(fn (array $lista) => array_values(array_filter(array_map('trim', $lista))))
                ->all(),
            'shorts' => $this->shorts,
            'chaves' => array_map('trim', $this->chaves),
            'modelos' => array_map('trim', $this->modelos),
            'blotato' => array_map('trim', $this->blotato),
        ]);

        $this->guardado = now()->translatedFormat('H:i');
    }

    public function adicionarCanal(string $plataforma): void
    {
        $this->canais[$plataforma][] = '';
    }

    public function removerCanal(string $plataforma, int $i): void
    {
        unset($this->canais[$plataforma][$i]);
        $this->canais[$plataforma] = array_values($this->canais[$plataforma]);

        if ($this->canais[$plataforma] === []) {
            $this->canais[$plataforma] = [''];
        }
    }

    /**
     * Converts a map of texts (one entry per line) into a map of lists.
     *
     * @param  array<string,string>  $mapa
     * @return array<string,array<int,string>>
     */
    private function emListas(array $mapa): array
    {
        return collect($mapa)
            ->map(fn (string $texto) => collect(preg_split('/\r\n|\r|\n/', $texto))
                ->map(fn ($l) => trim($l))
                ->filter()
                ->values()
                ->all())
            ->all();
    }

    public function render()
    {
        return view('livewire.definicoes', [
            'plataformasMeta' => config('contentmachine.plataformas_meta'),
        ]);
    }
}
