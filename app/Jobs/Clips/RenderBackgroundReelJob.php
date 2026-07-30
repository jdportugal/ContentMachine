<?php

namespace App\Jobs\Clips;

use App\Jobs\Concerns\RunsInProject;
use App\Services\Clips\BackgroundLibrary;
use App\Services\Clips\Contracts\RemotionRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;

/**
 * Renders (and caches) the backgrounds reel — one video cycling through every
 * background full-screen with its name centered (the BackgroundReel composition).
 * Idempotent: skips if the reel for the current design system + background set
 * already exists.
 */
class RenderBackgroundReelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RunsInProject;

    public int $timeout = 1200;

    public function __construct()
    {
        $this->captureProject();
    }

    public static function flagKey(string $projectSlug): string
    {
        return 'clips.background-reel.rendering.'.$projectSlug;
    }

    public function handle(BackgroundLibrary $library, RemotionRenderer $renderer): void
    {
        $this->activateProject();

        try {
            $out = $library->reelPath();
            if (is_file($out)) {
                return; // already cached for this design system + background set
            }

            // Code backgrounds must be present in remotion/src/backgrounds for this project.
            $library->syncFilesystem();

            @mkdir(dirname($out), 0777, true);
            $renderer->render($library->reelProps(), $out, 'src/index.ts', 'BackgroundReel');
        } finally {
            Cache::forget(self::flagKey($this->projectSlug));
        }
    }
}
