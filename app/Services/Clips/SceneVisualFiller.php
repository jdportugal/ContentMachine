<?php

namespace App\Services\Clips;

use Illuminate\Support\Str;

/**
 * Guarantees that animation clips are never "just a title on a blank frame".
 * Animation scenes have NO background video, so a scene with no visual layer is
 * empty. This gives every empty scene an image-reveal `generate` request derived
 * from what is spoken there (the PlanImageAugmentor then makes the image), and —
 * for any scene that still ends up bare — a subtle ambient layer instead of black.
 */
class SceneVisualFiller
{
    /** Add an image-reveal `generate` (from the scene's spoken words) to every empty scene. */
    public function requestImages(array $plan, array $transcript): array
    {
        $scenes = $plan['scenes'] ?? [];
        if (! is_array($scenes)) {
            return $plan;
        }
        foreach ($scenes as &$scene) {
            if ($this->hasForeground($scene)) {
                continue;
            }
            $spoken = $this->spokenText($transcript, (float) ($scene['start'] ?? 0), (float) ($scene['end'] ?? 0));
            if ($spoken === '') {
                continue;
            }
            $scene['layers'] = array_merge($scene['layers'] ?? [], [[
                'type' => 'image-reveal',
                'text' => null,
                'params' => ['generate' => "Illustrate this moment: {$spoken}", 'variant' => 'fullscreen'],
            ]]);
        }
        unset($scene);
        $plan['scenes'] = $scenes;

        return $plan;
    }

    /**
     * Cap how many images ONE clip asks to have MADE, spread across its length.
     *
     * Two sources pile these up and neither counted: the planner is told to be
     * "visual and dense", and requestImages() then adds one to every remaining
     * bare scene. The only limit was clips.image_max, which caps GENERATION, not
     * requests — and is a flat number, so a 25-second clip was allowed as many
     * pictures as a three-minute one. The result was a short clip asking for
     * seven images, each one a paid generation the user has to sit and approve.
     *
     * Only layers waiting on a `generate` count: an image the user supplied
     * (`src`) is already theirs, and a `site` is a page to FILM, chosen
     * deliberately by name. Over budget, the surplus layers are dropped whole —
     * the scene falls back to the bare-scene passes, exactly as it would with
     * image generation switched off.
     *
     * The keepers are spread evenly across the clip rather than taken from the
     * front, so the pictures do not all land in the opening seconds.
     */
    public function limitImages(array $plan, int $max): array
    {
        $scenes = $plan['scenes'] ?? [];
        if (! is_array($scenes) || $max < 0) {
            return $plan;
        }

        // Every layer awaiting a generated image, in playing order.
        $alvos = [];
        foreach ($scenes as $si => $scene) {
            foreach ($scene['layers'] ?? [] as $li => $layer) {
                if (! is_array($layer)) {
                    continue;
                }
                $p = $layer['params'] ?? [];
                if (! empty($p['generate']) && empty($p['src']) && empty($p['site'])) {
                    $alvos[] = [$si, $li];
                }
            }
        }

        if (count($alvos) <= $max) {
            return $plan;
        }

        $total = count($alvos);
        $manter = [];
        for ($i = 0; $i < $max; $i++) {
            $manter[$max === 1 ? 0 : (int) round($i * ($total - 1) / ($max - 1))] = true;
        }

        // Drop back-to-front so the earlier indices stay valid as we unset.
        foreach (array_reverse($alvos, true) as $n => [$si, $li]) {
            if (! isset($manter[$n])) {
                unset($scenes[$si]['layers'][$li]);
            }
        }
        foreach ($scenes as $si => $scene) {
            if (isset($scene['layers']) && is_array($scene['layers'])) {
                $scenes[$si]['layers'] = array_values($scene['layers']);
            }
        }

        $plan['scenes'] = $scenes;

        return $plan;
    }

