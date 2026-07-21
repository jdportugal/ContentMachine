<?php

namespace App\Jobs\Clips;

use App\Models\ClipProject;
use App\Services\Clips\Contracts\AnimationPlanner;
use App\Services\Clips\PlanValidator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class PlanAnimationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function __construct(public int $projectId) {}

    public function handle(AnimationPlanner $planner, PlanValidator $validator): void
    {
        $p = ClipProject::findOrFail($this->projectId);

        try {
            $p->update(['status' => ClipProject::STATUS_PLANNING]);

            $mode = $p->type === ClipProject::TYPE_ANIMATION ? 'dense' : 'sparse';
            $c = config('contentmachine.clips');

            $plan = $planner->plan($p->transcript, $mode, [
                'width' => $c['width'],
                'height' => $c['height'],
                'fps' => $c['fps'],
            ]);
            $plan = $validator->validate($plan);

            $p->update(['plan' => $plan]);

            RenderJob::dispatch($p->id);
        } catch (\Throwable $e) {
            $p->update(['status' => ClipProject::STATUS_FAILED, 'error' => $e->getMessage()]);
            throw $e;
        }
    }
}
