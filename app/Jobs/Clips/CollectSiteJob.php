<?php

namespace App\Jobs\Clips;

use App\Jobs\Concerns\RunsInProject;
use App\Services\Capture\SiteCapture;
use App\Services\Capture\SiteResolver;
use App\Services\Clips\Store\ClipStore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Films the website behind one image suggestion (the planner set `params.site`)
 * and pins the resulting video to that suggestion, exactly as if the user had
 * uploaded it. Runs queued from the "collect now" button on the image-review
 * screen, and synchronously from FinalizeClipPlanJob for anything not clicked.
 * A failure only records the error on the suggestion — the clip goes on without
 * the footage (the layer is dropped like any other src-less image).
 */
class CollectSiteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RunsInProject;

    public int $timeout = 900;

    /** ponytail: fixed capture length; scenes loop/cut the video as needed. */
    public const SECONDS = 6.0;

    public function __construct(public string $projectId, public string $key)
    {
        $this->captureProject();
    }

    public function handle(ClipStore $store, SiteResolver $resolver, SiteCapture $capture): void
    {
        $this->activateProject();
        $p = $store->find($this->projectId);
        if (! $p) {
            return;
        }

        $req = collect($p->meta['image_requests'] ?? [])->firstWhere('key', $this->key);
        $site = is_array($req) ? (string) ($req['site'] ?? '') : '';

        try {
            $url = $site !== '' ? $resolver->validar($site) : null;
            if ($url === null) {
                throw new RuntimeException("The page did not respond: {$site}");
            }

            $c = config('contentmachine.clips');
            $src = $capture->capture($url, (int) $c['width'], (int) $c['height'], self::SECONDS);

            // Store on the clips disk like an upload, so previews/renders find it.
            $disk = Storage::disk($c['disk']);
            $path = 'clips/uploads/site-'.$this->key.'.mp4';
            $disk->put($path, fopen($src, 'r'));

            $entry = [
                'id' => 'img_'.substr(md5($path), 0, 8),
                'path' => $path,
                'description' => 'Website: '.$url,
                'transparent' => false,
                'tone' => 'mixed',
                'video' => true,
            ];

            $uploads = $p->meta['image_uploads'] ?? [];
            $previous = $uploads[$this->key] ?? null;
            $images = [];
            foreach ($p->images ?? [] as $img) {
                if (($img['id'] ?? null) === $previous || ($img['id'] ?? null) === $entry['id']) {
                    if (! empty($img['path']) && $img['path'] !== $path) {
                        $disk->delete($img['path']); // a replaced upload's file
                    }

                    continue;
                }
                $images[] = $img;
            }
            $images[] = $entry;
            $uploads[$this->key] = $entry['id'];

            $p->update([
                'images' => $images,
                'meta' => array_merge($p->meta ?? [], [
                    'image_uploads' => $uploads,
                    'image_text' => array_diff_key($p->meta['image_text'] ?? [], [$this->key => true]),
                    'site_collecting' => array_diff_key($p->meta['site_collecting'] ?? [], [$this->key => true]),
                    'site_errors' => array_diff_key($p->meta['site_errors'] ?? [], [$this->key => true]),
                ]),
            ]);
        } catch (\Throwable $e) {
            $p->update(['meta' => array_merge($p->meta ?? [], [
                'site_collecting' => array_diff_key($p->meta['site_collecting'] ?? [], [$this->key => true]),
                'site_errors' => array_merge($p->meta['site_errors'] ?? [], [$this->key => Str::limit($e->getMessage(), 200)]),
            ])]);
        }
    }
}
