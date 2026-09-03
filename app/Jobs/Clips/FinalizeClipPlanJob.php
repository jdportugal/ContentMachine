<?php

namespace App\Jobs\Clips;

use App\Jobs\Concerns\RunsInProject;
use App\Services\Clips\Api\SceneTextVisuals;
use App\Services\Clips\BackgroundLibrary;
use App\Services\Clips\ClipImageGenerator;
use App\Services\Clips\EffectLibrary;
use App\Services\Clips\ImageLibrary;
use App\Services\Clips\ImageRequests;
use App\Services\Clips\PlanImageAugmentor;
use App\Services\Clips\SceneVisualFiller;
use App\Services\Clips\Store\ClipRecord;
use App\Services\Clips\Store\ClipStore;
use App\Services\Costs\CostLedger;
use App\Services\Publicacoes\Rendering\KieClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
        app(CostLedger::class)->contexto('clip', $this->projectId);
        $p = $store->findOrFail($this->projectId);

        try {
            $p->update(['status' => ClipRecord::STATUS_PLANNING]);
            $c = config('contentmachine.clips');
            $isOverlay = $p->type === ClipRecord::TYPE_OVERLAY;
            $plan = $p->plan ?? [];

            $imagesOn = (bool) ($c['generate_images'] ?? true)
                && app(ClipImageGenerator::class)->available();

            // Film any website suggestion the user approved without collecting —
            // "collect now" is optional; approval means "film the rest for me".
            $uploads = $p->meta['image_uploads'] ?? [];
            $declined = $p->meta['image_text'] ?? [];
            foreach ($p->meta['image_requests'] ?? [] as $r) {
                if (! empty($r['site']) && ! isset($uploads[$r['key']]) && empty($declined[$r['key']])) {
                    CollectSiteJob::dispatchSync($p->id, $r['key']);
                }
            }
            $p = $store->findOrFail($this->projectId); // captures may have added images/uploads

            // The user's uploads claim their suggestion slots; generation fills the rest.
            $plan = ImageRequests::applyUploads($plan, $p->meta['image_uploads'] ?? []);

            // Suggestions the user turned down ("no image") become non-image scenes
            // (card / list / diagram) built from what is said there.
            $declined = array_keys(array_filter($p->meta['image_text'] ?? []));
            if ($declined !== []) {
                $plan = app(SceneTextVisuals::class)->replace($plan, $declined, $p->transcript ?? []);
            }

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

            // An src/image that is not a real image id (the planner copied the
            // schema's "<id>" placeholder, or invented one) would render the
            // placeholder block — clear it so the layer is dropped or falls back.
            $library = app(EffectLibrary::class);
            $validIds = array_values(array_filter(array_map(fn ($i) => $i['id'] ?? null, $p->images ?? [])));
            $plan = $filler->stripUnknownImageIds($plan, $validIds);

            // Remove layers that would render an empty placeholder (image with no
            // src because generation failed, or a chart with no data) — including
            // custom effects that display an image but got none.
            $imageSlugs = array_values(array_filter($library->activeSlugs(), fn ($s) => $library->usesImage($s)));
            $plan = $filler->dropDeadLayers($plan, $imageSlugs);

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
            } else {
                // Overlay clips were left out of the bare-scene passes entirely, so an
                // `animation` scene the planner gave no layers rendered as nothing but
                // the karaoke punch word. There is always a source video here, so the
                // honest recovery is to just show it for that beat — same timing, no
                // blank frame, and no neighbour's graphic held for twice as long.
                // Unless the user DISALLOWED video-only beats (allowed_present): then
                // recover like animation clips — a neighbour's visual covers the beat.
                if (in_array('video', $p->meta['allowed_present'] ?? ['video'], true)) {
                    $plan = $filler->showVideoOnBareScenes($plan);
                } else {
                    $plan = $filler->mergeBareScenes($plan);
                    $plan = $filler->fillBareScenes($plan);
                }
            }

            // Guarantee the video OPENS with an intro effect (if any are marked) —
            // for all clip types. The planner is only nudged; this makes it reliable.
            // If an intro effect shows an image and the clip has uploaded images, feed
            // it one (prefer a transparent one — usually the logo) and force that
            // image-capable intro first.
            $intros = $library->introSlugs();

            // ── Animated intro video ─────────────────────────────────────────
            // The user approved (or the library matched) an animated intro: the
            // clip opens with the `video-intro` effect playing it, and a freshly
            // generated video is saved to the assets library for future reuse.
            $videoIntroId = $this->resolverVideoDeIntro($p);
            if ($videoIntroId !== null && in_array('video-intro', $intros, true)) {
                $plan = $filler->enforceIntro($plan, ['video-intro'], $videoIntroId);
                // enforceIntro prefers the opening scene's own image — right for
                // logo intros, wrong here: the intro plays THE VIDEO, always.
                if (isset($plan['scenes'][0]['layers']) && is_array($plan['scenes'][0]['layers'])) {
                    foreach ($plan['scenes'][0]['layers'] as &$l) {
                        if (is_array($l) && ($l['type'] ?? '') === 'video-intro') {
                            $l['params']['src'] = $videoIntroId;
                        }
                    }
                    unset($l);
                }
                $p->update(['plan' => $plan]);
                $this->renderizar($p);

                return;
            }

            // Without an approved video, `video-intro` is unusable — it plays
            // videos, and the classic path below would hand it a random still.
            $intros = array_values(array_diff($intros, ['video-intro']));

            $introImageId = null;
            if (($p->images ?? []) !== [] && collect($intros)->contains(fn (string $s) => $library->usesImage($s))) {
                usort($intros, fn (string $a, string $b) => (int) $library->usesImage($b) <=> (int) $library->usesImage($a));
                // An image uploaded FOR the intro wins: the user picked it for this
                // exact slot. Otherwise fall back to the logo-ish guess.
                $logo = collect($p->images)->firstWhere('id', $p->meta['image_uploads'][ImageRequests::INTRO_KEY] ?? null)
                    // The intro effect shows the logo via <Img>, so never feed it a video.
                    ?: collect($p->images)->firstWhere('transparent', true)
                    ?: collect($p->images)->first(fn ($i) => empty($i['video']));
                $introImageId = $logo['id'] ?? null;
            }
            // An intro effect that DISPLAYS an image renders empty without one, and
            // enforceIntro runs after dropDeadLayers so nothing would catch it — the
            // clip just opened on a blank frame. With no image to hand it, only
            // intros that stand on their own are eligible; if that leaves none, the
            // planner's own opening is kept instead of a forced empty one.
            if ($introImageId === null) {
                $intros = array_values(array_filter($intros, fn (string $s) => ! $library->usesImage($s)));
            }
            // Uploaded videos play fine on scenes (image-reveal), but an image intro
            // effect cannot render one — never let enforceIntro steal a video src.
            $videoIds = collect($p->images ?? [])->filter(fn ($i) => ! empty($i['video']))->pluck('id')->all();
            $plan = $filler->enforceIntro($plan, $intros, $introImageId, $videoIds);

            $p->update(['plan' => $plan]);

            $this->renderizar($p);
        } catch (\Throwable $e) {
            $p->update(['status' => ClipRecord::STATUS_FAILED, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * The video the intro should play, or null for the classic image intro:
     *  - a video the library matched / the user uploaded for the suggestion;
     *  - or one generated NOW (kie image→video) from the approved start image —
     *    and saved into the assets library (video + descriptions) for reuse.
     * "Image only" and "no image" both return null; failures degrade to null so
     * the clip still renders with a normal intro.
     */
    private function resolverVideoDeIntro($p): ?string
    {
        $key = ImageRequests::VIDEO_KEY;
        $req = collect($p->meta['image_requests'] ?? [])->firstWhere('key', $key);
        if (! is_array($req) || ! empty(($p->meta['image_text'] ?? [])[$key])) {
            return null;
        }

        $porId = collect($p->images ?? [])->keyBy('id');
        $escolhido = ($p->meta['image_uploads'] ?? [])[$key] ?? null;
        $entrada = $escolhido !== null ? $porId->get($escolhido) : null;
        if (is_array($entrada) && ! empty($entrada['video'])) {
            return (string) $entrada['id']; // reused asset / uploaded video — nothing to generate
        }
        if (($p->meta['video_mode'] ?? 'video') !== 'video') {
            return null; // kept as a still image
        }

        // The start frame: the user's replacement image if given, else the generated one.
        $inicio = is_array($entrada) ? $entrada : $porId->get($req['imagem'] ?? null);
        if (! is_array($inicio) || empty($inicio['path'])) {
            return null;
        }

        try {
            $disk = Storage::disk(config('contentmachine.clips.disk'));
            $bytes = $disk->exists($inicio['path']) ? $disk->get($inicio['path'])
                : (is_file($inicio['path']) ? file_get_contents($inicio['path']) : null);
            if (! $bytes) {
                return null;
            }

            $kie = app(KieClient::class);
            $kie->passo = 'clips_imagens';
            $url = $kie->generateVideo(
                (string) ($req['video_prompt'] ?? $req['label'] ?? ''),
                $kie->upload($bytes, 'intro.png'),
            );

            $rel = 'clips/uploads/intro-video-'.$p->id.'.mp4';
            $disk->put($rel, Http::timeout(120)->get($url)->body());

            $descricao = trim((string) ($req['label'] ?? 'Animated intro'));
            $entry = [
                'id' => 'vid_'.substr(md5($rel), 0, 8),
                'path' => $rel,
                'description' => $descricao,
                'transparent' => false,
                'tone' => 'mixed',
                'video' => true,
            ];
            $p->update(['images' => array_merge($p->images ?? [], [$entry])]);

            // The reusable asset: the next short can match it by name + description.
            app(ImageLibrary::class)->add(
                $disk->path($rel),
                Str::slug(Str::limit($descricao, 60, '')).'.mp4',
                'Animated video: '.$descricao
                    .(! empty($req['imagem_prompt']) ? ' — start frame: '.$req['imagem_prompt'] : ''),
            );

            return $entry['id'];
        } catch (\Throwable $e) {
            Log::warning('Intro video generation failed: '.$e->getMessage());

            return null;
        }
    }

    /**
     * Last step before pixels. A "Free SFX" clip detours through
     * GenerateFreeSfxJob, which writes bespoke components for its scenes and
     * dispatches the render itself — it does so even when generation fails, so
     * this is never a dead end.
     */
    private function renderizar(ClipRecord $p): void
    {
        if (! empty($p->meta['free_sfx'])) {
            GenerateFreeSfxJob::dispatch($p->id);

            return;
        }

        RenderJob::dispatch($p->id);
    }
}
