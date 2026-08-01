<?php

namespace App\Jobs\Clips;

use App\Jobs\Concerns\RunsInProject;
use App\Services\Clips\BackgroundLibrary;
use App\Services\Clips\ClipImageGenerator;
use App\Services\Clips\EffectLibrary;
use App\Services\Clips\ImageRequests;
use App\Services\Clips\PlanImageAugmentor;
use App\Services\Clips\SceneVisualFiller;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Second half of planning: the user has (optionally) uploaded images for the
 * planner's suggestions, so now fulfil the rest with generation, clean the plan up
 * and render. Split out from PlanAnimationsJob so a clip can pause between "plan
 * ready" and "images decided". On-demand image generation can be slow.
 */
class FinalizeClipPlanJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RunsInProject;

    public int $timeout = 900;

    public function __construct(public string $projectId)
    {
        $this->captureProject();
    }

    public function handle(ClipStore $store): void
    {
        $this->activateProject();
        $p = $store->findOrFail($this->projectId);

        try {
            $p->update(['status' => ClipRecord::STATUS_PLANNING]);
            $c = config('contentmachine.clips');
            $isOverlay = $p->type === ClipRecord::TYPE_OVERLAY;
            $plan = $p->plan ?? [];

            $imagesOn = (bool) ($c['generate_images'] ?? true)
                && app(ClipImageGenerator::class)->available();

            // The user's uploads claim their suggestion slots; generation fills the rest.
            $plan = ImageRequests::applyUploads($plan, $p->meta['image_uploads'] ?? []);

            $filler = app(SceneVisualFiller::class);

            // Fulfil the remaining image-generation requests (Nano Banana), on-brand.
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

            // Resolve the clip's backdrop: the manual choice (meta['background']:
            // 'auto' | 'none' | a slug) wins; on 'auto' the planner's suggestion is
            // honoured if enabled, else a random enabled background. Stored as a slug.
            $plannerPick = is_string($plan['background_pick'] ?? null) ? $plan['background_pick'] : null;
            unset($plan['background_pick']);
            $bgSlug = app(BackgroundLibrary::class)->resolveChoice((string) ($p->meta['background'] ?? 'auto'), $plannerPick);
            if ($bgSlug !== null) {
                $plan['background'] = $bgSlug;
            } else {
                unset($plan['background']);
            }

            // Eliminate bare scenes by extending a neighbour's real visual over them;
            // anything still bare (e.g. an all-bare plan) gets a clean fallback.
            if (! $isOverlay) {
                // MANDATORY: every image the user attached must land in some frame.
                // Runs BEFORE the bare-scene passes so a provided image claims a bare
                // scene (giving it a visual) instead of that scene being merged/filled.
                $plan = $filler->ensureProvidedImages($plan, $p->images ?? [], app(EffectLibrary::class)->allowedLayerTypes());
                $plan = $filler->mergeBareScenes($plan);
                $plan = $filler->fillBareScenes($plan);
                // Text-dense scenes get enough time to be read, borrowing from adjacent
                // low-value scenes (sync-safe — karaoke/audio are absolute-timed).
                $plan = $filler->enforceReadingTime($plan);
            }

            // Guarantee the video OPENS with an intro effect (if any are marked) —
            // for all clip types. The planner is only nudged; this makes it reliable.
            // If an intro effect shows an image and the clip has uploaded images, feed
            // it one (prefer a transparent one — usually the logo) and force that
            // image-capable intro first.
            $library = app(EffectLibrary::class);
            $intros = $library->introSlugs();
            $introImageId = null;
            if (($p->images ?? []) !== [] && collect($intros)->contains(fn (string $s) => $library->usesImage($s))) {
                usort($intros, fn (string $a, string $b) => (int) $library->usesImage($b) <=> (int) $library->usesImage($a));
                $logo = collect($p->images)->firstWhere('transparent', true) ?: $p->images[0];
                $introImageId = $logo['id'] ?? null;
            }
            $plan = $filler->enforceIntro($plan, $intros, $introImageId);

            $p->update(['plan' => $plan]);

            RenderJob::dispatch($p->id);
        } catch (\Throwable $e) {
            $p->update(['status' => ClipRecord::STATUS_FAILED, 'error' => $e->getMessage()]);
            throw $e;
        }
    }
}