    /**
     * How many images a clip of this length should ask for: one per
     * `clips.image_every_seconds`, never more than `clips.image_max`, never zero
     * (a clip with no picture at all is worse than one with a single good one).
     */
    public function imageBudget(array $plan): int
    {
        $fim = 0.0;
        foreach ($plan['scenes'] ?? [] as $scene) {
            $fim = max($fim, (float) ($scene['end'] ?? 0));
        }

        $c = config('contentmachine.clips');
        $cada = max(1, (int) ($c['image_every_seconds'] ?? 12));
        $teto = max(1, (int) ($c['image_max'] ?? 8));

        return max(1, min($teto, (int) round($fim / $cada)));
    }

    /**
     * Guarantee the video OPENS with an intro effect, kept as short as a normal
     * scene. The planner is only nudged to do this, so this makes it reliable:
     * if any effect is marked intro, the opening becomes a short intro scene
     * (the median duration of the other scenes, capped to the opening's own span)
     * showing the intro effect. Any displaced content is preserved right after.
     * No-op when nothing is marked intro or there are no scenes.
     *
     * When the intro effect shows an image, `$introImageId` (a provided image id)
     * is placed on its `params.src` so a branded/logo intro actually uses an
     * uploaded image instead of rendering empty.
     *
     * Ids in `$videoIds` are VIDEOS. An image-only intro effect cannot render
     * one, but the user put it on the opening scene on purpose — so the intro is
     * promoted to the built-in `video-intro`, which plays it full-frame.
     *
     * @param  string[]  $introSlugs
     * @param  string[]  $videoIds
     */
    public function enforceIntro(array $plan, array $introSlugs, ?string $introImageId = null, array $videoIds = []): array
    {
        $scenes = $plan['scenes'] ?? [];
        if ($introSlugs === [] || ! is_array($scenes) || $scenes === []) {
            return $plan;
        }
        $scenes = array_values($scenes);
        $first = $scenes[0];
        $layers = is_array($first['layers'] ?? null) ? $first['layers'] : [];

        // The intro must show the OPENING part's own visual (the one the user
        // chose / that was generated for it), not a generic logo — otherwise the
        // opening shows the wrong picture. Fall back to the passed logo only if
        // the opening scene has none of its own. When that visual is a VIDEO,
        // image intros can't play it — the built-in video-intro takes over.
        $sceneSrc = $this->firstImageSrc($layers);
        if ($sceneSrc !== null && in_array($sceneSrc, $videoIds, true)) {
            $introSlugs = ['video-intro'];
            $introImageId = $sceneSrc;
        } else {
            $introImageId = $sceneSrc ?? $introImageId;
        }

        $opensWithIntro = false;
        foreach ($layers as $layer) {
            if (is_array($layer) && in_array($layer['type'] ?? null, $introSlugs, true)) {
                $opensWithIntro = true;
                break;
            }
        }

        $start = (float) ($first['start'] ?? 0);
        $end = (float) ($first['end'] ?? 0);
        // Intro length: match a normal scene (median of the others; 2s fallback),
        // never longer than the opening scene's own span.
        $target = $this->typicalSceneDuration(array_slice($scenes, 1)) ?? 2.0;
        $tooLong = ($end - $start) > $target + self::EPS;

        // The intro ALWAYS shows full-frame: on an overlay clip, a video/over/split
        // opening would hide the effect behind the video or squeeze it into half
        // the frame — so the intro scene is forced to `animation` in every path.
        $fullFrame = isset($first['present']) && $first['present'] !== 'animation';

        // Planner already opens with a normal-length intro: make sure an image-based
        // intro gets its image, and that the scene shows the effect full-frame.
        if ($opensWithIntro && ! $tooLong) {
            $withImage = $this->withIntroImage($layers, $introSlugs, $introImageId);
            if ($withImage === $layers && ! $fullFrame) {
                return $plan; // nothing to change
            }
            $scenes[0]['layers'] = $withImage;
            if ($fullFrame) {
                $scenes[0]['present'] = 'animation';
            }
            $plan['scenes'] = $scenes;

            return $plan;
        }

        $introEnd = $tooLong ? $start + $target : $end;

        // The intro scene. If the planner already opened with an intro effect, keep
        // its content and just cap the length; otherwise swap in the intro effect.
        if ($opensWithIntro) {
            $introScene = array_merge($first, ['end' => $introEnd, 'layers' => $this->withIntroImage($layers, $introSlugs, $introImageId)]);
        } else {
            $introScene = array_merge($first, [
                'end' => $introEnd,
                'layers' => [[
                    'type' => $introSlugs[0],
                    'text' => (string) ($first['punchWord'] ?? ($layers[0]['text'] ?? '')),
                    'params' => $introImageId !== null ? ['src' => $introImageId] : [],
                ]],
                'punchWord' => null, // the intro effect carries the frame
            ]);
        }
        if ($fullFrame) {
            $introScene['present'] = 'animation'; // never split/video/over — see above
        }

        if ($introEnd >= $end - self::EPS) {
            $scenes[0] = $introScene; // opening is already short — it just becomes the intro
        } elseif ($opensWithIntro) {
            // Planner's intro spanned the whole scene: shorten it and let the next
            // scene start earlier (there is no other content to preserve).
            if (isset($scenes[1])) {
                $scenes[0] = $introScene;
                $scenes[1]['start'] = $introEnd;
            }
            // Single all-intro scene with nothing after → leave it (nothing to slide into).
        } else {
            // Keep the planner's original opening content in a remainder scene right
            // after the intro — MINUS the image the intro just TOOK from it.
            //
            // The intro shows the opening scene's own picture (see $sceneSrc above),
            // but the remainder used to be that scene verbatim, layer for layer. So
            // the instant the intro finished, the very same image played again
            // full-frame for whatever was left of the scene — 0.1s and 0.4s in two
            // real clips. That is the flash: not a render fault, the same picture
            // shown twice back to back.
            $resto = array_merge($first, [
                'start' => $introEnd,
                'end' => $end,
                'layers' => $this->semImagem($layers, $introImageId),
            ]);

            if (($resto['layers'] ?? []) === []) {
                // The image was all it had. Don't leave an empty sliver behind:
                // hand the time to the next scene, exactly as the branch above
                // does; with no next scene the intro keeps the whole opening span.
                if (isset($scenes[1])) {
                    $scenes[0] = $introScene;
                    $scenes[1]['start'] = $introEnd;
                } else {
                    $scenes[0] = array_merge($introScene, ['end' => $end]);
                }
            } else {
                array_splice($scenes, 0, 1, [$introScene, $resto]);
            }
        }

        $plan['scenes'] = array_values($scenes);

        return $plan;
    }

