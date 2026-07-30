<?php

namespace App\Services\Clips\Contracts;

interface RemotionRenderer
{
    /**
     * Render $props (plan shape) to a video at $outPath. Returns the path written.
     *
     * $entry/$composition let callers target a non-default bundle (e.g. the
     * isolated SampleEffect entry used to test-render a candidate SFX).
     */
    public function render(array $props, string $outPath, string $entry = 'src/index.ts', string $composition = 'ClipComposition'): string;
}
