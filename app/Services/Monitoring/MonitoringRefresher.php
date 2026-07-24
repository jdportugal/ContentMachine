<?php

namespace App\Services\Monitoring;

/**
 * Routes the collection of each network to the right source: YouTube via yt-dlp
 * (free, local); Instagram/TikTok/LinkedIn via Apify actors. Both write to
 * the same MonitoringStore, so the driver reads everything uniformly.
 */
class MonitoringRefresher
{
    public function __construct(
        private readonly YtDlpMonitoringFetcher $ytdlp,
        private readonly ApifyMonitoringFetcher $apify,
    ) {}

    /**
     * @return array<int,array<string,mixed>> collected items (may be empty)
     */
    public function atualizar(string $plataforma, string $channelUrl, ?int $limite = null): array
    {
        $limite ??= (int) config('contentmachine.monitoring.limite', 12);

        return $plataforma === 'youtube'
            ? $this->ytdlp->atualizar($plataforma, $channelUrl, $limite)
            : $this->apify->atualizar($plataforma, $channelUrl, $limite);
    }

    /** Whether the network has a collection source configured (YouTube always; others via Apify). */
    public function disponivel(string $plataforma): bool
    {
        return $plataforma === 'youtube' || $this->apify->disponivel($plataforma);
    }

    /** Source name, for messages. */
    public function fonte(string $plataforma): string
    {
        return $plataforma === 'youtube' ? 'yt-dlp' : 'Apify';
    }
}
