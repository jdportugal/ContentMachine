<?php

namespace App\Services\Clips\Contracts;

interface RemotionRenderer
{
    /**
     * Render $props (plan shape) to a video at $outPath. Returns the path written.
     */
    public function render(array $props, string $outPath): string;
}
