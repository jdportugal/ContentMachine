<?php

namespace App\Jobs\Clips;

use App\Jobs\Concerns\RunsInProject;
use App\Services\Clips\ClipImageGenerator;
use App\Services\Clips\Contracts\AnimationPlanner;
use App\Services\Clips\Contracts\MetadataService;
use App\Services\Clips\Contracts\ResearchService;
use App\Services\Clips\EffectLibrary;
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

            // Can we actually make AI images? (config on AND kie has credits). If not,
            // the planner is told to use provided images / non-image content only.
            $imagesOn = (bool) ($c['generate_images'] ?? true)
                && app(ClipImageGenerator::class)->available();

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
                'can_generate_images' => $imagesOn,
            ]);
            $plan = $validator->validate($plan, $p->transcript['text'] ?? '', $isOverlay, $allowed ?: null, app(EffectLibrary::class)->allowedLayerTypes());

            // If we can make images, give every empty animation scene an image-reveal
            // `generate` from its spoken words. If not, we leave it for the non-image
            // fallback (the planner was already told to use other content).
            $filler = app(SceneVisualFiller::class);
            if (! $isOverlay && $imagesOn) {
                $plan = $filler->requestImages($plan, $p->transcript ?? []);
            }

            // Fulfil the image-generation requests (Nano Banana), on-brand.
            if ($imagesOn) {
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

            // Eliminate bare scenes by extending a neighbour's real visual over them;
            // anything still bare (e.g. an all-bare plan) gets a clean fallback.
            if (! $isOverlay) {
                $plan = $filler->mergeBareScenes($plan);
                $plan = $filler->fillBareScenes($plan);
            }

            $p->update(['plan' => $plan]);

            RenderJob::dispatch($p->id);
        } catch (\Throwable $e) {
            $p->update(['status' => ClipRecord::STATUS_FAILED, 'error' => $e->getMessage()]);
            throw $e;
        }
    }
}
