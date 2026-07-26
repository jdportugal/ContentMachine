<?php

namespace App\Jobs\Clips;

use App\Jobs\Concerns\RunsInProject;
use App\Services\Clips\Contracts\AnimationPlanner;
use App\Services\Clips\Contracts\MetadataService;
use App\Services\Clips\Contracts\ResearchService;
use App\Services\Clips\PlanImageAugmentor;
use App\Services\Clips\PlanValidator;
use App\Services\Clips\SceneVisualFiller;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class PlanAnimationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RunsInProject;

    // Planning + research + on-demand image generation (Nano Banana) can be slow.
    public int $timeout = 900;

    public function __construct(public string $projectId)
    {
        $this->captureProject();
    }

    public function handle(AnimationPlanner $planner, PlanValidator $validator, ResearchService $research, MetadataService $metadata, ClipStore $store): void
    {
        $this->activateProject();
        $p = $store->findOrFail($this->projectId);

        try {
            $p->update(['status' => ClipRecord::STATUS_PLANNING]);
            $c = config('contentmachine.clips');
            $isOverlay = $p->type === ClipRecord::TYPE_OVERLAY;
            $allowed = $isOverlay ? ($p->meta['allowed_present'] ?? ['video', 'over', 'split', 'animation']) : [];

            // Deep-research the topic so visuals carry real context (not just the speech).
            $facts = ($c['research'] ?? false) ? $research->research($p->transcript) : [];
            if (! empty($facts)) {
                $p->update(['meta' => array_merge($p->meta ?? [], ['research' => $facts])]);
            }

            // Suggested publishing metadata (title/description/tags) from the transcript.
            $suggested = $metadata->suggest($p->transcript);
            $p->update([
                'title' => $suggested['title'] !== '' ? $suggested['title'] : $p->title,
                'meta' => array_merge($p->meta ?? [], ['suggested' => $suggested]),
            ]);

            // Both cover the full duration. Overlay clips intercut presentation modes
            // (video / over / split / animation) chosen by the planner per scene.
            $plan = $planner->plan($p->transcript, 'dense', [
                'width' => $c['width'],
                'height' => $c['height'],
                'fps' => $c['fps'],
                'facts' => $facts,
                'images' => $p->images ?? [],
                'overlay' => $isOverlay,
                'presents' => $allowed,
            ]);
            $plan = $validator->validate($plan, $p->transcript['text'] ?? '', $isOverlay, $allowed ?: null);

            // Animation clips have NO background video, so an empty scene is a blank
            // frame. Give every empty scene an image-reveal `generate` from its
            // spoken words so the clip isn't just a title.
            $filler = app(SceneVisualFiller::class);
            if (! $isOverlay) {
                $plan = $filler->requestImages($plan, $p->transcript ?? []);
            }

            // Fulfil the image-generation requests (Nano Banana), on-brand.
            if (config('contentmachine.clips.generate_images', true)) {
                $result = app(PlanImageAugmentor::class)->augment(
                    $plan,
                    $p->images ?? [],
                    (string) config('contentmachine.clips.image_style', ''),
                    (int) config('contentmachine.clips.image_max', 8),
                );
                $plan = $result['plan'];
                $p->update(['images' => $result['images']]);
            }

            // Remove layers that would render an empty placeholder (image with no
            // src because generation failed, or a chart with no data).
            $plan = $filler->dropDeadLayers($plan);

            // Anything now bare gets ambient motion, never a broken placeholder.
            if (! $isOverlay) {
                $plan = $filler->fallbackAmbient($plan);
            }

            $p->update(['plan' => $plan]);

            RenderJob::dispatch($p->id);
        } catch (\Throwable $e) {
            $p->update(['status' => ClipRecord::STATUS_FAILED, 'error' => $e->getMessage()]);
            throw $e;
        }
    }
}
