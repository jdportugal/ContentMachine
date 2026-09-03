<?php

namespace App\Jobs\Clips;

use App\Jobs\Concerns\RunsInProject;
use App\Services\Aggregation\LlmClient;
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
use App\Services\Clips\Store\VfxStore;
use App\Services\Costs\CostLedger;
use App\Services\Projects\ProjectLanguage;
use App\Services\Shorts\LocalVideoEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
        app(CostLedger::class)->contexto('clip', $this->projectId);
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
            // An overlay clip renders AROUND the uploaded video, so the composition
            // has to match the source: a fixed 9:16 frame turned a landscape upload
            // into a cropped portrait. Animation-only clips keep the configured size.
            // An explicit output format wins over both; 'auto' keeps the old rule.
            [$planW, $planH] = $this->formatoEscolhido($p)
                ?? ($isOverlay ? $this->sourceSize($p, $c) : [(int) $c['width'], (int) $c['height']]);

            $plan = $planner->plan($p->transcript, 'dense', [
                'width' => $planW,
                'height' => $planH,
                'fps' => $c['fps'],
                'facts' => $facts,
                'images' => $p->images ?? [],
                'overlay' => $isOverlay,
                'presents' => $allowed,
                'can_generate_images' => $imagesOn,
                // Direction for THIS clip, on top of the project's style manual.
                'instructions' => trim((string) ($p->meta['instructions'] ?? '')),
            ]);
            $plan = $validator->validate($plan, $p->transcript['text'] ?? '', $isOverlay, $allowed ?: null, app(EffectLibrary::class)->allowedLayerTypes());

            // If we can make images, give every empty animation scene an image-reveal
            // `generate` from its spoken words. If not, we leave it for the non-image
            // fallback (the planner was already told to use other content).
            $filler = app(SceneVisualFiller::class);
            // "Reduce the number of images": half the budget, and applied to overlay
            // clips too — those are otherwise uncapped (the planner asks freely).
            $fewer = (bool) ($p->meta['fewer_images'] ?? false);
            if (! $isOverlay && $imagesOn) {
                $plan = $filler->requestImages($plan, $p->transcript ?? []);
            }
            if ($imagesOn && (! $isOverlay || $fewer)) {
                // Both the planner and the filler above add image requests without
                // counting them, so cap the total against the clip's LENGTH — the
                // old flat ceiling let a short clip ask for seven paid generations.
                $budget = $filler->imageBudget($plan);
                $plan = $filler->limitImages($plan, $fewer ? max(1, intdiv($budget, 2)) : $budget);
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
            $requests = ImageRequests::withIntroVideo($requests, app(EffectLibrary::class), $p->images ?? []);
            $requests = ImageRequests::withIntro($requests, app(EffectLibrary::class), $p->images ?? []);

            // The animated intro needs a concept before anything else: the label is
            // what the user reads AND what the library matcher uses to spot a
            // reusable video asset (e.g. an existing mascot animation).
            $requests = $this->conceberVideoDeIntro($requests, $p);

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

            // An animated-intro suggestion the library did NOT satisfy stays
            // imageless: the REVIEW page has a «generate image» button, so the
            // user triggers (and waits for) the start image and only then decides.
            // Dropped when images cannot be generated at all.
            $requests = $this->podarVideoDeIntro($requests, $uploads, $imagesOn);

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

            // Now go and FETCH the real images — the actual person, logo, product
            // or page — before anyone is asked to find them by hand. Pages the
            // planner itself named are left to CollectSiteJob, and the animated
            // intro is a concept, not a real thing. Whatever is not found stays
            // pending and is generated at finalisation, exactly as before.
            $fetch = config('contentmachine.clips.fetch_images', true)
                ? array_values(array_map(
                    fn (array $r) => $r['key'],
                    array_filter($pending, fn (array $r) => empty($r['site']) && empty($r['animado'])),
                ))
                : [];

            if ($fetch !== []) {
                // Flagged before dispatch so the review screen shows the search
                // running the moment it opens (it polls on this).
                $p->update(['meta' => array_merge($p->meta ?? [], [
                    // 'search' (not just true) so the review screen says what is
                    // running: searching the web, not generating an image.
                    'site_collecting' => array_merge($p->meta['site_collecting'] ?? [], array_fill_keys($fetch, 'search')),
                ])]);
            }

            // Auto clips have nobody to review the suggestions — go straight on
            // (FinalizeClipPlanJob films the site suggestions and fills the rest),
            // but let the fetch finish first so they get real images too.
            if (! empty($p->meta['auto'])) {
                $fetch !== []
                    ? Bus::chain([new FetchWebImagesJob($p->id), new FinalizeClipPlanJob($p->id)])->dispatch()
                    : FinalizeClipPlanJob::dispatch($p->id);

                return;
            }

            if ($fetch !== []) {
                FetchWebImagesJob::dispatch($p->id);
            }
        } catch (\Throwable $e) {
            $p->update(['status' => ClipRecord::STATUS_FAILED, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * The frame the author picked when creating the clip, or null for 'auto'.
     *
     * @return array{0:int,1:int}|null
     */
    private function formatoEscolhido(ClipRecord $p): ?array
    {
        $aspecto = VfxStore::ASPECTS[(string) ($p->meta['formato'] ?? 'auto')] ?? null;

        return $aspecto ? [(int) $aspecto['width'], (int) $aspecto['height']] : null;
    }

    /**
     * The uploaded video's own frame size, so an overlay clip is composed at the
     * source's aspect ratio instead of a fixed 9:16. Downscaled to the configured
     * long edge (a 4K upload would otherwise quadruple every render) and kept even
     * for h264. Falls back to the configured size if ffprobe can't tell us.
     *
     * ponytail: ignores rotation metadata — a phone clip tagged 90° probes as its
     * coded (unrotated) size. Read side_data_list if that turns up.
     *
     * @param  array<string,mixed>  $c
     * @return array{0:int,1:int}
     */
    private function sourceSize(ClipRecord $p, array $c): array
    {
        $fallback = [(int) $c['width'], (int) $c['height']];

        try {
            $probe = app(LocalVideoEngine::class)->probe(
                Storage::disk($c['disk'])->path((string) $p->source_path)
            );
        } catch (\Throwable) {
            return $fallback;
        }

        $w = (int) ($probe['width'] ?? 0);
        $h = (int) ($probe['height'] ?? 0);
        if ($w <= 0 || $h <= 0) {
            return $fallback;
        }

        $scale = min(1.0, max($fallback) / max($w, $h));

        return [
            max(2, (int) round($w * $scale / 2) * 2),
            max(2, (int) round($h * $scale / 2) * 2),
        ];
    }

    /**
     * Writes the animated-intro concept: a short video description (project
     * language, shown to the user and used to match reusable assets), a motion
     * prompt and a start-frame prompt (English, for the models). Drops the
     * suggestion when no LLM can write it.
     *
     * @param  array<int,array<string,mixed>>  $requests
     * @return array<int,array<string,mixed>>
     */
    private function conceberVideoDeIntro(array $requests, $p): array
    {
        $titulo = (string) ($p->meta['suggested']['title'] ?? $p->title ?? '');
        $resumo = (string) ($p->meta['research']['summary'] ?? Str::limit((string) ($p->transcript['text'] ?? ''), 400, ''));
        $lingua = ProjectLanguage::name();

        // No animated intro in play → the PLAIN opening image still deserves a
        // real suggestion: an image about THIS clip's subject, not the brand logo.
        $i = collect($requests)->search(fn ($r) => ($r['key'] ?? null) === ImageRequests::VIDEO_KEY);
        if ($i === false) {
            return $this->conceberImagemDeIntro($requests, $titulo, $resumo, $lingua);
        }

        $texto = app(LlmClient::class)->texto(
            'A 9:16 short video opens with a 5-second generated INTRO ANIMATION. '
            .'Design ONE simple, reusable animation concept for it — a single subject with one clear motion '
            ."(a mascot waving, a logo assembling, an object morphing), on-brand, no text on screen.\n\n"
            ."The short is about: {$titulo}\n{$resumo}\n\n"
            .'Respond ONLY with JSON: {"descricao": "one sentence naming the animation, in '.$lingua.'", '
            .'"video": "motion prompt for an image-to-video model, in English (subject + motion + camera, one sentence)", '
            .'"imagem": "start-frame image prompt, in English (the first frame of that motion, no text)"}',
            json: true,
        );
        $dados = json_decode((string) preg_replace('/^.*?(\{.*\}).*$/s', '$1', (string) $texto), true);

        if (! is_array($dados) || trim((string) ($dados['video'] ?? '')) === '' || trim((string) ($dados['imagem'] ?? '')) === '') {
            unset($requests[$i]);

            return array_values($requests);
        }

        $requests[$i]['label'] = trim((string) ($dados['descricao'] ?? '')) ?: trim((string) $dados['video']);
        $requests[$i]['prompt'] = 'An animated video: '.trim((string) $dados['video']);
        $requests[$i]['video_prompt'] = trim((string) $dados['video']);
        $requests[$i]['imagem_prompt'] = trim((string) $dados['imagem']);

        return array_values($requests);
    }

    /**
     * Rewrites the plain opening-image suggestion so it is about the CLIP —
     * the label the user reads (project language) and the prompt «generate now»
     * uses (English). The logo default only survives when no LLM can write it.
     *
     * @param  array<int,array<string,mixed>>  $requests
     * @return array<int,array<string,mixed>>
     */
    private function conceberImagemDeIntro(array $requests, string $titulo, string $resumo, string $lingua): array
    {
        $i = collect($requests)->search(fn ($r) => ($r['key'] ?? null) === ImageRequests::INTRO_KEY);
        if ($i === false) {
            return $requests;
        }

        $texto = app(LlmClient::class)->texto(
            'A 9:16 short video needs its OPENING IMAGE — the first frame the viewer sees. '
            ."Suggest ONE striking image about the video's SUBJECT (a concrete scene, object or metaphor — "
            ."never a logo, never text on screen).\n\n"
            ."The short is about: {$titulo}\n{$resumo}\n\n"
            .'Respond ONLY with JSON: {"descricao": "one sentence naming the image, in '.$lingua.'", '
            .'"imagem": "image-generation prompt, in English (subject, composition, mood — no text)"}',
            json: true,
        );
        $dados = json_decode((string) preg_replace('/^.*?(\{.*\}).*$/s', '$1', (string) $texto), true);
        if (! is_array($dados) || trim((string) ($dados['imagem'] ?? '')) === '') {
            return $requests; // keep the logo fallback
        }

        $requests[$i]['prompt'] = trim((string) $dados['imagem']);
        $requests[$i]['label'] = 'Opening image — '.(trim((string) ($dados['descricao'] ?? '')) ?: trim((string) $dados['imagem']));

        return $requests;
    }

    /**
     * Drops the animated-intro suggestion when its start image could never be
     * made: no image generation available and nothing matched in the library.
     * Otherwise it stays — imageless — for the user to generate on review.
     *
     * @param  array<int,array<string,mixed>>  $requests
     * @param  array<string,string>  $uploads
     * @return array<int,array<string,mixed>>
     */
    private function podarVideoDeIntro(array $requests, array $uploads, bool $imagesOn): array
    {
        $key = ImageRequests::VIDEO_KEY;
        $i = collect($requests)->search(fn ($r) => ($r['key'] ?? null) === $key);
        if ($i === false || isset($uploads[$key]) || $imagesOn) {
            return $requests;
        }
        unset($requests[$i]);

        return array_values($requests);
    }
}
