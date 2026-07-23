<?php

namespace App\Livewire;

use App\Services\Monitoring\MonitoringManager;
use App\Services\Monitoring\MonitoringStats;
use App\Services\News\NewsManager;
use App\Services\Vault\VaultContract;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Painel')]
class Painel extends Component
{
    public function render(MonitoringManager $monitoring, MonitoringStats $stats, NewsManager $news, VaultContract $vault)
    {
        // Resumo de desempenho por plataforma (melhor conteúdo recente).
        $plataformas = $monitoring->todos()->map(function ($driver, $p) {
            $melhores = $driver->melhores(1);

            return [
                'plataforma' => $p,
                'melhor' => $melhores[0] ?? null,
                'resumo' => $driver->resumo()[1] ?? $driver->resumo()[0] ?? null,
            ];
        })->values();

        $relatorio = $news->relatorio();

        return view('livewire.painel', [
            'plataformas' => $plataformas,
            // Totais de canal (subscritores, publicações, desempenho) das redes.
            'estatisticas' => $stats->totais($monitoring->plataformas()),
            'destaquesNoticias' => array_slice($relatorio['destaques'], 0, 3),
        ]);
    }
}
