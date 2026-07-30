<?php

namespace App\Services\Clips\Fake;

use App\Services\Clips\Contracts\RemotionRenderer;

class FakeRemotionRenderer implements RemotionRenderer
{
    public function render(array $props, string $outPath, string $entry = 'src/index.ts', string $composition = 'ClipComposition'): string
    {
        @mkdir(dirname($outPath), 0777, true);
        file_put_contents($outPath, 'FAKE-VIDEO');

        return $outPath;
    }
}
