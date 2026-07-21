<?php

namespace App\Jobs\Clips;

use App\Models\ClipProject;
use App\Services\Clips\Contracts\RemotionRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class RenderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public int $projectId) {}

    public function handle(RemotionRenderer $renderer): void
    {
        $p = ClipProject::findOrFail($this->projectId);

        try {
            $p->update(['status' => ClipProject::STATUS_RENDERING]);

            $dir = storage_path("app/clips/{$p->id}");
            @mkdir($dir, 0777, true);
            $plan = $p->plan;

            if ($p->type === ClipProject::TYPE_ANIMATION) {
                $plan['audioSrc'] = $p->audio_path;
                $out = $renderer->render($plan, "$dir/clip.mp4");
                $p->update(['output_path' => $out, 'status' => ClipProject::STATUS_DONE]);
            } else {
                $plan['transparent'] = true;
                $out = $renderer->render($plan, "$dir/overlay.mov");
                $p->update(['meta' => array_merge($p->meta ?? [], ['overlay_path' => $out])]);
                ComposeOverlayJob::dispatch($p->id);
            }
        } catch (\Throwable $e) {
            $p->update(['status' => ClipProject::STATUS_FAILED, 'error' => $e->getMessage()]);
            throw $e;
        }
    }
}
