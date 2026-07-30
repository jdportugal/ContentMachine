<?php

namespace App\Services\Aggregation;

use App\Services\Monitoring\ApifyClient;
use App\Services\Monitoring\ThumbnailCache;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Throwable;

/**
 * Aggregation driver for the networks yt-dlp cannot collect (Instagram, TikTok,
 * LinkedIn), via the same Apify actors the monitoring already uses. Maps each
 * post to an AggregatedItem, using the CAPTION as the textual content (there is
 * no spoken transcript) so topics/summaries downstream work unchanged.
 * Degrades to [] when the actor/token is missing or the run fails — never throws.
 */
class ApifyDriver implements AggregatorDriver
{
    public function __construct(
        private readonly ApifyClient $apify,
        private readonly string $plataforma,
    ) {}

    public function plataforma(): string
    {
        return $this->plataforma;
    }

    /** Whether an actor + token are configured for this network. */
    public function disponivel(): bool
    {
        return filled(config("contentmachine.monitoring.apify.{$this->plataforma}"))
            && filled(config('services.apify.token'));
    }

    public function collect(array $canais, int $limitePorCanal, array $idsArquivados = []): array
    {
        if (! $this->disponivel()) {
            return [];
        }

        $actor = (string) config("contentmachine.monitoring.apify.{$this->plataforma}");
        $thumbs = app(ThumbnailCache::class);
        $itens = [];

        foreach ($canais as $canal) {
            $canal = trim($canal);
            if ($canal === '') {
                continue;
            }

            try {
                $raw = $this->apify->runActor($actor, $this->input($canal, $limitePorCanal));
            } catch (Throwable) {
                continue;
            }

            foreach ($raw as $r) {
                if (! is_array($r)) {
                    continue;
                }
                $item = $this->mapear($r);
                if ($item === null) {
                    continue;
                }
                // Already in the vault — skip the thumbnail fetch + re-archive.
                if (isset($idsArquivados[$item->id])) {
                    continue;
                }
                // Cache the thumbnail locally (IG/TikTok CDNs block hotlinking).
                $thumb = $thumbs->localizar($this->plataforma, $item->id, $item->thumbnail);
                $itens[] = $thumb === $item->thumbnail ? $item : $this->comThumbnail($item, $thumb);
            }
        }

        return $itens;
    }

    /** @return array<string,mixed> actor input per platform */
    private function input(string $url, int $limite): array
    {
        return match ($this->plataforma) {
            'instagram' => [
                'directUrls' => [$url],
                'resultsType' => 'posts',
                'resultsLimit' => $limite,
                'addParentData' => true,
            ],
            'tiktok' => [
                'profiles' => [$this->tiktokHandle($url)],
                'resultsPerPage' => $limite,
                'shouldDownloadVideos' => false,
                'shouldDownloadCovers' => false,
            ],
            'linkedin' => [
                'urls' => [$url],
                'limit' => $limite,
            ],
            default => ['urls' => [$url], 'limit' => $limite],
        };
    }

    /** @param array<string,mixed> $r */
    private function mapear(array $r): ?AggregatedItem
    {
        return match ($this->plataforma) {
            'instagram' => $this->item($r, (string) ($r['url'] ?? $r['inputUrl'] ?? ''), (string) ($r['id'] ?? $r['shortCode'] ?? ''), (string) ($r['caption'] ?? ''), (string) ($r['ownerUsername'] ?? $r['ownerFullName'] ?? ''), (string) ($r['displayUrl'] ?? ''), $r['timestamp'] ?? null),
            'tiktok' => $this->item($r, (string) ($r['webVideoUrl'] ?? $r['url'] ?? ''), (string) ($r['id'] ?? ''), (string) ($r['text'] ?? ''), (string) (Arr::get($r, 'authorMeta.name') ?? Arr::get($r, 'authorMeta.nickName') ?? ''), (string) (Arr::get($r, 'videoMeta.coverUrl') ?? Arr::get($r, 'videoMeta.originalCoverUrl') ?? ($r['covers'][0] ?? '')), $r['createTimeISO'] ?? $r['createTime'] ?? null),
            'linkedin' => $this->item($r, (string) ($r['url'] ?? $r['postUrl'] ?? ''), (string) ($r['id'] ?? $r['urn'] ?? ''), (string) ($r['text'] ?? $r['commentary'] ?? ''), (string) ($r['authorName'] ?? Arr::get($r, 'author.name') ?? ''), '', $r['postedAtISO'] ?? $r['date'] ?? null),
            default => null,
        };
    }

    /** @param array<string,mixed> $r */
    private function item(array $r, string $url, string $rawId, string $caption, string $canal, string $thumbnail, mixed $data): ?AggregatedItem
    {
        $id = $rawId !== '' ? $rawId : ($url !== '' ? md5($url) : '');
        if ($id === '') {
            return null;
        }

        $caption = trim($caption);

        return new AggregatedItem(
            id: Str::slug($id) !== '' ? Str::slug($id) : md5($id),
            plataforma: $this->plataforma,
            titulo: $this->titulo($caption),
            canal: $canal,
            data: $this->data($data),
            url: $url,
            thumbnail: $thumbnail,
            descricao: $caption,
            // No spoken transcript on these networks — the caption is the content
            // the topics/summary pipeline reads.
            transcricao: $caption,
            tags: $this->hashtags($caption),
            fontes: $this->fontes($caption),
        );
    }

    private function titulo(string $caption): string
    {
        if ($caption === '') {
            return 'Publicação';
        }
        $primeira = trim((string) (preg_split('/\r\n|\r|\n/', $caption)[0] ?? $caption));

        return Str::limit($primeira !== '' ? $primeira : $caption, 80);
    }

    /** @return array<int,string> */
    private function hashtags(string $texto): array
    {
        if (! preg_match_all('/#([\p{L}0-9_]+)/u', $texto, $m)) {
            return [];
        }

        return array_values(array_slice(array_unique(array_map('strtolower', $m[1])), 0, 15));
    }

    /** @return array<int,string> */
    private function fontes(string $texto): array
    {
        if (! preg_match_all('#https?://[^\s<>"\')]+#i', $texto, $m)) {
            return [];
        }

        $limpas = array_map(fn (string $u) => rtrim($u, '.,);'), $m[0]);

        return array_values(array_slice(array_unique($limpas), 0, 15));
    }

    private function data(mixed $valor): string
    {
        if (is_numeric($valor)) {
            return date('Y-m-d', (int) $valor);
        }
        $s = trim((string) ($valor ?? ''));
        if ($s !== '' && ($ts = strtotime($s)) !== false) {
            return date('Y-m-d', $ts);
        }

        return now()->toDateString();
    }

    private function tiktokHandle(string $url): string
    {
        if (preg_match('#@([A-Za-z0-9._]+)#', $url, $m)) {
            return $m[1];
        }

        return trim($url);
    }

    private function comThumbnail(AggregatedItem $i, string $thumbnail): AggregatedItem
    {
        return new AggregatedItem(
            id: $i->id, plataforma: $i->plataforma, titulo: $i->titulo, canal: $i->canal,
            data: $i->data, url: $i->url, thumbnail: $thumbnail, descricao: $i->descricao,
            transcricao: $i->transcricao, tags: $i->tags, fontes: $i->fontes,
        );
    }
}
