<?php

namespace App\Jobs\Clips;

use App\Jobs\Concerns\RunsInProject;
use App\Services\Capture\SiteCapture;
use App\Services\Capture\SiteResolver;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use App\Services\Clips\WebImageFinder;
use App\Services\Costs\CostLedger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Fetches REAL images off the web for the planner's image suggestions and pins
 * them to those suggestions, exactly as if the user had uploaded them — which
 * is the point: it is the hand-feeding of images that this removes.
 *
 * Runs queued after planning (the review screen polls and the pictures appear),
 * and for a single suggestion from the "find image" button. Anything it cannot
 * find simply stays pending and is generated later, as before.
 */
class FetchWebImagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RunsInProject;

    // A web search plus several downloads, and a screenshot drives a browser.
    public int $timeout = 900;

    /** @param  string|null  $key  one suggestion, or null for every pending one. */
    public function __construct(public string $projectId, public ?string $key = null)
    {
        $this->captureProject();
    }

    public function handle(ClipStore $store, WebImageFinder $finder, SiteResolver $resolver, SiteCapture $capture): void
    {
        $this->activateProject();
        app(CostLedger::class)->contexto('clip', $this->projectId);

        $p = $store->find($this->projectId);
        if (! $p || ! config('contentmachine.clips.fetch_images', true)) {
            $this->limparFlags($store, $this->key !== null ? [$this->key] : []);

            return;
        }

        // Every mark this job is responsible for — NOT just the ones it ends up
        // searching. The image ceiling can cut the list short, and a mark left
        // behind spins the review screen forever and blocks the continue button.
        $limpar = $this->key !== null
            ? [$this->key]
            : array_keys(array_filter($p->meta['site_collecting'] ?? [], fn ($v) => $v === 'search'));

        $pendentes = $this->pendentes($p);

        try {
            if ($pendentes === []) {
                return;
            }

            $encontrados = $finder->find(
                $pendentes,
                (string) ($p->meta['suggested']['title'] ?? $p->title ?? ''),
                (string) ($p->meta['research']['summary'] ?? Str::limit((string) ($p->transcript['text'] ?? ''), 400, '')),
            );

            foreach ($pendentes as $req) {
                $achado = $encontrados[$req['key']] ?? null;
                if (! is_array($achado)) {
                    continue;
                }

                $entry = ($achado['site'] ?? '') !== ''
                    ? $this->screenshot($resolver, $capture, (string) $achado['site'], $req)
                    : $finder->fetch((string) $achado['image'], (string) ($req['label'] ?? ''), (string) ($achado['page'] ?? ''));

                if ($entry !== null) {
                    $this->fixar($store, $req['key'], $entry);
                }
            }
        } finally {
            // Whatever happened, the review screen must stop spinning.
            $this->limparFlags($store, $limpar);
        }
    }

    /**
     * The suggestions worth searching for: still unfulfilled, not declined, not
     * a page the planner already named (CollectSiteJob films those) and not the
     * animated intro (it is a generated concept, not a real thing). Bounded by
     * image_max, counting the images the clip already carries.
     *
     * @return array<int,array<string,mixed>>
     */
    private function pendentes($p): array
    {
        $uploads = $p->meta['image_uploads'] ?? [];
        $asText = $p->meta['image_text'] ?? [];

        $out = [];
        foreach ($p->meta['image_requests'] ?? [] as $req) {
            if (! is_array($req) || ! is_string($req['key'] ?? null)) {
                continue;
            }
            $key = $req['key'];
            if ($this->key !== null && $key !== $this->key) {
                continue;
            }
            // An explicit request for ONE suggestion is the user asking again —
            // it may replace an image already pinned to it.
            if ($this->key === null && isset($uploads[$key])) {
                continue;
            }
            if (! empty($asText[$key]) || ! empty($req['site']) || ! empty($req['animado'])) {
                continue;
            }
            $out[] = $req;
        }

        $espaco = max(0, (int) config('contentmachine.clips.image_max', 8) - count($p->images ?? []));

        return array_slice($out, 0, $espaco);
    }

    /** One screenshot of a page, stored like an upload. */
    private function screenshot(SiteResolver $resolver, SiteCapture $capture, string $site, array $req): ?array
    {
        try {
            $url = $resolver->validar($site);
            if ($url === null) {
                return null;
            }

            // Landscape viewport: ImageReveal contains rather than crops, so the
            // page shows whole, letterboxed over the blurred backdrop.
            $src = $capture->still($url, 1920, 1080);

            $disk = Storage::disk(config('contentmachine.clips.disk'));
            $path = 'clips/uploads/web-'.$req['key'].'.png';
            $disk->put($path, fopen($src, 'r'));

            return [
                'id' => 'web_'.substr(md5($path), 0, 8),
                'path' => $path,
                'description' => 'Website: '.$url,
                'tone' => 'mixed',
                'transparent' => false,
                'video' => false,
                'fetched' => true,
                'source' => $url,
            ];
        } catch (\Throwable $e) {
            Log::warning('Screenshot for a clip suggestion failed: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Pin one image to its suggestion. The record is re-read every time: the
     * search takes a while, and the user may have uploaded their own meanwhile —
     * their choice wins.
     */
    private function fixar(ClipStore $store, string $key, array $entry): void
    {
        $p = $store->find($this->projectId);
        if (! $p || ! empty(($p->meta['image_text'] ?? [])[$key])) {
            return;
        }

        // The clip moved on while we were searching (the user pressed continue,
        // or it is already rendering) — the plan is settled, do not touch it.
        if ($p->status !== ClipRecord::STATUS_COLLECTING) {
            return;
        }

        $anterior = ($p->meta['image_uploads'] ?? [])[$key] ?? null;
        if ($anterior !== null && $this->key === null) {
            return; // the user pinned their own while we searched — theirs wins
        }

        $disk = Storage::disk(config('contentmachine.clips.disk'));
        $images = [];
        foreach ($p->images ?? [] as $img) {
            // Drop the image this one replaces, and the file behind it.
            if (($img['id'] ?? null) === $anterior || ($img['id'] ?? null) === $entry['id']) {
                if (! empty($img['path']) && $img['path'] !== $entry['path']) {
                    $disk->delete($img['path']);
                }

                continue;
            }
            $images[] = $img;
        }
        $images[] = $entry;

        $p->update([
            'images' => $images,
            'meta' => array_merge($p->meta ?? [], [
                'image_uploads' => array_merge($p->meta['image_uploads'] ?? [], [$key => $entry['id']]),
                'site_errors' => array_diff_key($p->meta['site_errors'] ?? [], [$key => true]),
            ]),
        ]);
    }

    /** @param  array<int,string>  $chaves */
    private function limparFlags(ClipStore $store, array $chaves): void
    {
        if ($chaves === []) {
            return;
        }
        $p = $store->find($this->projectId);
        if (! $p) {
            return;
        }

        $p->update(['meta' => array_merge($p->meta ?? [], [
            'site_collecting' => array_diff_key($p->meta['site_collecting'] ?? [], array_fill_keys($chaves, true)),
        ])]);
    }
}
