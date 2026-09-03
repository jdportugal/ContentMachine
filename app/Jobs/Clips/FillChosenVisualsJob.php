<?php

namespace App\Jobs\Clips;

use App\Jobs\Concerns\RunsInProject;
use App\Services\Clips\Api\SceneTextVisuals;
use App\Services\Clips\EffectLibrary;
use App\Services\Clips\SceneVisualFiller;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Write the data for scenes whose animation the user picked in the plan editor.
 *
 * Swapping a scene to a timeline / diagram / chart gives it a type but no items,
 * bars or nodes — only the planner writes those, and the scene editor has no
 * field to type them into. Left alone the primitive draws its empty-state
 * placeholder, the big grey em-dash in the middle of the frame. This asks for
 * the data from what is spoken in that scene, in the type the user chose, then
 * hands off to the render.
 *
 * Runs before RenderJob rather than inside it so a slow LLM call never eats the
 * render timeout, and a failure here still renders the clip (the empty layer is
 * dropped instead of drawing the dash).
 *
 * @param  array<int,string>  $typeByScene  scene index => the type the user picked
 */
class FillChosenVisualsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RunsInProject;

    /** @param array<int,string> $typeByScene */
    public function __construct(public string $projectId, public array $typeByScene)
    {
        $this->captureProject();
    }

    public function handle(ClipStore $store, SceneTextVisuals $visuals, SceneVisualFiller $filler, EffectLibrary $library): void
    {
        $this->activateProject();
        $p = $store->findOrFail($this->projectId);

        try {
            $plan = $visuals->buildChosen($p->plan ?? [], $this->typeByScene, $p->transcript ?? []);
        } catch (\Throwable $e) {
            // Not fatal: the clip still renders, just without this scene's graphic.
            Log::warning('Chosen scene visuals failed: '.$e->getMessage());
            $plan = $p->plan ?? [];
        }

        // Anything the model would not write stays empty — drop it rather than
        // let it draw the placeholder. Same rule FinalizeClipPlanJob applies.
        $imageSlugs = array_values(array_filter($library->activeSlugs(), fn ($s) => $library->usesImage($s)));
        $plan = $filler->dropDeadLayers($plan, $imageSlugs);

        // Dropping a layer can leave an `animation` scene with nothing to draw, and
        // this path went straight to the renderer — so the beat came out as a bare
        // backdrop with the video hidden behind it. FinalizeClipPlanJob already
        // recovers those; the same rule has to apply after a hand-picked visual
        // fails to materialise, which is the common way to get here.
        if ($p->type === ClipRecord::TYPE_OVERLAY
            && in_array('video', $p->meta['allowed_present'] ?? ['video'], true)) {
            $plan = $filler->showVideoOnBareScenes($plan);
        }

        $p->update(['plan' => $plan, 'status' => ClipRecord::STATUS_RENDERING]);

        RenderJob::dispatch($p->id);
    }
}