    /**
     * Set `params.src` to a provided image id on the first intro-effect layer that
     * has no image yet — so an image-based intro shows the uploaded image. Returns
     * the layers unchanged when there is no image, no intro layer, or one is set.
     *
     * @param  array<int,mixed>  $layers
     * @param  string[]  $introSlugs
     * @return array<int,mixed>
     */
    /**
     * Layers minus any that shows `$id` — the picture the intro has already used.
     *
     * Matches on the src rather than on the layer type: a custom image effect
     * would replay the image just as visibly as the built-in image-reveal. When
     * the intro image did NOT come from this scene (the logo fallback), nothing
     * matches and the layers are returned untouched, which is right — that image
     * was never the scene's to begin with.
     *
     * @param  array<int,mixed>  $layers
     * @return array<int,mixed>
     */
    private function semImagem(array $layers, ?string $id): array
    {
        if ($id === null || $id === '') {
            return array_values($layers);
        }

        return array_values(array_filter(
            $layers,
            fn ($l) => ! (is_array($l) && ($l['params']['src'] ?? null) === $id),
        ));
    }

    /** The `src` of the first image-reveal layer in a set (the scene's own image), or null. */
    private function firstImageSrc(array $layers): ?string
    {
        foreach ($layers as $layer) {
            if (is_array($layer) && ($layer['type'] ?? null) === 'image-reveal') {
                $src = $layer['params']['src'] ?? null;
                if (is_string($src) && $src !== '') {
                    return $src;
                }
            }
        }

        return null;
    }

