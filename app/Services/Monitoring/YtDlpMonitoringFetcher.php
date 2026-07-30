<?php

namespace App\Services\Monitoring;

use App\Services\Aggregation\YtDlpRunnerContract;
use App\Services\Scoring\EngagementScorer;
use Throwable;

/**
 * Collects the user channel's REAL performance via yt-dlp (without API
 * keys). Reads the profile URL from Settings, lists the recent posts and
 * enriches each one with metrics (views, likes, comments, date).
 * Normalizes to the domain format, scores and stores in the MonitoringStore.
 *
 * yt-dlp reads YouTube reliably; Instagram/TikTok may require login and
 * return empty — in that case the platform is «no data» (never false).
 */
class YtDlpMonitoringFetcher
{
    /** How many entries to list (light, metadata-only) to count posts. */
    private const LISTAGEM_MAX = 200;

    public function __construct(
        private readonly YtDlpRunnerContract $runner,
        private readonly EngagementScorer $scorer,
        private readonly MonitoringStore $store,
    ) {}

    /**
     * Collects, scores and stores. Returns the items (may be empty).
     *
     * @return array<int,array<string,mixed>>
     */
    public function atualizar(string $plataforma, string $channelUrl, int $limite = 12): array
    {
        [$itens, $canal] = $this->recolher($plataforma, $channelUrl, $limite);
        $this->store->guardar($plataforma, $itens, $canal);

        return $itens;
    }

    /**
     * @return array{0:array<int,array<string,mixed>>,1:array<string,mixed>} [itens, canal]
     */
    private function recolher(string $plataforma, string $channelUrl, int $limite): array
    {
        if (trim($channelUrl) === '') {
            return [[], []];
        }

        // A light listing per URL (up to LISTAGEM_MAX): serves to COUNT the total
        // number of posts and to capture the channel stats (subscribers,
        // name) that yt-dlp returns at the top — at no extra cost.
        $entries = [];
        $canal = ['subscribers' => 0, 'posts' => 0, 'nome' => ''];
        foreach ($this->listingUrls($plataforma, $channelUrl) as $listUrl) {
            try {
                $listing = $this->runner->listing($listUrl, self::LISTAGEM_MAX);
            } catch (Throwable) {
                continue;
            }

            if ($canal['nome'] === '' && ! empty($listing['channel'])) {
                $canal['nome'] = (string) $listing['channel'];
            }
            $canal['subscribers'] = max($canal['subscribers'], (int) ($listing['channel_follower_count'] ?? 0));

            foreach (($listing['entries'] ?? []) as $entry) {
                $id = (string) ($entry['id'] ?? $this->urlDe($entry) ?? '');
                if ($id !== '' && ! isset($entries[$id])) {
                    $entries[$id] = $entry;
                }
            }
        }
        $canal['posts'] = count($entries);

        // Only the most recent `limite` get full metadata (slow step).
        $itens = [];
        foreach (array_slice(array_values($entries), 0, $limite) as $entry) {
            $url = $this->urlDe($entry);
            if ($url === null) {
                continue;
            }

            try {
                $meta = $this->runner->metadata($url);
            } catch (Throwable) {
                $meta = [];
            }

            // Subscribers also come in the video metadata (safety net).
            $canal['subscribers'] = max($canal['subscribers'], (int) ($meta['channel_follower_count'] ?? 0));

            $item = $this->normalizar(array_merge($entry, $meta), $plataforma, $url);
            if ($item !== null) {
                $itens[] = $item;
            }
        }

        // Local copy of the thumbnails (CDNs may block hotlinking in the browser).
        $thumbs = app(ThumbnailCache::class);
        foreach ($itens as &$it) {
            $it['thumbnail'] = $thumbs->localizar($plataforma, (string) $it['id'], (string) ($it['thumbnail'] ?? ''));
        }
        unset($it);

        return [$this->pontuar($plataforma, $itens), $canal];
    }

    /**
     * URLs to list. A YouTube channel URL (root) lists the TABS, not
     * the videos — so we point explicitly to /videos and /shorts. If the
     * URL already targets a tab/playlist/video, it is used as-is.
     *
     * @return array<int,string>
     */
    private function listingUrls(string $plataforma, string $channelUrl): array
    {
        $url = rtrim(trim($channelUrl), '/');

        if ($plataforma === 'youtube'
            && ! preg_match('#/(videos|shorts|streams|playlist|watch)#i', $url)
            && ! str_contains($url, 'watch?v=')) {
            return [$url.'/videos', $url.'/shorts'];
        }

        return [$url];
    }

    private function urlDe(array $entry): ?string
    {
        foreach (['webpage_url', 'url', 'original_url'] as $k) {
            if (! empty($entry[$k]) && is_string($entry[$k])) {
                return $entry[$k];
            }
        }

        return null;
    }

    /** @return array<string,mixed>|null */
    private function normalizar(array $m, string $plataforma, string $url): ?array
    {
        $id = (string) ($m['id'] ?? md5($url));
        $titulo = trim((string) ($m['title'] ?? $m['fulltitle'] ?? ''));
        if ($titulo === '') {
            return null;
        }

        $duracao = (int) ($m['duration'] ?? 0);

        return [
            'id' => $id,
            'plataforma' => $plataforma,
            'tipo' => $this->tipo($plataforma, $duracao, $url, $m),
            'titulo' => $titulo,
            'url' => (string) ($m['webpage_url'] ?? $url),
            'thumbnail' => (string) ($m['thumbnail'] ?? ''),
            'publicado_em' => $this->data($m),
            'duracao_seg' => $duracao,
            'views' => (int) ($m['view_count'] ?? 0),
            'likes' => (int) ($m['like_count'] ?? 0),
            'comentarios' => (int) ($m['comment_count'] ?? 0),
            'partilhas' => (int) ($m['repost_count'] ?? 0),
            'guardados' => 0, // not exposed by yt-dlp
        ];
    }

    private function tipo(string $plataforma, int $duracao, string $url, array $m): string
    {
        if ($plataforma === 'youtube') {
            return (str_contains($url, '/shorts/') || ($duracao > 0 && $duracao <= 60)) ? 'short' : 'vídeo';
        }
        if ($plataforma === 'instagram') {
            if (($m['_type'] ?? '') === 'playlist' || str_contains($url, '/p/')) {
                return 'carrossel';
            }

            return str_contains($url, '/reel') ? 'reel' : 'post';
        }

        return 'vídeo';
    }

    private function data(array $m): string
    {
        // yt-dlp: upload_date = YYYYMMDD; timestamp = epoch.
        $d = (string) ($m['upload_date'] ?? '');
        if (preg_match('/^\d{8}$/', $d)) {
            return substr($d, 0, 4).'-'.substr($d, 4, 2).'-'.substr($d, 6, 2);
        }
        if (! empty($m['timestamp']) && is_numeric($m['timestamp'])) {
            return date('Y-m-d', (int) $m['timestamp']);
        }

        return now()->toDateString();
    }

    /**
     * Scores each item (weighted performance index) against the median of views.
     *
     * @param  array<int,array<string,mixed>>  $itens
     * @return array<int,array<string,mixed>>
     */
    private function pontuar(string $plataforma, array $itens): array
    {
        if ($itens === []) {
            return [];
        }

        $mediana = $this->scorer->mediana(array_column($itens, 'views'));

        return array_map(fn ($i) => array_merge($i, $this->scorer->score($plataforma, $i, $mediana)), $itens);
    }
}
