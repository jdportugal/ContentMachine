<?php

namespace App\Services\Monitoring;

use App\Services\Scoring\EngagementScorer;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Throwable;

/**
 * Recolhe o desempenho REAL das redes não-YouTube (Instagram, TikTok, LinkedIn)
 * via actores Apify, a partir do URL do perfil em Definições. Normaliza para o
 * formato do domínio, pontua e guarda no MonitoringStore (mesmo formato que o
 * driver de yt-dlp). Sem token/actor, ou em falha, devolve vazio (nunca falso).
 */
class ApifyMonitoringFetcher
{
    public function __construct(
        private readonly ApifyClient $apify,
        private readonly EngagementScorer $scorer,
        private readonly MonitoringStore $store,
    ) {}

    /**
     * @return array<int,array<string,mixed>>
     */
    public function atualizar(string $plataforma, string $channelUrl, int $limite = 12): array
    {
        [$itens, $canal] = $this->recolher($plataforma, $channelUrl, $limite);
        $this->store->guardar($plataforma, $itens, $canal);

        return $itens;
    }

    /** Se há actor + token configurados para esta rede. */
    public function disponivel(string $plataforma): bool
    {
        return filled(config("contentmachine.monitoring.apify.{$plataforma}"))
            && filled(config('services.apify.token'));
    }

    /**
     * @return array{0:array<int,array<string,mixed>>,1:array<string,mixed>} [itens, canal]
     */
    private function recolher(string $plataforma, string $channelUrl, int $limite): array
    {
        $actor = (string) config("contentmachine.monitoring.apify.{$plataforma}");
        if ($actor === '' || trim($channelUrl) === '') {
            return [[], []];
        }

        try {
            $raw = $this->apify->runActor($actor, $this->input($plataforma, $channelUrl, $limite));
        } catch (Throwable) {
            return [[], []];
        }

        $itens = [];
        $seguidores = 0;
        foreach ($raw as $r) {
            if (! is_array($r)) {
                continue;
            }
            $seguidores = max($seguidores, $this->seguidores($plataforma, $r));
            $item = $this->mapear($plataforma, $r);
            if ($item !== null) {
                $itens[] = $item;
            }
        }

        $canal = ['subscribers' => $seguidores, 'posts' => count($itens), 'nome' => ''];

        // Guarda uma cópia local das miniaturas (os CDN bloqueiam o hotlink).
        $thumbs = app(ThumbnailCache::class);
        foreach ($itens as &$it) {
            $it['thumbnail'] = $thumbs->localizar($plataforma, (string) $it['id'], (string) ($it['thumbnail'] ?? ''));
        }
        unset($it);

        return [$this->pontuar($plataforma, $itens), $canal];
    }

    /** @return array<string,mixed> input do actor por plataforma */
    private function input(string $plataforma, string $url, int $limite): array
    {
        return match ($plataforma) {
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

    /** @return array<string,mixed>|null item normalizado */
    private function mapear(string $plataforma, array $r): ?array
    {
        return match ($plataforma) {
            'instagram' => $this->mapearInstagram($r),
            'tiktok' => $this->mapearTiktok($r),
            'linkedin' => $this->mapearLinkedin($r),
            default => null,
        };
    }

    private function mapearInstagram(array $r): ?array
    {
        $url = (string) ($r['url'] ?? $r['inputUrl'] ?? '');
        $id = (string) ($r['id'] ?? $r['shortCode'] ?? ($url !== '' ? md5($url) : ''));
        if ($id === '') {
            return null;
        }

        $tipo = match (strtolower((string) ($r['type'] ?? ''))) {
            'video' => 'reel',
            'sidecar' => 'carrossel',
            default => 'post',
        };

        return [
            'id' => $id,
            'plataforma' => 'instagram',
            'tipo' => $tipo,
            'titulo' => $this->titulo($r['caption'] ?? null, 'Publicação'),
            'url' => $url,
            'thumbnail' => (string) ($r['displayUrl'] ?? ''),
            'publicado_em' => $this->data($r['timestamp'] ?? null),
            'duracao_seg' => (int) ($r['videoDuration'] ?? 0),
            'views' => (int) ($r['videoViewCount'] ?? $r['videoPlayCount'] ?? 0),
            'likes' => (int) ($r['likesCount'] ?? 0),
            'comentarios' => (int) ($r['commentsCount'] ?? 0),
            'partilhas' => 0,
            'guardados' => 0,
        ];
    }

    private function mapearTiktok(array $r): ?array
    {
        $url = (string) ($r['webVideoUrl'] ?? $r['url'] ?? '');
        $id = (string) ($r['id'] ?? ($url !== '' ? md5($url) : ''));
        if ($id === '') {
            return null;
        }

        return [
            'id' => $id,
            'plataforma' => 'tiktok',
            'tipo' => 'vídeo',
            'titulo' => $this->titulo($r['text'] ?? null, 'Vídeo'),
            'url' => $url,
            'thumbnail' => (string) (Arr::get($r, 'videoMeta.coverUrl') ?? Arr::get($r, 'videoMeta.originalCoverUrl') ?? ($r['covers'][0] ?? '')),
            'publicado_em' => $this->data($r['createTimeISO'] ?? $r['createTime'] ?? null),
            'duracao_seg' => (int) Arr::get($r, 'videoMeta.duration', 0),
            'views' => (int) ($r['playCount'] ?? 0),
            'likes' => (int) ($r['diggCount'] ?? 0),
            'comentarios' => (int) ($r['commentCount'] ?? 0),
            'partilhas' => (int) ($r['shareCount'] ?? 0),
            'guardados' => (int) ($r['collectCount'] ?? 0),
        ];
    }

    private function mapearLinkedin(array $r): ?array
    {
        $url = (string) ($r['url'] ?? $r['postUrl'] ?? '');
        $id = (string) ($r['id'] ?? $r['urn'] ?? ($url !== '' ? md5($url) : ''));
        if ($id === '') {
            return null;
        }

        return [
            'id' => $id,
            'plataforma' => 'linkedin',
            'tipo' => 'post',
            'titulo' => $this->titulo($r['text'] ?? $r['commentary'] ?? null, 'Publicação'),
            'url' => $url,
            'publicado_em' => $this->data($r['postedAtISO'] ?? $r['date'] ?? null),
            'duracao_seg' => 0,
            'views' => (int) ($r['numViews'] ?? $r['impressions'] ?? 0),
            'likes' => (int) ($r['numLikes'] ?? $r['likesCount'] ?? 0),
            'comentarios' => (int) ($r['numComments'] ?? $r['commentsCount'] ?? 0),
            'partilhas' => (int) ($r['numShares'] ?? $r['sharesCount'] ?? 0),
            'guardados' => 0,
        ];
    }

    private function seguidores(string $plataforma, array $r): int
    {
        return match ($plataforma) {
            'instagram' => (int) ($r['ownerFollowersCount'] ?? Arr::get($r, 'owner.followersCount', 0)),
            'tiktok' => (int) Arr::get($r, 'authorMeta.fans', 0),
            'linkedin' => (int) ($r['authorFollowersCount'] ?? Arr::get($r, 'author.followers', 0)),
            default => 0,
        };
    }

    private function titulo(mixed $texto, string $fallback): string
    {
        $texto = trim((string) ($texto ?? ''));
        if ($texto === '') {
            return $fallback;
        }
        $primeira = trim((string) (preg_split('/\r\n|\r|\n/', $texto)[0] ?? $texto));

        return Str::limit($primeira !== '' ? $primeira : $texto, 80);
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

    /**
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