    private function withIntroImage(array $layers, array $introSlugs, ?string $introImageId): array
    {
        if ($introImageId === null) {
            return $layers;
        }
        foreach ($layers as &$layer) {
            if (is_array($layer) && in_array($layer['type'] ?? null, $introSlugs, true)) {
                $params = is_array($layer['params'] ?? null) ? $layer['params'] : [];
                if (empty($params['src'])) {
                    $params['src'] = $introImageId;
                    $layer['params'] = $params;
                }
                break;
            }
        }
        unset($layer);

        return $layers;
    }

    /** Median duration of the given scenes, or null if none have a real span. */
    private function typicalSceneDuration(array $scenes): ?float
    {
        $durations = [];
        foreach ($scenes as $s) {
            $d = (float) ($s['end'] ?? 0) - (float) ($s['start'] ?? 0);
            if ($d > self::EPS) {
                $durations[] = $d;
            }
        }
        if ($durations === []) {
            return null;
        }
        sort($durations);

        return $durations[intdiv(count($durations), 2)];
    }

    /** Ornament/speech layers that carry little information — safe to replace with an image. */
    private const ORNAMENTS = ['ambient', 'kinetic-text', 'seal-stamp', 'fleuron-draw', 'underline-sweep', 'highlight', 'count-up'];

    /** Layer types whose content is a block of text worth reading (drives dwell time). */
    private const TEXT_HEAVY = ['bullet-list', 'card', 'comparison', 'terminal', 'timeline', 'diagram'];

    private const EPS = 0.05;

    /**
     * Give text-dense scenes enough time to be read. A scene whose text visual runs
     * shorter than its estimated reading time is extended by borrowing seconds from
     * the FOLLOWING low-value scene (bare / ambient / ornament) — never from a real
     * visual. Only the visual boundary moves, so coverage stays contiguous and the
     * audio + karaoke (absolute-timed) stay in sync. A neighbour shrunk to nothing is
     * dropped. Complements mergeBareScenes (which already holds a visual over gaps).
     */
    public function enforceReadingTime(array $plan): array
    {
        $scenes = $plan['scenes'] ?? [];
        if (! is_array($scenes) || count($scenes) < 2) {
            return $plan;
        }
        $scenes = array_values($scenes);
        $n = count($scenes);

        for ($i = 0; $i < $n - 1; $i++) {
            $need = $this->readingSeconds($scenes[$i]);
            if ($need <= 0) {
                continue;
            }
            $deficit = $need - ((float) ($scenes[$i]['end'] ?? 0) - (float) ($scenes[$i]['start'] ?? 0));

            // Borrow from consecutive following low-value scenes until satisfied.
            $j = $i + 1;
            while ($deficit > self::EPS && $j < $n && $this->isLowValue($scenes[$j])) {
                $jStart = (float) ($scenes[$j]['start'] ?? 0);
                $jEnd = (float) ($scenes[$j]['end'] ?? 0);
                $jDur = $jEnd - $jStart;
                if ($jDur <= self::EPS) {
                    $j++;

                    continue;
                }
                // Take what's needed; if that would leave a sliver, consume it whole.
                $take = min($deficit, $jDur);
                if ($jDur - $take < 0.6) {
                    $take = $jDur;
                }
                $scenes[$i]['end'] = (float) $scenes[$i]['end'] + $take;
                $scenes[$j]['start'] = $jStart + $take;
                $deficit -= $take;
                if ((float) $scenes[$j]['start'] >= $jEnd - self::EPS) {
                    $j++; // neighbour fully absorbed — move to the next
                }
            }
        }

        // Drop any neighbour that was fully consumed.
        $plan['scenes'] = array_values(array_filter(
            $scenes,
            fn ($s) => (float) ($s['end'] ?? 0) - (float) ($s['start'] ?? 0) > self::EPS,
        ));

        return $plan;
    }

    /** Visuals whose entrance animation takes ~2s to play — cutting away sooner truncates it. */
    private const ANIMATED = ['bar-chart', 'line-chart', 'pie-chart', 'scatter-chart', 'timeline', 'diagram', 'terminal'];

