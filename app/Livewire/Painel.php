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
        //
        // O Painel é a página de entrada (/), por isso NUNCA deve rebentar: os
        // drivers reais ('api') lançam quando faltam credenciais ou quando o
        // endpoint externo falha. Aqui degradamos com elegância — sem dados em
        // vez de um erro 500 — e reportamos a exceção para observabilidade.
        $plataformas = $monitoring->todos()->map(function ($driver, $p) {
            try {
                $resumo = $driver->resumo();

                return [
                    'plataforma' => $p,
                    'melhor' => $driver->melhores(1)[0] ?? null,
                    'resumo' => $resumo[1] ?? $resumo[0] ?? null,
                ];
            } catch (\Throwable $e) {
                report($e);

                return ['plataforma' => $p, 'melhor' => null, 'resumo' => null];
            }
        })->values();

        try {
            $estatisticas = $stats->totais($monitoring->plataformas());
        } catch (\Throwable $e) {
            report($e);
            $estatisticas = ['subscritores' => 0, 'publicacoes' => 0, 'visualizacoes' => 0, 'interacoes' => 0, 'redes' => 0, 'temDados' => false];
        }

        try {
            $destaquesNoticias = array_slice($news->relatorio()['destaques'] ?? [], 0, 3);
        } catch (\Throwable $e) {
            report($e);
            $destaquesNoticias = [];
        }

        return view('livewire.painel', [
            'plataformas' => $plataformas,
            // Totais de canal (subscritores, publicações, desempenho) das redes.
            'estatisticas' => $estatisticas,
            'destaquesNoticias' => $destaquesNoticias,
        ]);
    }
}
