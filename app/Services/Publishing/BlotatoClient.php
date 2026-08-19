<?php

namespace App\Services\Publishing;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Thin wrapper over the Blotato publishing API (https://backend.blotato.com).
 *
 * Auth is a single account-wide key sent as the `blotato-api-key` header. Media
 * that is already a public http(s) URL is passed straight through; local files
 * are uploaded via Blotato's presigned-upload flow first. Scheduling is handled
 * by Blotato itself (scheduledTime / useNextFreeSlot), so we don't run a local
 * scheduler.
 */
class BlotatoClient
{
    public function __construct(
        private readonly ?string $key = null,
        private readonly string $baseUrl = 'https://backend.blotato.com',
    ) {}

    private function http(): PendingRequest
    {
        $key = $this->key ?: (string) config('services.blotato.key');
        if ($key === '') {
            throw new RuntimeException('Blotato API key is not configured (Settings → API keys).');
        }

        return Http::baseUrl($this->baseUrl)
            ->withHeaders(['blotato-api-key' => $key])
            ->acceptJson()
            ->timeout(120);
    }

    /**
     * Returns a public URL Blotato can fetch. `http(s)` inputs pass through; a
     * local file path is uploaded via the presigned flow and its permanent
     * publicUrl is returned.
     */
    public function uploadMedia(string $pathOrUrl): string
    {
        if (Str::startsWith($pathOrUrl, ['http://', 'https://'])) {
            return $pathOrUrl;
        }

        if (! is_file($pathOrUrl)) {
            throw new RuntimeException("Media file not found: {$pathOrUrl}");
        }

        // 1. Ask for a presigned upload slot.
        $res = $this->http()
            ->post('/v2/media/uploads', ['filename' => basename($pathOrUrl)])
            ->throw()
            ->json();

        $presigned = $res['presignedUrl'] ?? null;
        $public = $res['publicUrl'] ?? null;
        if (! $presigned || ! $public) {
            throw new RuntimeException('Blotato presigned upload response missing URLs.');
        }

        // 2. PUT the bytes to the presigned URL (expires quickly — do it now).
        Http::withBody(file_get_contents($pathOrUrl), $this->mime($pathOrUrl))
            ->timeout(300)
            ->put($presigned)
            ->throw();

        // 3. The public URL is the permanent, reusable media URL.
        return $public;
    }

    /**
     * Publishes (or schedules) one post to one platform target. Returns Blotato's
     * decoded response (contains the created post id/status).
     *
     * @param  array<int,string>  $mediaUrls  public URLs from uploadMedia()
     * @param  string|null  $scheduledTime  ISO-8601 with offset; null = immediate/slot
     * @param  string|null  $title  platform title (YouTube); defaults to the caption's first line
     * @return array<string,mixed>
     */
    public function publish(
        string $accountId,
        string $targetType,
        string $text,
        array $mediaUrls = [],
        ?string $scheduledTime = null,
        bool $useNextFreeSlot = false,
        ?string $title = null,
    ): array {
        $body = [
            'post' => [
                'accountId' => $accountId,
                'content' => [
                    'text' => $text,
                    'mediaUrls' => array_values($mediaUrls),
                    'platform' => $targetType,
                ],
                'target' => array_merge(['targetType' => $targetType], $this->targetExtras($targetType, $title ?: $text)),
            ],
        ];

        if ($scheduledTime !== null) {
            $body['scheduledTime'] = $scheduledTime;
        } elseif ($useNextFreeSlot) {
            $body['useNextFreeSlot'] = true;
        }

        return $this->http()->post('/v2/posts', $body)->throw()->json() ?? [];
    }

    /**
     * Platform-specific required target fields. Sensible fixed defaults for v1.
     *
     * ponytail: hardcoded publishing defaults (public visibility, comments on).
     * Add per-post overrides in the UI if a platform's options need to vary.
     */
    private function targetExtras(string $targetType, string $title): array
    {
        return match ($targetType) {
            'youtube' => [
                'title' => Str::limit(strtok($title, "\n") ?: $title, 95, ''),
                'privacyStatus' => 'public',
                'shouldNotifySubscribers' => false,
            ],
            'tiktok' => [
                'privacyLevel' => 'PUBLIC_TO_EVERYONE',
                'disabledComments' => false,
                'disabledDuet' => false,
                'disabledStitch' => false,
                'isBrandContent' => false,
                'isYourBrand' => false,
                'isAiGenerated' => true,
            ],
            default => [], // instagram, linkedin, threads: targetType only
        };
    }

    private function mime(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'mp4', 'mov', 'm4v' => 'video/mp4',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'image/jpeg',
        };
    }
}
