<?php

namespace App\Livewire;

use App\Services\Monitoring\MonitoringAnalytics;
use App\Services\Monitoring\MonitoringManager;
use App\Services\Monitoring\MonitoringRefresher;
use App\Services\Monitoring\MonitoringStore;
use App\Services\Settings\SettingsRepository;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Monitoring')]
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
     * Collects the real channel performance for the focused network (YouTube via yt-dlp;
     * Instagram/TikTok/LinkedIn via Apify), from the profile URL in
     * Settings. Synchronous — fetches the metrics and saves them.
     */
    public function atualizar(MonitoringRefresher $refresher, SettingsRepository $settings): void
    {
        $url = (string) ($settings->get("perfis.{$this->rede}.url") ?? '');

        if (trim($url) === '') {
            $this->dispatch('toast', message: 'Set the profile URL for this network in Settings.', type: 'erro');

            return;
        }

        if (! $refresher->disponivel($this->rede)) {
            $this->dispatch('toast',
                message: 'Apify collection not configured — set APIFY_TOKEN (and the actor) in .env.',
                type: 'erro',
            );

            return;
        }

        $itens = $refresher->atualizar($this->rede, $url);
        $erro = $refresher->ultimoErro();

        // Say WHY when it failed. An empty result used to read the same whether
        // the actor errored or the profile genuinely had no posts.
        $this->dispatch('toast',
            message: match (true) {
                $erro !== null => $refresher->fonte($this->rede).' failed: '.$erro,
                $itens === [] => 'No data obtained ('.$refresher->fonte($this->rede).' returned no posts for this network).',
                default => count($itens).' posts collected.',
            },
            type: ($erro !== null || $itens === []) ? 'erro' : 'ok',
        );
    }

    /**
     * Collects every network that has a profile URL configured, in one go. Each
     * is independent — one failing network never stops the others — and the
     * toast summarises what came back per network.
     */
    public function atualizarTodas(MonitoringRefresher $refresher, SettingsRepository $settings): void
    {
        $urls = [];
        foreach ((array) config('contentmachine.monitoring.plataformas', []) as $p) {
            $urls[$p] = (string) ($settings->get("perfis.{$p}.url") ?? '');
        }

        if (trim(implode('', $urls)) === '') {
            $this->dispatch('toast', message: 'No profile URLs set — add them in Settings.', type: 'erro');

            return;
        }

        $resultado = $refresher->atualizarTodas($urls);

        if ($resultado === []) {
            $this->dispatch('toast', message: 'Nothing to collect.', type: 'erro');

            return;
        }

        $partes = [];
        foreach ($resultado as $plataforma => $r) {
            $partes[] = $r['ok'] ? "{$plataforma} {$r['count']}" : "{$plataforma} failed";
        }
        $falhas = collect($resultado)->filter(fn ($r) => ! $r['ok']);

        $this->dispatch('toast',
            message: implode(' · ', $partes).($falhas->isNotEmpty() ? ' — '.$falhas->first()['error'] : ''),
            type: $falhas->count() === count($resultado) ? 'erro' : 'ok',
        );
    }

    public function render(MonitoringManager $monitoring, MonitoringStore $store, MonitoringRefresher $refresher, SettingsRepository $settings)
    {
        $plataformas = $monitoring->plataformas();

        if (! in_array($this->rede, $plataformas, true)) {
            $this->rede = $plataformas[0] ?? 'youtube';
        }

        $driver = $monitoring->driver($this->rede);

        // Analytics over the whole collected set: day/month charts + per-type averages.
        $analytics = new MonitoringAnalytics;
        $conteudos = $driver->conteudosRecentes(500);
        $subscribers = (int) ($store->canal($this->rede)['subscribers'] ?? 0);

        return view('livewire.monitorizacao', [
            'plataformas' => $plataformas,
            'meta' => config('contentmachine.plataformas_meta.'.$this->rede),
            'resumo' => $driver->resumo(),
            'ultimoPorTipo' => $driver->ultimoPorTipo(),
            'melhores' => $driver->melhores(5),
            'recentes' => $driver->conteudosRecentes(12),
            'serieDia' => $analytics->serie($conteudos, 'dia'),
            'serieMes' => $analytics->serie($conteudos, 'mes'),
            'mediasPorTipo' => $analytics->mediasPorTipo($conteudos, $subscribers),
            'subscribers' => $subscribers,
            // Manual collection context (real-data mode).
            'recolheReal' => in_array(config('contentmachine.monitoring.driver'), ['ytdlp', 'real'], true),
            'fonte' => $refresher->fonte($this->rede),
            'fonteDisponivel' => $refresher->disponivel($this->rede),
            // Instagram hides likes/views from users without a session.
            'semMetricas' => in_array($this->rede, (array) config('contentmachine.monitoring.sem_metricas', []), true),
            'atualizadoEm' => $store->atualizadoEm($this->rede),
            'perfilUrl' => (string) ($settings->get("perfis.{$this->rede}.url") ?? ''),
        ]);
    }
}