    /**
     * Seconds this scene's visual needs on screen (0 when there is nothing to wait
     * for). Two components, the larger wins: a FLOOR for the entrance animation to
     * actually finish (every real visual animates in; charts/diagrams draw over
     * ~2s), and reading time for text-heavy content.
     */
    private function readingSeconds(array $scene): float
    {
        $floor = 0.0;
        $len = 0;
        foreach ($scene['layers'] ?? [] as $l) {
            if (! is_array($l)) {
                continue;
            }
            $type = $l['type'] ?? '';
            if ($type === 'ambient' || in_array($type, self::ORNAMENTS, true)) {
                continue;
            }
            $floor = max($floor, in_array($type, self::ANIMATED, true) ? 3.0 : 2.0);
            if (in_array($type, self::TEXT_HEAVY, true)) {
                $len += $this->textLength($l['params'] ?? []);
            }
        }
        if ($len < 40) {
            return $floor; // little/no text — just let the animation finish
        }

        return max($floor, min(6.0, $len / 16.0)); // ~16 chars/sec, clamped to a sane window
    }

    /** Total length of all string content nested anywhere in a value. */
    private function textLength(mixed $node): int
    {
        if (is_string($node)) {
            return mb_strlen($node);
        }
        if (is_array($node)) {
            $sum = 0;
            foreach ($node as $v) {
                $sum += $this->textLength($v);
            }

            return $sum;
        }

        return 0;
    }

    /** A scene safe to shrink for a neighbour's dwell: no foreground, or only ornaments. */
    private function isLowValue(array $scene): bool
    {
        $foreground = array_values(array_filter(
            $scene['layers'] ?? [],
            fn ($l) => is_array($l) && ($l['type'] ?? '') !== 'ambient',
        ));

        return $foreground === [] || $this->allOrnaments($foreground);
    }

    /**
     * MANDATORY provided images: every image the user attached to the clip must show
     * in at least one frame. The planner is asked to place them, but this GUARANTEES
     * it — any provided image not referenced anywhere is injected as an image-reveal
     * layer (the built-in SFX that displays an image), preferring scenes with no real
     * visual (bare / ambient / ornament), then AI-generated images, and only then a
     * data visual. No-op if image-reveal is disabled (nothing can show an image) or no
     * images were provided.
     *
     * @param  array<int,array<string,mixed>>  $images  the clip's provided images ({id,path,…})
     * @param  string[]|null  $allowedLayers  enabled layer types (image-reveal must be among them)
     */
    public function ensureProvidedImages(array $plan, array $images, ?array $allowedLayers = null): array
    {
        $scenes = $plan['scenes'] ?? [];
        if (! is_array($scenes) || $scenes === [] || $images === []) {
            return $plan;
        }
        if ($allowedLayers !== null && ! in_array('image-reveal', $allowedLayers, true)) {
            return $plan; // the only image-capable layer is off — cannot enforce
        }

        $providedIds = array_values(array_filter(
            array_map(fn ($i) => is_array($i) ? ($i['id'] ?? null) : null, $images),
            fn ($v) => is_string($v) && $v !== '',
        ));
        if ($providedIds === []) {
            return $plan;
        }

        $unused = array_values(array_diff($providedIds, $this->usedImageIds($scenes, $providedIds)));
        if ($unused === []) {
            return $plan; // the planner already placed every provided image
        }

        // Scene indices ranked by how little is lost if replaced (lowest first);
        // scenes already showing a provided image are excluded.
        $order = $this->placementOrder($scenes, $providedIds);
        if ($order === []) {
            return $plan;
        }
        $slots = count($order);

        foreach ($unused as $k => $imgId) {
            $idx = $order[$k % $slots];
            $imageLayer = ['type' => 'image-reveal', 'text' => null, 'params' => ['src' => $imgId, 'variant' => 'fullscreen']];

            if ($k < $slots) {
                // First pass: the image OWNS this scene (keep any ambient underneath).
                $ambient = array_values(array_filter(
                    $scenes[$idx]['layers'] ?? [],
                    fn ($l) => is_array($l) && ($l['type'] ?? '') === 'ambient',
                ));
                $scenes[$idx]['layers'] = array_merge($ambient, [$imageLayer]);
                $scenes[$idx]['punchWord'] = null; // the image carries the frame
            } else {
                // ponytail: more images than scenes (rare — clips have many scenes).
                // Best-effort: append; the extra image at least enters the plan.
                $scenes[$idx]['layers'][] = $imageLayer;
            }
        }

        $plan['scenes'] = array_values($scenes);

        return $plan;
    }

