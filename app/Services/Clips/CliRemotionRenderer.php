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
        if (! empty($props['scenes'])) {
            // Stage any local image file referenced anywhere in a layer's params
            // (image-reveal.src, bar.image, timeline item.image, …) into public/.
            $isImage = static fn (string $s): bool => (bool) preg_match('#\.(png|jpe?g|gif|webp|bmp)$#i', $s);
            $stage = function (&$node) use (&$stage, &$staged, $isImage) {
                if (is_array($node)) {
                    foreach ($node as &$v) {
                        $stage($v);
                    }
                    unset($v);
                } elseif (is_string($node) && $isImage($node) && ! preg_match('#^https?://#', $node) && is_file($node)) {
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
            $process->setTimeout(600);
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
