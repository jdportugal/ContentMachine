<?php

namespace App\Jobs\Clips;

use App\Jobs\Concerns\RunsInProject;
use App\Services\Clips\BackgroundLibrary;
use App\Services\Clips\Contracts\RemotionRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Renders (and caches) a preview for an already-active CODE background through the
 * normal ClipComposition bundle — render only, no Claude. Cheap and idempotent:
 * skips if the preview for the current design system exists. Used to rebuild a
 * preview that went stale when the design system changed.
 */
class RenderBackgroundSampleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RunsInProject;

    public int $timeout = 600;

    public function __construct(public string $slug)
    {
        $this->captureProject();
        // Previews are bulk, best-effort work: opening the studio can queue one
        // per effect per framing. On its own queue, listed after `default` on the
        // worker, a backfill can never make someone's generation wait behind it.
        $this->onQueue('previews');
    }

    public function handle(BackgroundLibrary $library, RemotionRenderer $renderer): void
    {
        $this->activateProject();

        $out = $library->previewPath($this->slug);
        if (is_file($out)) {
            return; // already cached for this design system
        }

        $library->syncFilesystem(); // the code background must be present in remotion/src/backgrounds
        @mkdir(dirname($out), 0777, true);
        $renderer->render($library->samplePlan($this->slug), $out);
    }
}
