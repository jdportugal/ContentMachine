<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Powers the "Check for updates" button on a Docker deploy.
 *
 * - version check: compares the running image's tag digest to `:latest` on GHCR
 *   (anonymous — the package is public), so no Docker access is needed for it.
 * - update: asks the Watchtower sidecar (reachable over the compose network) to
 *   pull `:latest` and recreate the app container. Watchtower runs the recreation
 *   from OUTSIDE the app container, which a container can't reliably do to itself.
 */
class UpdateService
{
    public function currentVersion(): string
    {
        return (string) config('contentmachine.update.version', 'dev');
    }

    public function shortVersion(): string
    {
        $v = $this->currentVersion();

        return $v === 'dev' ? 'dev' : substr($v, 0, 7);
    }

    /** Whether the one-click update path is wired (Watchtower configured). */
    public function updatable(): bool
    {
        return trim((string) config('contentmachine.update.watchtower_url')) !== '';
    }

    /**
     * true = a newer image is published, false = up to date, null = undetermined
     * (dev build, private/unreachable registry, or a transient error).
     */
    public function updateAvailable(): ?bool
    {
        $version = $this->currentVersion();
        $repo = $this->repository();
        if ($repo === null || $version === 'dev' || $version === '') {
            return null;
        }

        $latest = $this->digest($repo, 'latest');
        $mine = $this->digest($repo, $version);
        if ($latest === null || $mine === null) {
            return null;
        }

        return $latest !== $mine;
    }

    /** Ask Watchtower to pull `:latest` and recreate the app. Returns false if not wired. */
    public function triggerUpdate(): bool
    {
        $url = trim((string) config('contentmachine.update.watchtower_url'));
        $token = (string) config('contentmachine.update.watchtower_token');
        if ($url === '') {
            return false;
        }

        // Short timeout: Watchtower recreates THIS container, so the response often
        // never comes back — firing the request is enough. Swallow the expected drop.
        try {
            Http::withToken($token)->timeout(5)->post(rtrim($url, '/').'/v1/update');
        } catch (\Throwable) {
            // connection reset as the container is recreated — that's success, not failure
        }

        return true;
    }

    /** `ghcr.io/owner/name` (or `owner/name`) → `owner/name`; null if not a GHCR image. */
    private function repository(): ?string
    {
        $image = (string) config('contentmachine.update.image');
        $image = preg_replace('#^ghcr\.io/#', '', $image);
        $image = preg_replace('#:[^/]+$#', '', (string) $image); // strip any :tag

        return ($image !== '' && str_contains($image, '/')) ? $image : null;
    }

    /** The content digest GHCR reports for a tag, or null. Anonymous (public package). */
    private function digest(string $repo, string $tag): ?string
    {
        try {
            $token = Http::get('https://ghcr.io/token', [
                'service' => 'ghcr.io',
                'scope' => "repository:{$repo}:pull",
            ])->json('token');
            if (! $token) {
                return null;
            }

            $resp = Http::withToken($token)
                ->withHeaders(['Accept' => 'application/vnd.oci.image.index.v1+json, application/vnd.docker.distribution.manifest.list.v2+json, application/vnd.docker.distribution.manifest.v2+json'])
                ->timeout(10)
                ->get("https://ghcr.io/v2/{$repo}/manifests/{$tag}");

            return $resp->successful() ? ($resp->header('Docker-Content-Digest') ?: null) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
