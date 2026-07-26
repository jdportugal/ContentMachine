<?php

namespace App\Jobs\Clips;

use App\Jobs\Concerns\RunsInProject;
use App\Services\Clips\Contracts\RemotionRenderer;
use App\Services\Clips\EffectLibrary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;

/**
 * Renders (and caches) the SFX showreel — one video cycling through every effect
 * with its name centered — through the normal ClipComposition bundle. Idempotent:
 * skips if the reel for the current design system + effect set already exists.
 */
class RenderShowreelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RunsInProject;

    public int $timeout = 1200;

    public function __construct()
    {
        $this->captureProject();
    }

    public static function flagKey(string $projectSlug): string
    {
        return 'clips.showreel.rendering.'.$projectSlug;
    }

    public function handle(EffectLibrary $library, RemotionRenderer $renderer): void
    {
        $this->activateProject();

        try {
            $out = $library->showreelPath();
            if (is_file($out)) {
                return; // already cached for this design system + effect set
            }

            // Custom effects must be present in remotion/src/effects for this project.
            $library->syncFilesystem();

            @mkdir(dirname($out), 0777, true);
            $renderer->render($library->showreelPlan(), $out);
        } finally {
            Cache::forget(self::flagKey($this->projectSlug));
        }
    }
}
