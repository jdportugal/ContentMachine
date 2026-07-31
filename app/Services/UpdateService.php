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

    /**
     * Ask Watchtower to pull `:latest` and recreate the app. Returns a status the UI
     * can be honest about, instead of always claiming success:
     *   'triggered'    — Watchtower accepted it (or dropped the reply mid-recreate).
     *   'not-wired'    — no Watchtower configured (shouldn't reach here; guard in UI).
     *   'unreachable'  — Watchtower isn't running / not on the network.
     *   'unauthorized' — token mismatch between the app and the sidecar.
     *   'unsupported'  — the sidecar's HTTP API isn't enabled (--http-api-update).
     */
    public function triggerUpdate(): string
    {
        $url = trim((string) config('contentmachine.update.watchtower_url'));
        $token = (string) config('contentmachine.update.watchtower_token');
        if ($url === '') {
            return 'not-wired';
        }

        try {
            $resp = Http::withToken($token)->timeout(8)->post(rtrim($url, '/').'/v1/update');
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // Watchtower recreates THIS container, so a dropped reply mid-flight is the
            // SUCCESS path. But a refused connection / unknown host means the sidecar
            // isn't reachable — a real, reportable failure.
            $msg = strtolower($e->getMessage());
            $down = str_contains($msg, 'refused')
                || str_contains($msg, 'could not resolve')
                || str_contains($msg, 'name or service not known')
                || str_contains($msg, 'no route to host');

            return $down ? 'unreachable' : 'triggered';
        } catch (\Throwable) {
            return 'triggered';
        }

        return match (true) {
            $resp->status() === 401 || $resp->status() === 403 => 'unauthorized',
            $resp->status() === 404 => 'unsupported',
            default => 'triggered',
        };
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
