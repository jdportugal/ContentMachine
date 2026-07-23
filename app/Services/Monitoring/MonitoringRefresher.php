<?php

namespace App\Services\Monitoring;

/**
 * Encaminha a recolha de cada rede para a fonte certa: YouTube via yt-dlp
 * (grátis, local); Instagram/TikTok/LinkedIn via actores Apify. Ambos escrevem
 * no mesmo MonitoringStore, pelo que o driver lê tudo de forma uniforme.
 */
class MonitoringRefresher
{
    public function __construct(
        private readonly YtDlpMonitoringFetcher $ytdlp,
        private readonly ApifyMonitoringFetcher $apify,
    ) {}

    /**
     * @return array<int,array<string,mixed>> itens recolhidos (pode ser vazio)
     */
    public function atualizar(string $plataforma, string $channelUrl, ?int $limite = null): array
    {
        $limite ??= (int) config('contentmachine.monitoring.limite', 12);

        return $plataforma === 'youtube'
            ? $this->ytdlp->atualizar($plataforma, $channelUrl, $limite)
            : $this->apify->atualizar($plataforma, $channelUrl, $limite);
    }

    /** Se a rede tem fonte de recolha configurada (YouTube sempre; outras via Apify). */
    public function disponivel(string $plataforma): bool
    {
        return $plataforma === 'youtube' || $this->apify->disponivel($plataforma);
    }

    /** Nome da fonte, para mensagens. */
    public function fonte(string $plataforma): string
    {
        return $plataforma === 'youtube' ? 'yt-dlp' : 'Apify';
    }
}
