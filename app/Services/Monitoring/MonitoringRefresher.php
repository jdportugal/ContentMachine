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
        private readonly MonitoringStats $stats,
        private readonly MonitoringHistory $history,
    ) {}

    /**
     * @return array<int,array<string,mixed>> collected items (may be empty)
     */
    public function atualizar(string $plataforma, string $channelUrl, ?int $limite = null): array
    {
        $limite ??= (int) config('contentmachine.monitoring.limite', 12);

        if ($plataforma !== 'youtube') {
            return $this->apify->atualizar($plataforma, $channelUrl, $limite);
        }

        // YouTube's bot check can leave yt-dlp with nothing at all. Apify reaches
        // the channel another way — use it rather than showing an empty dashboard.
        $itens = $this->ytdlp->atualizar($plataforma, $channelUrl, $limite);

        return $itens === [] && $this->apify->disponivel('youtube')
            ? $this->apify->atualizar($plataforma, $channelUrl, $limite)
            : $itens;
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

    /** Why the last collection came back empty, or null when it genuinely was. */
    public function ultimoErro(): ?string
    {
        return $this->apify->ultimoErro();
    }

    /**
     * Collect every network that has both a profile URL and a working source, in
     * one pass. Each network is independent: one failing never stops the rest.
     *
     * @param  array<string,string>  $urls  platform => profile URL
     * @return array<string,array{ok:bool,count:int,error:?string}> per platform
     */
    public function atualizarTodas(array $urls, ?int $limite = null): array
    {
        $resultado = [];

        foreach ($urls as $plataforma => $url) {
            if (trim((string) $url) === '') {
                continue; // no profile configured — not a failure, just nothing to do
            }
            if (! $this->disponivel($plataforma)) {
                $resultado[$plataforma] = ['ok' => false, 'count' => 0, 'error' => 'no collection source configured'];

                continue;
            }

            $itens = $this->atualizar($plataforma, $url, $limite);
            $erro = $this->ultimoErro();

            $resultado[$plataforma] = [
                'ok' => $erro === null,
                'count' => count($itens),
                'error' => $erro,
            ];
        }

        // The store only ever holds the CURRENT numbers, so a day's totals are
        // gone the moment the next collection overwrites them. Record them here,
        // after the pass, while they still describe a whole day — every route into
        // a collection (the nightly command, the Monitoring tab's button) ends up
        // in this method. Never at the cost of the collection itself.
        if ($resultado !== []) {
            try {
                $this->history->registar($this->stats->totais(array_keys($resultado)));
            } catch (\Throwable) {
                // a missed day of history is not worth failing a collection over
            }
        }

        return $resultado;
    }
}
