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
        }

        return $outPath;
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
