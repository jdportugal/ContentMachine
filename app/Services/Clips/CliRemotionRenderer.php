<?php

namespace App\Services\Clips;

use App\Services\Clips\Contracts\RemotionRenderer;
use RuntimeException;
use Symfony\Component\Process\Process;

class CliRemotionRenderer implements RemotionRenderer
{
    public function render(array $props, string $outPath, string $entry = 'src/index.ts', string $composition = 'ClipComposition'): string
    {
        @mkdir(dirname($outPath), 0777, true);

        // Remotion loads local assets via staticFile() from its public/ folder, not
        // filesystem paths or file:// URLs. Stage the audio + any images there and
        // reference them by filename.
        $staged = [];
        if (! empty($props['audioSrc']) && ! preg_match('#^https?://#', $props['audioSrc'])) {
            $file = $this->stageAsset($props['audioSrc']);
            $staged[] = $file;
            $props['audioSrc'] = basename($file);
        }
        // Background music track — staged into public/ like the narration audio.
        if (! empty($props['musicSrc']) && ! preg_match('#^https?://#', $props['musicSrc']) && is_file($props['musicSrc'])) {
            $file = $this->stageAsset($props['musicSrc']);
            $staged[] = $file;
            $props['musicSrc'] = basename($file);
        }
        // Source video (overlay clips) — copied into public/ so it's bundled by Remotion.
        if (! empty($props['videoSrc']) && ! preg_match('#^https?://#', $props['videoSrc']) && is_file($props['videoSrc'])) {
            $file = $this->stageAsset($props['videoSrc']);
            $staged[] = $file;
            $props['videoSrc'] = basename($file);
        }
        // Custom video background — staged into public/ like the source video.
        if (! empty($props['background']['src']) && ! preg_match('#^https?://#', $props['background']['src']) && is_file($props['background']['src'])) {
            $file = $this->stageAsset($props['background']['src']);
            $staged[] = $file;
            $props['background']['src'] = basename($file);
        }
        // Backgrounds reel — stage each video entry's file into public/. NB: use a
        // distinct loop var — $entry is this method's Remotion entry-point parameter.
        if (! empty($props['entries']) && is_array($props['entries'])) {
            foreach ($props['entries'] as &$reelEntry) {
                if (! empty($reelEntry['src']) && ! preg_match('#^https?://#', $reelEntry['src']) && is_file($reelEntry['src'])) {
                    $file = $this->stageAsset($reelEntry['src']);
                    $staged[] = $file;
                    $reelEntry['src'] = basename($file);
                }
            }
            unset($reelEntry);
        }
        if (! empty($props['scenes'])) {
            // Stage any local image OR audio file referenced anywhere in a layer
            // (image-reveal.src, bar.image, timeline item.image, layer.audioSrc, …).
            $isAsset = static fn (string $s): bool => (bool) preg_match('#\.(png|jpe?g|gif|webp|bmp|mp3|wav|m4a|aac|ogg|mp4|mov|webm|m4v)$#i', $s);
            $stage = function (&$node) use (&$stage, &$staged, $isAsset) {
                if (is_array($node)) {
                    foreach ($node as &$v) {
                        $stage($v);
                    }
                    unset($v);
                } elseif (is_string($node) && $isAsset($node) && ! preg_match('#^https?://#', $node) && is_file($node)) {
                    $file = $this->stageAsset($node);
                    $staged[] = $file;
                    $node = basename($file);
                }
            };
            foreach ($props['scenes'] as &$scene) {
                if (! empty($scene['layers']) && is_array($scene['layers'])) {
                    $stage($scene['layers']);
                }
            }
            unset($scene);
        }

        $propsFile = tempnam(sys_get_temp_dir(), 'clip_props_').'.json';
        file_put_contents($propsFile, json_encode($props, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        try {
            $process = new Process(
                $this->buildRenderArgs($props, $outPath, $propsFile, $entry, $composition),
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

    /** Stage a local asset into Remotion's public/ dir; returns the staged path. Large files are symlinked. */
    private function stageAsset(string $sourcePath, bool $symlink = false): string
    {
        $publicDir = rtrim(config('contentmachine.clips.remotion_path'), '/').'/public';
        @mkdir($publicDir, 0777, true);

        $name = 'clip-asset-'.md5($sourcePath.microtime()).'.'.pathinfo($sourcePath, PATHINFO_EXTENSION);
        $dest = "$publicDir/$name";

        if ($symlink && @symlink($sourcePath, $dest)) {
            return $dest;
        }
        if (! @copy($sourcePath, $dest)) {
            throw new RuntimeException("Could not prepare the file for render: {$sourcePath}");
        }

        return $dest;
    }

    /**
     * @return array<int,string> the argv for the remotion render command
     */
    public function buildRenderArgs(array $props, string $outPath, string $propsFile, string $entry = 'src/index.ts', string $composition = 'ClipComposition'): array
    {
        $args = [
            'npx', 'remotion', 'render', $entry, $composition, $outPath,
            "--props={$propsFile}",
        ];

        if (! empty($props['transparent'])) {
            $args[] = '--codec=prores';
            $args[] = '--prores-profile=4444';
        } else {
            $args[] = '--codec=h264';
        }

        return $args;
    }
}
