<?php

namespace App\Jobs\Clips;

use App\Models\ClipProject;
use App\Services\Clips\Contracts\VideoCompositor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class ComposeOverlayJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public int $projectId) {}

    public function handle(VideoCompositor $ff): void
    {
        $p = ClipProject::findOrFail($this->projectId);

        try {
            $dir = storage_path("app/clips/{$p->id}");
            $out = $ff->overlay(
                storage_path("app/{$p->source_path}"),
                $p->meta['overlay_path'],
                "$dir/final.mp4"
            );
            $p->update(['output_path' => $out, 'status' => ClipProject::STATUS_DONE]);
        } catch (\Throwable $e) {
            $p->update(['status' => ClipProject::STATUS_FAILED, 'error' => $e->getMessage()]);
            throw $e;
        }
    }
}
