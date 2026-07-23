<?php

namespace App\Livewire;

use App\Services\Monitoring\MonitoringManager;
use App\Services\Monitoring\MonitoringRefresher;
use App\Services\Monitoring\MonitoringStore;
use App\Services\Settings\SettingsRepository;
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

    /**
     * Recolhe o desempenho real do canal da rede em foco (YouTube via yt-dlp;
     * Instagram/TikTok/LinkedIn via Apify), a partir do URL do perfil em
     * Definições. Síncrono — apanha as métricas e guarda.
     */
    public function atualizar(MonitoringRefresher $refresher, SettingsRepository $settings): void
    {
        $url = (string) ($settings->get("perfis.{$this->rede}.url") ?? '');

        if (trim($url) === '') {
            $this->dispatch('toast', message: 'Defina o URL do perfil desta rede em Definições.', type: 'erro');

            return;
        }

        if (! $refresher->disponivel($this->rede)) {
            $this->dispatch('toast',
                message: 'Recolha por Apify não configurada — defina APIFY_TOKEN (e o actor) no .env.',
                type: 'erro',
            );

            return;
        }

        $itens = $refresher->atualizar($this->rede, $url);

        $this->dispatch('toast',
            message: $itens === []
                ? 'Sem dados obtidos ('.$refresher->fonte($this->rede).' não devolveu publicações para esta rede).'
                : count($itens).' publicações recolhidas.',
            type: $itens === [] ? 'erro' : 'ok',
        );
    }

    public function render(MonitoringManager $monitoring, MonitoringStore $store, MonitoringRefresher $refresher, SettingsRepository $settings)
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
            // Contexto da recolha manual (modo com dados reais).
            'recolheReal' => in_array(config('contentmachine.monitoring.driver'), ['ytdlp', 'real'], true),
            'fonte' => $refresher->fonte($this->rede),
            'fonteDisponivel' => $refresher->disponivel($this->rede),
            // Instagram esconde gostos/visualizações a quem não tem sessão.
            'semMetricas' => in_array($this->rede, (array) config('contentmachine.monitoring.sem_metricas', []), true),
            'atualizadoEm' => $store->atualizadoEm($this->rede),
            'perfilUrl' => (string) ($settings->get("perfis.{$this->rede}.url") ?? ''),
        ]);
    }
}
