<?php

namespace App\Livewire;

use App\Services\Settings\SettingsRepository;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Definições')]
class Definicoes extends Component
{
    /** @var array<string,string> */
    public array $geral = [];

    /** @var array<string,array<string,string>> */
    public array $perfis = [];

    /** Fontes do agregador como texto (uma por linha), por fonte. @var array<string,string> */
    public array $fontes = [];

    /** Canais a agregar — lista de URLs por plataforma. @var array<string,array<int,string>> */
    public array $canais = [];

    public ?string $guardado = null;

    public function mount(SettingsRepository $definicoes): void
    {
        $tudo = $definicoes->all();

        $this->geral = $tudo['geral'];
        $this->perfis = $tudo['perfis'];
        $this->fontes = collect($tudo['agregador'])
            ->map(fn (array $lista) => implode("\n", $lista))
            ->all();
        // Cada plataforma tem uma lista de URLs; garante ≥1 linha para escrever.
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
     * Converte um mapa de textos (uma entrada por linha) num mapa de listas.
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
