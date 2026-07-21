<?php

namespace App\Livewire;

use App\Services\Monitoring\MonitoringManager;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Monitorização')]
class Monitorizacao extends Component
{
    #[Url]
    public string $rede = 'youtube';

    public function selecionar(string $rede): void
    {
        if (in_array($rede, config('contentmachine.monitoring.plataformas', []), true)) {
            $this->rede = $rede;
        }
    }

    public function render(MonitoringManager $monitoring)
    {
        $plataformas = $monitoring->plataformas();

        if (! in_array($this->rede, $plataformas, true)) {
            $this->rede = $plataformas[0] ?? 'youtube';
        }

        $driver = $monitoring->driver($this->rede);

        return view('livewire.monitorizacao', [
            'plataformas' => $plataformas,
            'meta' => config('contentmachine.plataformas_meta.'.$this->rede),
            'resumo' => $driver->resumo(),
            'ultimoPorTipo' => $driver->ultimoPorTipo(),
            'melhores' => $driver->melhores(5),
            'recentes' => $driver->conteudosRecentes(12),
        ]);
    }
}
