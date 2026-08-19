<?php

namespace App\Jobs\Clips;

use App\Jobs\Concerns\RunsInProject;
use App\Services\Clips\Contracts\RemotionRenderer;
use App\Services\Clips\EffectLibrary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Renders (and caches) a showcase preview for a KNOWN effect — a built-in or an
 * already-active custom one — through the normal ClipComposition bundle. Cheap
 * and idempotent: skips if the preview for the current design system exists.
 */
class RenderEffectSampleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RunsInProject;

    public int $timeout = 600;

    /** @param  array<string,mixed>  $params */
    public function __construct(
        public string $slug,
        public ?string $text,
        public array $params,
        public string $format = EffectLibrary::FORMAT_PORTRAIT,
    ) {
        $this->captureProject();
    }

    public function handle(EffectLibrary $library, RemotionRenderer $renderer): void
    {
        $this->activateProject();

        $out = $library->previewPath($this->slug, $this->format);
        if (is_file($out)) {
            return; // already cached for this design system
        }

        // A custom effect must be present in remotion/src/effects for this project.
        if (! $library->isBuiltin($this->slug)) {
            $library->syncFilesystem();
        }

        @mkdir(dirname($out), 0777, true);
        $renderer->render($library->samplePlan($this->slug, $this->text, $this->params, $this->format), $out);
    }
}
