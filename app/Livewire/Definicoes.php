<?php

namespace App\Livewire;

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

    public ?string $guardado = null;

    public function mount(SettingsRepository $definicoes): void
    {
        $tudo = $definicoes->all();

        $this->geral = $tudo['geral'];
        $this->perfis = $tudo['perfis'];
        $this->shorts = $tudo['shorts'];
        $this->fontes = collect($tudo['agregador'])
            ->map(fn (array $lista) => implode("\n", $lista))
            ->all();
        // Each platform has a list of URLs; ensure ≥1 line to write into.
        $this->canais = collect($tudo['canais'])
            ->map(fn (array $lista) => $lista === [] ? [''] : array_values($lista))
            ->all();
    }

    public function guardar(SettingsRepository $definicoes): void
    {
        $definicoes->save([
            'geral' => $this->geral,
            'perfis' => $this->perfis,
            'agregador' => $this->emListas($this->fontes),
            'canais' => collect($this->canais)
                ->map(fn (array $lista) => array_values(array_filter(array_map('trim', $lista))))
                ->all(),
            'shorts' => $this->shorts,
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