    /** Provided image ids referenced anywhere in the scenes (src, chart image fields, nodes…). @return string[] */
    private function usedImageIds(array $scenes, array $providedIds): array
    {
        $found = [];
        $walk = function ($node) use (&$walk, &$found, $providedIds) {
            if (is_array($node)) {
                foreach ($node as $v) {
                    $walk($v);
                }
            } elseif (is_string($node) && in_array($node, $providedIds, true)) {
                $found[$node] = true;
            }
        };
        foreach ($scenes as $s) {
            $walk($s['layers'] ?? []);
        }

        return array_keys($found);
    }

    /**
     * Scene indices to place images into, lowest-cost first: rank 0 = no real visual
     * (bare/ornament), rank 1 = an AI/other image-reveal, rank 2 = a data visual.
     * Scenes already showing a provided image are omitted. Ties keep scene order.
     *
     * @return int[]
     */
    private function placementOrder(array $scenes, array $providedIds): array
    {
        $ranked = [];
        foreach ($scenes as $i => $scene) {
            $layers = array_values(array_filter($scene['layers'] ?? [], 'is_array'));
            if ($this->usedImageIds([$scene], $providedIds) !== []) {
                continue; // already fulfils a provided image — leave it be
            }
            $foreground = array_values(array_filter($layers, fn ($l) => ($l['type'] ?? '') !== 'ambient'));

            if ($foreground === [] || $this->allOrnaments($foreground)) {
                $ranked[$i] = 0;
            } elseif (count($foreground) === 1 && ($foreground[0]['type'] ?? '') === 'image-reveal') {
                $ranked[$i] = 1; // an AI/other image — a user image is preferred here
            } else {
                $ranked[$i] = 2;
            }
        }
        asort($ranked); // stable on PHP 8: rank asc, scene order preserved for ties

        return array_keys($ranked);
    }

