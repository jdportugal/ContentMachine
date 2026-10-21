<?php

namespace App\Services\Clips;

use App\Services\Clips\Contracts\RemotionRenderer;
use App\Services\Shorts\LocalVideoEngine;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Symfony\Component\Process\Process;

class CliRemotionRenderer implements RemotionRenderer
{
    /**
     * Run $work while no other render is running anywhere. remotion/src/effects and
     * backgrounds are one folder for every project: two renders of different
     * projects at once (two queue workers) had one bundling the OTHER project's
     * effects — an IAD clip's ending came out in the Nuno Narciso project's colours.
     */
    public static function exclusively(callable $work): mixed
    {
        return Cache::lock('remotion-folder', 3600)->block(3600, $work);
    }

    public function render(array $props, string $outPath, string $entry = 'src/index.ts', string $composition = 'ClipComposition'): string
    {
        // The folder is filled with THIS project's effects and backgrounds, and rendered, in one go.
        return self::exclusively(function () use ($props, $outPath, $entry, $composition) {
            app(EffectLibrary::class)->syncFilesystem();
            app(BackgroundLibrary::class)->syncFilesystem();

            return $this->renderNow($props, $outPath, $entry, $composition);
        });
    }

    private function renderNow(array $props, string $outPath, string $entry, string $composition): string
    {
        @mkdir(dirname($outPath), 0777, true);

        [$props, $staged] = $this->stageProps($props);

        $propsFile = tempnam(sys_get_temp_dir(), 'clip_props_').'.json';
        file_put_contents($propsFile, json_encode($props, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        try {
            $process = new Process(
                $this->buildRenderArgs($props, $outPath, $propsFile, $entry, $composition, $this->concurrency()),
                config('contentmachine.clips.remotion_path')
            );
            // Full clips (many scenes, ~1-2k frames) can outrun 10 min; keep this
            // below the queue retry_after (1800) so the worker fails cleanly if it
            // ever truly hangs, but give real renders room.
            $process->setTimeout((float) config('contentmachine.clips.render_timeout', 1500));
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException('Remotion failed: '.$process->getErrorOutput());
            }
        } finally {
            @unlink($propsFile);
            foreach ($staged as $file) {
                @unlink($file);
            }
        }

        return $outPath;
    }

    /**
     * Copy every local asset the props reference into Remotion's public/ and
     * rewrite the reference to its basename — Remotion resolves staticFile()
     * from there, not from filesystem paths.
     *
     * Public so the rewriting can be tested without running a render.
     *
     * @return array{0:array<string,mixed>,1:array<int,string>} [props, staged files]
     */
    public function stageProps(array $props): array
    {
        $staged = [];
        // The frame this render composes at. A staged video bigger than this is
        // downscaled to it — see stageAsset.
        $frame = [(int) ($props['width'] ?? 0), (int) ($props['height'] ?? 0)];
        if (! empty($props['audioSrc']) && ! preg_match('#^https?://#', $props['audioSrc'])) {
            $file = $this->stageAsset($props['audioSrc'], false, $frame);
            $staged[] = $file;
            $props['audioSrc'] = basename($file);
        }
        // Background music track — staged into public/ like the narration audio.
        if (! empty($props['musicSrc']) && ! preg_match('#^https?://#', $props['musicSrc']) && is_file($props['musicSrc'])) {
            $file = $this->stageAsset($props['musicSrc'], false, $frame);
            $staged[] = $file;
            $props['musicSrc'] = basename($file);
        }
        // Source video (overlay clips) — copied into public/ so it's bundled by Remotion.
        if (! empty($props['videoSrc']) && ! preg_match('#^https?://#', $props['videoSrc']) && is_file($props['videoSrc'])) {
            $file = $this->stageAsset($props['videoSrc'], false, $frame);
            $staged[] = $file;
            $props['videoSrc'] = basename($file);
        }
        // Custom video background — staged into public/ like the source video.
        if (! empty($props['background']['src']) && ! preg_match('#^https?://#', $props['background']['src']) && is_file($props['background']['src'])) {
            $file = $this->stageAsset($props['background']['src'], false, $frame);
            $staged[] = $file;
            $props['background']['src'] = basename($file);
        }
        // Backgrounds reel — stage each video entry's file into public/. NB: use a
        // distinct loop var — $entry is this method's Remotion entry-point parameter.
        if (! empty($props['entries']) && is_array($props['entries'])) {
            foreach ($props['entries'] as &$reelEntry) {
                if (! empty($reelEntry['src']) && ! preg_match('#^https?://#', $reelEntry['src']) && is_file($reelEntry['src'])) {
                    $file = $this->stageAsset($reelEntry['src'], false, $frame);
                    $staged[] = $file;
                    $reelEntry['src'] = basename($file);
                }
            }
            unset($reelEntry);
        }
        // Stage any local image / audio / video referenced anywhere in a layer
        // (image-reveal.src, bar.image, timeline item.image, layer.audioSrc, …)
        // or in a single effect's params (the SampleEffect path used by the VFX
        // Lab and the SFX test-render, where a site capture arrives as params.src).
        $isAsset = static fn (string $s): bool => (bool) preg_match('#\.(png|jpe?g|gif|webp|bmp|mp3|wav|m4a|aac|ogg|mp4|mov|webm|m4v)$#i', $s);
        $stage = function (&$node) use (&$stage, &$staged, $isAsset, $frame) {
            if (is_array($node)) {
                foreach ($node as &$v) {
                    $stage($v);
                }
                unset($v);
            } elseif (is_string($node) && $isAsset($node) && ! preg_match('#^https?://#', $node) && is_file($node)) {
                $file = $this->stageAsset($node, false, $frame);
                $staged[] = $file;
                $node = basename($file);
            }
        };

        if (! empty($props['scenes'])) {
            foreach ($props['scenes'] as &$scene) {
                if (! empty($scene['layers']) && is_array($scene['layers'])) {
                    $stage($scene['layers']);
                }
            }
            unset($scene);
        }
        if (! empty($props['params']) && is_array($props['params'])) {
            $stage($props['params']);
        }
        // A one-effect plan (EffectLibrary::samplePlan — e.g. the property reel's agent
        // outro) carries its params under animations: their photos and logos too.
        if (! empty($props['animations']) && is_array($props['animations'])) {
            $stage($props['animations']);
        }

        return [$props, $staged];
    }

    /**
     * Stage a local asset into Remotion's public/ dir; returns the staged path.
     * Large files are symlinked.
     *
     * @param  array{0:int,1:int}|null  $frame  the composition size; a video larger
     *                                          than it is staged downscaled to fit
     */
    private function stageAsset(string $sourcePath, bool $symlink = false, ?array $frame = null): string
    {
        $publicDir = rtrim(config('contentmachine.clips.remotion_path'), '/').'/public';
        @mkdir($publicDir, 0777, true);

        $name = 'clip-asset-'.md5($sourcePath.microtime()).'.'.pathinfo($sourcePath, PATHINFO_EXTENSION);
        $dest = "$publicDir/$name";

        if ($frame !== null && $this->reduzir($sourcePath, $dest, $frame)) {
            return $dest;
        }
        if ($symlink && @symlink($sourcePath, $dest)) {
            return $dest;
        }
        if (! @copy($sourcePath, $dest)) {
            throw new RuntimeException("Could not prepare the file for render: {$sourcePath}");
        }

        return $dest;
    }

    /**
     * Stage a video downscaled to the composition's frame, if it is bigger.
     *
     * OffthreadVideo decodes EVERY frame at the source's own resolution and hands it
     * to Chrome as a bitmap over its proxy — a 3456x2234 source is ~31 MB per frame,
     * which Chrome refuses outright (net::ERR_FAILED, then EPIPE as the compositor
     * dies). All of it was going to be thrown away at the render size anyway, so a
     * source larger than the frame is pure cost. The same clip that failed on frame
     * one at 3456x2234 rendered clean at 1080x698.
     *
     * Returns false when it does not apply (not a video, not bigger, no ffmpeg) so
     * the caller falls back to a plain copy — never fatal, worst case the old path.
     *
     * @param  array{0:int,1:int}  $frame
     */
    private function reduzir(string $source, string $dest, array $frame): bool
    {
        [$fw, $fh] = $frame;
        if ($fw < 1 || $fh < 1 || ! preg_match('#\.(mp4|mov|webm|m4v)$#i', $source)) {
            return false;
        }

        try {
            $meta = app(LocalVideoEngine::class)->probe($source);
        } catch (\Throwable) {
            return false; // unreadable — let the plain copy try
        }
        [$w, $h] = [(int) ($meta['width'] ?? 0), (int) ($meta['height'] ?? 0)];
        if ($w < 1 || $h < 1 || ($w <= $fw && $h <= $fh)) {
            return false;
        }

        // Fit inside the frame, keeping the aspect ratio; even dimensions for h264.
        $escala = min($fw / $w, $fh / $h);
        $nw = max(2, (int) round($w * $escala / 2) * 2);
        $nh = max(2, (int) round($h * $escala / 2) * 2);

        $p = new Process([
            config('services.shorts.ffmpeg', 'ffmpeg'), '-y',
            '-i', $source,
            '-vf', "scale={$nw}:{$nh}",
            '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '20', '-pix_fmt', 'yuv420p',
            '-c:a', 'copy', '-movflags', '+faststart',
            $dest,
        ]);
        $p->setTimeout(600);
        $p->run();

        return $p->isSuccessful() && is_file($dest) && filesize($dest) > 0;
    }

    /**
     * @return array<int,string> the argv for the remotion render command
     */
    /**
     * How many frames Remotion paints in parallel.
     *
     * Left unset, Remotion's own default behaved like 1 here — a 900-frame clip
     * took 125s wall for 146s of CPU, i.e. one core busy while the rest idled.
     * Passing the core count took the same render to 67s on 2 cores and 49s on 4,
     * for the same total CPU. The work was never the problem; the parallelism was.
     *
     * Each frame in flight is another headless Chrome tab, so this is also the
     * memory dial — cap it with CLIPS_RENDER_CONCURRENCY on a box that is tighter
     * on RAM than on cores.
     */
    private function concurrency(): int
    {
        $configured = (int) config('contentmachine.clips.render_concurrency', 0);

        return $configured > 0 ? $configured : $this->cores();
    }

    /** Cores available to this host, 2 if it will not say. Container-free on purpose. */
    private function cores(): int
    {
        $n = (int) trim((string) (@shell_exec('nproc 2>/dev/null')
            ?: @shell_exec('sysctl -n hw.ncpu 2>/dev/null')));

        return max(1, $n ?: 2);
    }

    public function buildRenderArgs(array $props, string $outPath, string $propsFile, string $entry = 'src/index.ts', string $composition = 'ClipComposition', ?int $concurrency = null): array
    {
        $args = [
            'npx', 'remotion', 'render', $entry, $composition, $outPath,
            "--props={$propsFile}",
            '--concurrency='.($concurrency ?: $this->cores()),
        ];

        // Only part of the composition (RenderJob re-rendering just the ending).
        if (isset($props['frameRange'][0], $props['frameRange'][1])) {
            $args[] = '--frames='.(int) $props['frameRange'][0].'-'.(int) $props['frameRange'][1];
        }

        if (! empty($props['transparent'])) {
            // Alpha survives only with ALL four: the 4444 profile carries the
            // channel, PNG frames preserve it through the pipeline, and
            // yuva444p10le is the only pixel format here that encodes it.
            // Drop any one and the output is silently opaque.
            $args[] = '--codec=prores';
            $args[] = '--prores-profile=4444';
            $args[] = '--image-format=png';
            $args[] = '--pixel-format=yuva444p10le';
        } else {
            $args[] = '--codec=h264';
            // Opaque video has no alpha to keep, so frames go to disk as JPEG, not
            // the config's PNG: a 1080x1920 PNG is several MB, and a 40s clip's
            // ~1200 of them filled the disk mid-render (ENOSPC) with 4 GB free.
            // At quality 95 the difference is invisible once h264 re-encodes it.
            $args[] = '--image-format=jpeg';
            $args[] = '--jpeg-quality=95';
        }

        return $args;
    }
}
