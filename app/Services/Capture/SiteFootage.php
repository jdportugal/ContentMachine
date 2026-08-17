<?php

namespace App\Services\Capture;

use Illuminate\Support\Facades\Log;

/**
 * "Does this effect want a real website on screen, and if so, get me the video."
 *
 * The one place the resolve → capture pair lives, so the VFX Lab, the SFX studio
 * and the video editor all behave identically. A capture is a bonus: if the
 * model names no site, or the site is unreachable, or the browser fails, the
 * effect is still generated — just without the footage.
 */
class SiteFootage
{
    public function __construct(
        private readonly SiteResolver $resolver,
        private readonly SiteCapture $capture,
    ) {}

    /**
     * @return array{path:?string,url:?string,error:?string}
     */
    public function forPrompt(string $descricao, int $width, int $height, float $seconds): array
    {
        $vazio = ['path' => null, 'url' => null, 'error' => null];

        $url = $this->resolver->resolve($descricao);
        if ($url === null) {
            return $vazio;   // no site mentioned, or the guess did not respond
        }

        try {
            return [
                'path' => $this->capture->capture($url, $width, $height, $seconds),
                'url' => $url,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            // Name the URL: an AI-guessed address is the likeliest thing to be
            // wrong here, and "capture failed" alone would not say which page.
            Log::warning("Site capture failed for {$url}: ".$e->getMessage());

            return ['path' => null, 'url' => $url, 'error' => $e->getMessage()];
        }
    }
}
