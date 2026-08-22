<?php

namespace App\Jobs\Clips;

use App\Jobs\Concerns\RunsInProject;
use App\Services\Clips\ClipImageGenerator;
use App\Services\Clips\Contracts\AnimationPlanner;
use App\Services\Clips\Contracts\MetadataService;
use App\Services\Clips\Contracts\ResearchService;
use App\Services\Clips\EffectLibrary;
use App\Services\Clips\ImageLibrary;
use App\Services\Clips\ImageLibraryMatcher;
use App\Services\Clips\ImageRequests;
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
        app(\App\Services\Costs\CostLedger::class)->contexto('clip', $this->projectId);
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

            // The full script/plan is ready — every image the planner wants exists as
            // a `generate` request. First reuse anything already in the project's
            // image library (a logo, a brand shot); whatever is left, offer the user
            // the chance to upload before it is generated. No pending suggestions →
            // go straight to finalisation.
            $p->update(['plan' => $plan]);
            // Site suggestions (pages the planner wants FILMED) don't need the image
            // generator, so they are collected even when AI images are off.
            $requests = ImageRequests::collect($plan, $p->transcript ?? [], $filler);
            if (! $imagesOn) {
                $requests = array_values(array_filter($requests, fn (array $r) => ! empty($r['site'])));
            }

            // The intro effect is bolted on AFTER planning (FinalizeClipPlanJob →
            // enforceIntro), so an image-showing intro has no layer in the plan and
            // would never be asked about — it just rendered empty. Ask for it here.
            $requests = ImageRequests::withIntro($requests, app(EffectLibrary::class), $p->images ?? []);

            $library = app(ImageLibrary::class);
            // A site suggestion's "prompt" is a URL — meaningless to the library matcher.
            $matchable = array_values(array_filter($requests, fn (array $r) => empty($r['site'])));
            $matched = $matchable !== [] ? app(ImageLibraryMatcher::class)->match($matchable, $library->all()) : [];
            $uploads = [];
            $images = $p->images ?? [];
            foreach ($matched as $key => $libId) {
                $entry = $library->attachToClip($libId);
                if ($entry !== null) {
                    $images[] = $entry;
                    $uploads[$key] = $entry['id'];
                }
            }
            $p->update(['images' => $images]);

            // Only the suggestions the library did NOT satisfy still need the user.
            $pending = array_filter($requests, fn (array $r) => ! isset($uploads[$r['key']]));

            if ($pending === []) {
                // Fully covered (library matches, or nothing to generate): finalise.
                $p->update(['meta' => array_merge($p->meta ?? [], ['image_uploads' => $uploads])]);
                FinalizeClipPlanJob::dispatch($p->id);

                return;
            }

            $p->update([
                'status' => ClipRecord::STATUS_COLLECTING,
                'meta' => array_merge($p->meta ?? [], ['image_requests' => $requests, 'image_uploads' => $uploads]),
            ]);
        } catch (\Throwable $e) {
            $p->update(['status' => ClipRecord::STATUS_FAILED, 'error' => $e->getMessage()]);
            throw $e;
        }
    }
}
