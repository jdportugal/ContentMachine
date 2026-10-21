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
 * Renders one effect with the user's own inputs ("use this effect" in the SFX
 * studio) — a template filled with a real photo, avatar and texts. Same plan as
 * the studio preview, at the length asked for. A failure is written next to the
 * output as <out>.err so the page can stop waiting and say why.
 */
class RenderEffectUseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RunsInProject;

    public int $timeout = 900;

    public int $tries = 1;

    /** @param  array<string,mixed>  $params */
    public function __construct(
        public string $slug,
        public ?string $text,
        public array $params,
        public string $format,
        public float $duration,
        public string $out,
    ) {
        $this->captureProject();
    }

    public function handle(EffectLibrary $library, RemotionRenderer $renderer): void
    {
        $this->activateProject();

        try {
            @mkdir(dirname($this->out), 0777, true);
            $renderer->render($library->samplePlan($this->slug, $this->text, $this->params, $this->format, $this->duration), $this->out);
        } catch (\Throwable $e) {
            @file_put_contents($this->out.'.err', $e->getMessage());
            throw $e;
        }
    }
}
