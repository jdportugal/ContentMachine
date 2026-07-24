<?php

namespace App\Jobs\Clips;

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
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 600;

    /** @param  array<string,mixed>  $params */
    public function __construct(public string $slug, public ?string $text, public array $params) {}

    public function handle(EffectLibrary $library, RemotionRenderer $renderer): void
    {
        $out = $library->previewPath($this->slug);
        if (is_file($out)) {
            return; // already cached for this design system
        }

        @mkdir(dirname($out), 0777, true);
        $renderer->render($library->samplePlan($this->slug, $this->text, $this->params), $out);
    }
}