    private function allOrnaments(array $foreground): bool
    {
        foreach ($foreground as $l) {
            if (! in_array($l['type'] ?? '', self::ORNAMENTS, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Clear image references that point at nothing: the planner sometimes copies
     * the schema's own placeholder ("<id of a PROVIDED image>") or invents an id,
     * and that string survives resolution untouched — the layer then renders the
     * striped placeholder block instead of a picture. A src/image value must be a
     * known image id (or an existing file, e.g. a site capture); anything else is
     * removed so dropDeadLayers can drop or fall back the layer honestly.
     *
     * @param  string[]  $validIds
     */
    public function stripUnknownImageIds(array $plan, array $validIds): array
    {
        $scenes = $plan['scenes'] ?? [];
        if (! is_array($scenes)) {
            return $plan;
        }
        $ok = fn ($v) => is_string($v) && $v !== '' && (in_array($v, $validIds, true) || is_file($v));
        $walk = function (&$node) use (&$walk, $ok) {
            if (! is_array($node)) {
                return;
            }
            foreach (['src', 'image', 'img'] as $k) {
                if (array_key_exists($k, $node) && ! $ok($node[$k])) {
                    unset($node[$k]);
                }
            }
            foreach ($node as &$v) {
                $walk($v);
            }
            unset($v);
        };
        foreach ($scenes as &$scene) {
            if (isset($scene['layers']) && is_array($scene['layers'])) {
                $walk($scene['layers']);
            }
        }
        unset($scene);
        $plan['scenes'] = $scenes;

        return $plan;
    }

    /**
     * Remove layers that would render an empty-state placeholder: an image-reveal
     * with no image (generation failed / no src) or a chart/diagram with no data.
     * Those show a striped block or a faint "—" and look broken. Run AFTER image
     * generation, so failed images are cleaned up and the scene can fall back.
     *
     * @param  string[]  $imageSlugs  custom effect slugs that DISPLAY an image —
     *                                without a src they render their placeholder too
     */
    public function dropDeadLayers(array $plan, array $imageSlugs = []): array
    {
        $scenes = $plan['scenes'] ?? [];
        if (! is_array($scenes)) {
            return $plan;
        }
        foreach ($scenes as &$scene) {
            if (! isset($scene['layers']) || ! is_array($scene['layers'])) {
                continue;
            }
            $scene['layers'] = array_values(array_filter(
                $scene['layers'],
                fn ($l) => is_array($l) && $this->layerHasContent($l, $imageSlugs),
            ));
        }
        unset($scene);
        $plan['scenes'] = $scenes;

        return $plan;
    }

    /**
     * True unless the layer would render its empty-state placeholder (the big
     * grey em-dash). Public so the plan editor can apply the same rule when the
     * user swaps a scene's animation — a data primitive handed none of its data
     * renders that dash, and the editor has no field to type bars/items/nodes in.
     *
     * @param  string[]  $imageSlugs
     */
    public function layerHasContent(array $layer, array $imageSlugs = []): bool
    {
        $p = is_array($layer['params'] ?? null) ? $layer['params'] : [];
        $text = $layer['text'] ?? null;

        // A custom effect that displays an image is as dead without one as
        // image-reveal is — it renders its own "IMAGE / PLACEHOLDER" frame.
        if (in_array($layer['type'] ?? '', $imageSlugs, true)) {
            return ! empty($p['src']) || ! empty($p['image']);
        }

        return match ($layer['type'] ?? '') {
            'image-reveal' => ! empty($p['src']),
            // Text primitives need at least one non-blank string (matches the
            // primitives, which filter out empty lines before rendering).
            'card' => $this->hasText($p['title'] ?? null) || $this->hasText($text) || $this->hasAnyText($p['lines'] ?? $p['items'] ?? null),
            'bullet-list' => $this->hasText($p['title'] ?? null) || $this->hasAnyText($p['items'] ?? null),
            'terminal' => $this->hasAnyText($p['lines'] ?? null),
            'timeline' => ! empty($p['items']),
            'bar-chart' => ! empty($p['bars']),
            'line-chart' => ! empty($p['series']),
            'pie-chart' => ! empty($p['slices']),
            'scatter-chart' => ! empty($p['points']),
            'diagram' => ! empty($p['nodes']),
            'comparison' => ! empty($p['left']) || ! empty($p['right']),
            default => true, // ornament / speech layers (kinetic-text, seal-stamp, ambient, count-up, …)
        };
    }

    private function hasText(mixed $v): bool
    {
        return is_string($v) && trim($v) !== '';
    }

    private function hasAnyText(mixed $list): bool
    {
        if (! is_array($list)) {
            return false;
        }
        foreach ($list as $item) {
            if ($this->hasText($item) || (is_array($item) && $this->hasText($item['label'] ?? null))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Eliminate bare scenes (no foreground visual) by absorbing each into an
     * adjacent scene that HAS a visual: extend that neighbour's real graphic to
     * cover the bare stretch instead of leaving a blank frame. Prefer the previous
     * scene (visual holds a bit longer), else the next; a bare scene with no visual
     * neighbour (e.g. every scene is bare) is left for fillBareScenes. The karaoke
     * captions keep flowing, so no invented text and no duplicated words.
     */
    public function mergeBareScenes(array $plan): array
    {
        $scenes = $plan['scenes'] ?? [];
        if (! is_array($scenes) || count($scenes) < 2) {
            return $plan;
        }

        // Forward: absorb a bare scene into the previous kept scene when that one
        // has a visual (covers interior and trailing bare runs).
        $kept = [];
        foreach ($scenes as $scene) {
            $prev = $kept === [] ? null : $kept[array_key_last($kept)];
            if (! $this->hasForeground($scene) && $prev !== null && $this->hasForeground($prev)) {
                $i = array_key_last($kept);
                $kept[$i]['end'] = $scene['end'] ?? $kept[$i]['end'];
                $kept[$i]['transitionOut'] = $scene['transitionOut'] ?? ($kept[$i]['transitionOut'] ?? 'cut');

                continue; // drop the bare scene
            }
            $kept[] = $scene;
        }

        // Backward: any bare scene still standing (a leading bare run) is absorbed
        // into the NEXT kept scene when that one has a visual.
        $out = [];
        foreach (array_reverse($kept) as $scene) {
            $next = $out === [] ? null : $out[array_key_last($out)];
            if (! $this->hasForeground($scene) && $next !== null && $this->hasForeground($next)) {
                $i = array_key_last($out);
                $out[$i]['start'] = $scene['start'] ?? $out[$i]['start'];
                $out[$i]['transitionIn'] = $scene['transitionIn'] ?? ($out[$i]['transitionIn'] ?? 'cut');

                continue; // drop the bare scene
            }
            $out[] = $scene;
        }

        $plan['scenes'] = array_values(array_reverse($out));

        return $plan;
    }

    /**
     * Any scene still without a foreground layer gets a clean ambient background
     * (never a broken placeholder). The karaoke captions carry the words — we do
     * NOT turn a bare scene into a giant single word: a lone punch word is dropped
     * so no scene is "just one word". With image credits the scene would instead
     * have gotten a picture.
     */
    public function fillBareScenes(array $plan): array
    {
        $scenes = $plan['scenes'] ?? [];
        if (! is_array($scenes)) {
            return $plan;
        }
        // Only fall back to an `ambient` layer if that effect is enabled; when it's
        // toggled off the scene stays bare and the composition starfield shows through.
        $ambientAllowed = app(EffectLibrary::class)->builtinAllowed('ambient');
        foreach ($scenes as &$scene) {
            if ($this->hasForeground($scene)) {
                continue;
            }
            $scene['layers'] = $ambientAllowed ? [['type' => 'ambient', 'text' => null, 'params' => []]] : [];
            $scene['punchWord'] = null; // no lone word as the whole scene
        }
        unset($scene);
        $plan['scenes'] = $scenes;

        return $plan;
    }

    /**
     * Overlay clips only: an `animation` scene with no visual has nothing behind it,
     * so it renders as a bare backdrop plus the karaoke punch word. The source video
     * is always available in an overlay clip, so show it for that beat instead.
     * Scene timing is untouched, so audio/karaoke stay in sync.
     */
    public function showVideoOnBareScenes(array $plan): array
    {
        $scenes = $plan['scenes'] ?? [];
        if (! is_array($scenes)) {
            return $plan;
        }
        foreach ($scenes as &$scene) {
            if (is_array($scene) && ! $this->hasForeground($scene)) {
                $scene['present'] = 'video';
            }
        }
        unset($scene);
        $plan['scenes'] = $scenes;

        return $plan;
    }

    private function hasForeground(array $scene): bool
    {
        // Overlay clips composite the source video per scene: a `video`/`over`/
        // `split` scene already has a picture on screen, so it is never bare no
        // matter what layers it carries. Only `animation` scenes render on nothing
        // but the backdrop, which is what makes an empty one "just a punch word".
        $present = $scene['present'] ?? null;
        if (is_string($present) && $present !== 'animation') {
            return true;
        }

        return (bool) array_filter(
            $scene['layers'] ?? [],
            fn ($l) => is_array($l) && ($l['type'] ?? '') !== 'ambient',
        );
    }

    /** The words spoken within [start,end], as a short prompt seed. */
    public function spokenText(array $transcript, float $start, float $end): string
    {
        $words = $transcript['words'] ?? [];
        if (! is_array($words) || $words === []) {
            return '';
        }
        $out = [];
        foreach ($words as $w) {
            $ws = (float) ($w['start'] ?? -1);
            if ($ws >= $start - 0.25 && $ws < $end) {
                $out[] = (string) ($w['word'] ?? '');
            }
        }

        return Str::limit(trim(implode(' ', array_filter($out))), 220, '');
    }
}
