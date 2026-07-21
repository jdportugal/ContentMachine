<?php

namespace App\Services\Clips;

use App\Services\Clips\Contracts\RemotionRenderer;
use RuntimeException;
use Symfony\Component\Process\Process;

class CliRemotionRenderer implements RemotionRenderer
{
    public function render(array $props, string $outPath): string
    {
        @mkdir(dirname($outPath), 0777, true);

        // Remotion loads local assets via staticFile() from its public/ folder, not
        // filesystem paths or file:// URLs. Stage the audio there and reference it by name.
        $staged = null;
        if (! empty($props['audioSrc']) && ! preg_match('#^https?://#', $props['audioSrc'])) {
            $staged = $this->stageAsset($props['audioSrc']);
            $props['audioSrc'] = basename($staged);
        }

        $propsFile = tempnam(sys_get_temp_dir(), 'clip_props_').'.json';
        file_put_contents($propsFile, json_encode($props, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        try {
            $process = new Process(
                $this->buildRenderArgs($props, $outPath, $propsFile),
                config('contentmachine.clips.remotion_path')
            );
            $process->setTimeout(600);
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException('Remotion falhou: '.$process->getErrorOutput());
            }
        } finally {
            @unlink($propsFile);
            if ($staged) {
                @unlink($staged);
            }
        }

        return $outPath;
    }

    /** Copy a local asset into Remotion's public/ dir; returns the staged absolute path. */
    private function stageAsset(string $sourcePath): string
    {
        $publicDir = rtrim(config('contentmachine.clips.remotion_path'), '/').'/public';
        @mkdir($publicDir, 0777, true);

        $name = 'clip-asset-'.md5($sourcePath.microtime()).'.'.pathinfo($sourcePath, PATHINFO_EXTENSION);
        $dest = "$publicDir/$name";

        if (! @copy($sourcePath, $dest)) {
            throw new RuntimeException("Não foi possível preparar o áudio para render: {$sourcePath}");
        }

        return $dest;
    }

    /**
     * @return array<int,string> the argv for the remotion render command
     */
    public function buildRenderArgs(array $props, string $outPath, string $propsFile): array
    {
        $args = [
            'npx', 'remotion', 'render', 'src/index.ts', 'ClipComposition', $outPath,
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

