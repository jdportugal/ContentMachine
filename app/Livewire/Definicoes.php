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

    public ?string $guardado = null;

    public function mount(SettingsRepository $definicoes): void
    {
        $tudo = $definicoes->all();

        $this->geral = $tudo['geral'];
        $this->perfis = $tudo['perfis'];
        $this->fontes = collect($tudo['agregador'])
            ->map(fn (array $lista) => implode("\n", $lista))
            ->all();
    }

    public function guardar(SettingsRepository $definicoes): void
    {
        $agregador = collect($this->fontes)
            ->map(fn (string $texto) => collect(preg_split('/\r\n|\r|\n/', $texto))
                ->map(fn ($l) => trim($l))
                ->filter()
                ->values()
                ->all())
            ->all();

        $definicoes->save([
            'geral' => $this->geral,
            'perfis' => $this->perfis,
            'agregador' => $agregador,
        ]);

        $this->guardado = now()->translatedFormat('H:i');
    }

    public function render()
    {
        return view('livewire.definicoes', [
            'plataformasMeta' => config('contentmachine.plataformas_meta'),
        ]);
    }
}
