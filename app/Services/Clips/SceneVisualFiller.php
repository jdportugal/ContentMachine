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
        // The same holds when the video arrives as $introImageId — the user
        // uploaded it for the intro slot itself. Check the id actually chosen.
        $introImageId = $this->firstImageSrc($layers) ?? $introImageId;
        if ($introImageId !== null && in_array($introImageId, $videoIds, true)) {
            $introSlugs = ['video-intro'];
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
            // The intro shows the opening scene's own picture (see above),
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
     * Fill the empty photo slots of fan/gallery effects. The planner tends to
     * hand a five-photo fan three — the other two then render as blank cards.
     * Pictures nothing uses yet go in first; then ones shown elsewhere (least
     * used first), never one already in this fan and never one serving as an
     * avatar or logo (the agent's portrait in a house fan).
     *
     * @param  array<int,array<string,mixed>>  $images
     * @param  array<string,list<string>>  $galleries  slug => slot keys in order
     * @param  string[]  $slotSlugs  intro/outro effects — every picture they show is off limits
     */
    public function fillGallerySlots(array $plan, array $images, array $galleries, array $slotSlugs = []): array
    {
        // Videos too: effects play a clip in a photo slot (MediaImg), a listing's room clips among them.
        $ids = array_values(array_filter(array_column($images, 'id')));
        if ($galleries === [] || $ids === []) {
            return $plan;
        }

        // How often each picture is shown, and which serve as avatar/logo (any non-src key).
        $usos = array_fill_keys($ids, 0);
        $reservadas = [];
        foreach ($plan['scenes'] ?? [] as $scene) {
            foreach ($scene['layers'] ?? [] as $layer) {
                foreach ((array) ($layer['params'] ?? []) as $k => $v) {
                    foreach ((array) $v as $id) {
                        if (is_string($id) && isset($usos[$id])) {
                            $usos[$id]++;
                            if (! preg_match('/^src\d*$|^srcs$/', (string) $k) || in_array($layer['type'] ?? null, $slotSlugs, true)) {
                                $reservadas[$id] = true;
                            }
                        }
                    }
                }
            }
        }

        foreach ($plan['scenes'] ?? [] as $si => $scene) {
            foreach ($scene['layers'] ?? [] as $li => $layer) {
                $slots = $galleries[$layer['type'] ?? ''] ?? null;
                if ($slots === null) {
                    continue;
                }
                $params = (array) ($layer['params'] ?? []);
                $dentro = array_filter(array_map(fn ($k) => $params[$k] ?? null, $slots));
                foreach ($slots as $k) {
                    if (! empty($params[$k])) {
                        continue;
                    }
                    $candidatas = array_filter($ids, fn ($id) => ! in_array($id, $dentro, true) && ! isset($reservadas[$id]));
                    if ($candidatas === []) {
                        break;
                    }
                    usort($candidatas, fn ($a, $b) => $usos[$a] <=> $usos[$b]); // unused first
                    $params[$k] = $candidatas[0];
                    $dentro[] = $candidatas[0];
                    $usos[$candidatas[0]]++;
                }
                $plan['scenes'][$si]['layers'][$li]['params'] = $params;
            }
        }

        return $plan;
    }

    /**
     * A picture placed in an intro/outro slot (the agent's portrait in the card,
     * the avatar in the listing template) is not shown again anywhere else. The
     * "every image appears" pass runs BEFORE those slots are filled, so it saw the
     * portrait as unused and gave it a full-frame scene of its own — the agent
     * then appeared mid-video and again right before his card. A scene left empty
     * by the removal hands its time to the scene before it.
     *
     * @param  string[]  $slotSlugs  intro + outro effect slugs
     */
    public function dropSlotDuplicates(array $plan, array $slotSlugs): array
    {
        $scenes = array_values(is_array($plan['scenes'] ?? null) ? $plan['scenes'] : []);
        $reservadas = [];
        foreach ($scenes as $scene) {
            foreach ($scene['layers'] ?? [] as $layer) {
                if (is_array($layer) && in_array($layer['type'] ?? null, $slotSlugs, true)) {
                    foreach ((array) ($layer['params'] ?? []) as $v) {
                        if (is_string($v) && str_starts_with($v, 'img_')) {
                            $reservadas[$v] = true;
                        }
                    }
                }
            }
        }
        if ($reservadas === []) {
            return $plan;
        }

        $out = [];
        foreach ($scenes as $scene) {
            $layers = array_values(array_filter($scene['layers'] ?? [], fn ($l) => ! (is_array($l)
                && ! in_array($l['type'] ?? null, $slotSlugs, true)
                && isset($reservadas[$l['params']['src'] ?? '']))));
            if ($layers === [] && ($scene['layers'] ?? []) !== [] && $out !== []) {
                $out[count($out) - 1]['end'] = $scene['end'];  // the previous scene covers its time

                continue;
            }
            $scene['layers'] = $layers;
            $out[] = $scene;
        }
        $plan['scenes'] = $out;

        return $plan;
    }

    /**
     * A scene whose main layer is a custom image effect is a full-frame photo: the
     * scene background never shows, but the effect still picks its text colour
     * from it (`dark`). The planner alternates papyrus/ink per scene, so the same
     * effect drew white titles in one scene and dark, unreadable ones over the
     * photo in the next. Photos read as dark — force `ink` there.
     *
     * @param  string[]  $imageEffects  custom slugs that show a picture
     */
    public function darkenPhotoScenes(array $plan, array $imageEffects): array
    {
        foreach ($plan['scenes'] ?? [] as $i => $scene) {
            foreach ($scene['layers'] ?? [] as $layer) {
                if (is_array($layer) && in_array($layer['type'] ?? null, $imageEffects, true) && ! empty($layer['params']['src'])) {
                    $plan['scenes'][$i]['background'] = 'ink';
                    break;
                }
            }
        }

        return $plan;
    }

    /**
     * A listing reel made from its video: between the intro (+ after-intro) and
     * the agent's card, the planner's photo scenes give way to continuous room
     * footage laid on the voiceover's topics (VideoClipMatcher::segments) — each
     * topic shows its own takes, cut where the next topic's words start, no
     * effects in between. A topic longer than one take continues on another take
     * of what it shows; a take repeats only when no unused one shows the topic.
     * The planner's on-screen lines stay, on the first take of their topic.
     *
     * @param  list<array{start:float,end:float,text?:string,clips:list<string>}>  $topics
     * @param  array<string,float>  $lengths  video id => seconds
     * @param  array<string,string>  $rooms  video id => its room ("cozinha" for Cozinha and Cozinha 2)
     */
    public function footageTimeline(array $plan, array $topics, array $lengths, array $rooms): array
    {
        $scenes = array_values($plan['scenes'] ?? []);
        $head = ! empty($scenes[1]['plain']) ? 2 : 1;
        if (count($scenes) < $head + 2 || $lengths === []) {
            return $plan;
        }
        $outro = end($scenes);
        [$from, $to] = [(float) $scenes[$head - 1]['end'], (float) $outro['start']];
        $lines = collect(array_slice($scenes, $head, -1))
            ->map(fn ($sc) => ['start' => (float) $sc['start'], 'end' => (float) $sc['end'], 'text' => trim((string) ($sc['layers'][0]['text'] ?? ''))])
            ->filter(fn ($l) => $l['text'] !== '' && mb_strlen($l['text']) <= 40)->values()->all();

        // The topics inside the body, back to back (each runs to the next one's first word).
        $segs = [];
        foreach ($topics as $tp) {
            if ((float) $tp['end'] <= $from || (float) $tp['start'] >= $to) {
                continue;
            }
            // Every shot gets at least MIN_SHOT: a quick topic ("cozinha equipada", 0.9 s) still shows, running a
            // little into the next one, which starts that much later.
            $start = $segs === [] ? $from : max((float) $tp['start'], $segs[count($segs) - 1]['start'] + self::MIN_SHOT);
            // A topic with nothing to show, or pushed past its own words, rides on the one before.
            if ($segs !== [] && ($tp['clips'] === [] || $start >= min($to, (float) $tp['end']) + 0.5)) {   // footage may trail its words by half a second
                $segs[count($segs) - 1]['clips'] = array_values(array_unique(array_merge($segs[count($segs) - 1]['clips'], $tp['clips'])));

                continue;
            }
            $segs[] = ['start' => $start, 'clips' => $tp['clips']];
        }
        if ($segs === []) {
            $segs[] = ['start' => $from, 'clips' => []];
        }

        $uses = array_fill_keys(array_keys($lengths), 0);
        $prev = $scenes[0]['layers'][0]['params']['src'] ?? null;
        if (isset($uses[$prev])) {
            $uses[$prev]++;
        }
        $body = [];
        foreach ($segs as $k => $seg) {
            $t = (float) $seg['start'];
            $end = (float) ($segs[$k + 1]['start'] ?? $to);
            $line = collect($lines)->sortByDesc(fn ($l) => min($end, $l['end']) - max($t, $l['start']))
                ->first(fn ($l) => min($end, $l['end']) - max($t, $l['start']) > 0.5);
            if ($line) {
                $lines = array_values(array_filter($lines, fn ($l) => $l !== $line));
            }
            $first = true;
            while ($end - $t > self::CLIP_SHORTFALL) {
                $id = self::nextFootage($seg['clips'], $uses, $lengths, $rooms, $prev);
                $e = min($end, $t + $lengths[$id]);
                if ($end - $e <= self::CLIP_SHORTFALL) {
                    $e = $end;   // a few frames short: held
                } elseif ($end - $e < 1.0 && $e - $t > 2.0) {
                    $e = $end - 1.0;   // leave the next take a full second, not a flash
                }
                $body[] = ['start' => round($t, 3), 'end' => round($e, 3), 'footage' => true, 'present' => 'animation', 'background' => 'ink', 'karaoke' => false, 'punchWord' => null,
                    'layers' => [['type' => 'video-intro', 'text' => $first ? ($line['text'] ?? '') : '', 'params' => ['src' => $id]]]];
                $uses[$id]++;
                [$prev, $t, $first] = [$id, $e, false];
            }
        }
        $plan['scenes'] = array_merge(array_slice($scenes, 0, $head), $body, [$outro]);

        return $plan;
    }

    /**
     * The next take for a topic ($ranked: the clips that show it, best first):
     * an unused one, then an unused take of a room it shows, then the best one
     * again; anything unused only when nothing fits; never the take just shown.
     */
    private static function nextFootage(array $ranked, array $uses, array $lengths, array $rooms, ?string $prev): string
    {
        $ok = fn ($id) => $id !== $prev && isset($lengths[$id]);
        $fitRooms = array_unique(array_map(fn ($id) => $rooms[$id] ?? $id, $ranked));
        $sameRoom = array_values(array_filter(array_keys($lengths), fn ($id) => in_array($rooms[$id] ?? $id, $fitRooms, true)));
        $fits = array_values(array_unique(array_filter(array_merge($ranked, $sameRoom), $ok)));
        $unused = array_values(array_filter($fits, fn ($id) => $uses[$id] === 0));

        return $unused[0]
            ?? $fits[0]   // every take that fits was shown: the best one again, not a lesser match
            ?? collect(array_keys($lengths))->filter($ok)->sortBy(fn ($id) => [$uses[$id], -$lengths[$id]])->first()
            ?? $prev;
    }

    /**
     * Show what is being said: each scene playing a video clip gets a clip that
     * shows the phrase said over it (VideoClipMatcher's ranking), preferring one
     * not shown yet and long enough to cover the scene; never the one just shown.
     * The scene right before the after-intro video MUST be covered — nothing can
     * move there, so a short clip would mean a stray cut into the video. Photos,
     * the after-intro scene and phrases with no clip keep the planner's choice.
     *
     * @param  list<array{start:float,end:float,clips:list<string>}>  $phrases
     * @param  array<string,float>  $lengths  video id => seconds
     */
    public function syncVideoClips(array $plan, array $phrases, array $lengths, ?string $photo = null): array
    {
        $uses = array_fill_keys(array_keys($lengths), 0);
        $scenes = array_values($plan['scenes']);
        $prev = null;
        foreach ($scenes as $k => &$scene) {
            $src = $scene['layers'][0]['params']['src'] ?? null;
            if (! empty($scene['plain']) || ! is_string($src) || ! isset($lengths[$src])) {
                $prev = $src;

                continue;
            }
            [$from, $to] = [(float) $scene['start'], (float) $scene['end']];
            $mustCover = ! empty($scenes[$k + 1]['plain']);
            // Not a clip the effect already shows in another slot (a split's top and bottom alike).
            $taken = array_values(array_intersect_key($scene['layers'][0]['params'], array_flip(['src2', 'src3', 'src4', 'src5'])));
            $lengthsHere = array_diff_key($lengths, array_flip(array_filter($taken, 'is_string')));
            // What is said over it first; what is said around it only when nothing fits that.
            $pick = self::bestClip(self::clipsFor($phrases, $from, $to), $uses, $lengthsHere, $to - $from, $prev, $mustCover)
                ?? self::bestClip(self::clipsFor($phrases, $from - 3, $to + 3), $uses, $lengthsHere, $to - $from, $prev, $mustCover)
                // Right before the after-intro video and nothing that fits covers it: the longest that fits (another
                // fitting take finishes the scene), else the house photo — never an unrelated room.
                ?? ($mustCover ? (collect(self::clipsFor($phrases, $from, $to))->filter(fn ($id) => $id !== $prev && isset($lengthsHere[$id]))->sortByDesc(fn ($id) => $lengthsHere[$id])->first()
                    ?? $photo ?? self::bestClip(array_keys($lengthsHere), $uses, $lengthsHere, $to - $from, $prev, true)) : null);
            if ($pick !== null) {
                $scene['layers'][0]['params']['src'] = $pick;
            }
            $prev = $scene['layers'][0]['params']['src'];
            if (isset($uses[$prev])) {
                $uses[$prev]++;
            }
        }
        unset($scene);
        $plan['scenes'] = $scenes;

        return $plan;
    }

    /**
     * The clip to show: of those that fit the words ($ranked, best first), the
     * least shown — an unused one before any repeat — then one that covers $need
     * seconds, then the best ranked. $mustCover: only clips that cover it.
     */
    private static function bestClip(array $ranked, array $uses, array $lengths, float $need, ?string $prev, bool $mustCover = false): ?string
    {
        $covers = fn ($id) => $lengths[$id] >= $need - self::CLIP_SHORTFALL;
        $candidates = array_values(array_filter($ranked, fn ($id) => $id !== $prev && isset($lengths[$id]) && (! $mustCover || $covers($id))));
        $rank = array_flip($candidates);
        usort($candidates, fn ($a, $b) => [$uses[$a] ?? 0, $covers($a) ? 0 : 1, $rank[$a]] <=> [$uses[$b] ?? 0, $covers($b) ? 0 : 1, $rank[$b]]);

        return $candidates[0] ?? null;
    }

    /**
     * No effect shows the same picture twice (a split with one clip top and
     * bottom, a fan with a repeated card): a repeat in an effect's slots
     * (src…src5) gives way to the least shown of $ids not already in it.
     *
     * @param  string[]  $ids  what may fill a slot (the clip's photos and footage — not the agent or the logo)
     */
    public function distinctSlots(array $plan, array $ids): array
    {
        $keys = ['src', 'src2', 'src3', 'src4', 'src5'];
        $uses = array_fill_keys($ids, 0);
        $scenesForCount = $plan['scenes'] ?? [];
        array_walk_recursive($scenesForCount, function ($v) use (&$uses) {
            if (is_string($v) && isset($uses[$v])) {
                $uses[$v]++;
            }
        });
        foreach ($plan['scenes'] as &$scene) {
            if (! is_array($scene['layers'] ?? null)) {
                continue;
            }
            foreach ($scene['layers'] as &$layer) {
                $seen = [];
                foreach ($keys as $key) {
                    $v = $layer['params'][$key] ?? null;
                    if (! is_string($v) || $v === '') {
                        continue;
                    }
                    if (in_array($v, $seen, true)) {
                        $free = collect($ids)->reject(fn ($id) => in_array($id, $seen, true) || in_array($id, array_values($layer['params']), true))
                            ->sortBy(fn ($id) => $uses[$id])->first();
                        if ($free === null) {
                            continue;
                        }
                        $layer['params'][$key] = $v = $free;
                        $uses[$free]++;
                    }
                    $seen[] = $v;
                }
            }
            unset($layer);
        }
        unset($scene);

        return $plan;
    }

    /**
     * The clips for what is said in [$from, $to]: the phrases overlapping it, the
     * one heard longest first, each phrase's ranking in order.
     *
     * @return list<string>
     */
    public static function clipsFor(array $phrases, float $from, float $to): array
    {
        return collect($phrases)
            ->map(fn ($p) => [$p, min($to, (float) $p['end']) - max($from, (float) $p['start'])])
            ->filter(fn ($x) => $x[1] > 0 && $x[0]['clips'] !== [])
            ->sortByDesc(fn ($x) => $x[1])
            ->flatMap(fn ($x) => $x[0]['clips'])
            ->unique()->values()->all();
    }

    /**
     * A video never freezes on its last frame: a scene showing video clips ends
     * when its shortest clip does, and the rest of its time is filled with other
     * clips, full-frame — the one that shows what is said then ($phrases, see
     * syncVideoClips), else the least shown; never the one just shown. Under a
     * second left over goes to the next scene instead — unless that is the
     * after-intro scene (plain), which is left alone: it is sized to its own
     * video and starts on its line, so the clip holds its last frame there (a
     * half-second cut to another clip read as a glitch before the intro).
     *
     * @param  array<string,float>  $lengths  video id => seconds
     */
    public function fitVideoScenes(array $plan, array $lengths, array $phrases = []): array
    {
        if ($lengths === [] || empty($plan['scenes'])) {
            return $plan;
        }
        $videosIn = function (array $scene) use ($lengths): array {
            $ids = [];
            array_walk_recursive($scene['layers'], function ($v) use (&$ids, $lengths) {
                if (is_string($v) && isset($lengths[$v])) {
                    $ids[$v] = true;
                }
            });

            return array_keys($ids);
        };
        $uses = array_fill_keys(array_keys($lengths), 0);
        foreach ($plan['scenes'] as $scene) {
            foreach ($videosIn($scene) as $id) {
                $uses[$id]++;
            }
        }

        $out = [];
        $carry = null;   // where the next scene starts, when this one handed it its leftover
        $scenes = array_values($plan['scenes']);
        foreach ($scenes as $k => $scene) {
            // The after-intro scene starts on its line: it can't start early, so a leftover before it is filled.
            $nextMoves = isset($scenes[$k + 1]) && empty($scenes[$k + 1]['plain']);
            if ($carry !== null) {
                $scene['start'] = $carry;
                $carry = null;
            }
            $ids = empty($scene['plain']) ? $videosIn($scene) : [];
            if ($ids === []) {
                $out[] = $scene;

                continue;
            }
            [$start, $end] = [(float) $scene['start'], (float) $scene['end']];
            $at = $start + min(array_map(fn ($id) => $lengths[$id], $ids));
            if ($end - $at <= self::CLIP_SHORTFALL || ($end - $at < 1.0 && ! $nextMoves)) {
                $at = $end;   // a few frames short, or nothing can move: held, not a flash of another clip
            }
            $out[] = array_merge($scene, ['end' => min($end, $at)]);
            $prev = $ids;
            // Nor the clip the next scene opens with: it would play twice in a row.
            $next = isset($scenes[$k + 1]) && empty($scenes[$k + 1]['plain']) ? $videosIn($scenes[$k + 1]) : [];
            while ($end - $at > self::EPS) {
                $pool = array_values(array_diff(array_keys($lengths), $prev, $next));
                if (($end - $at < 1.0 && $nextMoves) || $pool === []) {
                    $carry = $at;   // no flash of a clip, no freeze: the next scene starts early
                    break;
                }
                usort($pool, fn ($a, $b) => [$uses[$a], -$lengths[$a]] <=> [$uses[$b], -$lengths[$b]]);
                // What is said over the fill decides; the least shown only when nothing fits.
                $id = self::bestClip(array_values(array_intersect(self::clipsFor($phrases, $at, $end), $pool)), $uses, $lengths, $end - $at, null)
                    ?? self::bestClip(array_values(array_intersect(self::clipsFor($phrases, $at - 3, $end + 3), $pool)), $uses, $lengths, $end - $at, null)
                    ?? $pool[0];
                $uses[$id]++;
                $out[] = [
                    'start' => $at, 'end' => min($end, $at + $lengths[$id]), 'background' => 'ink', 'present' => 'animation',
                    'karaoke' => $scene['karaoke'] ?? true, 'punchWord' => null,
                    'layers' => [['type' => 'image-reveal', 'text' => '', 'params' => ['src' => $id, 'variant' => 'fullscreen']]],
                ];
                $at += $lengths[$id];
                $prev = [$id];
            }
        }
        if ($carry !== null) {
            $out[count($out) - 1]['end'] = max((float) $out[count($out) - 1]['end'], (float) end($plan['scenes'])['end']);
        }
        $plan['scenes'] = $out;

        return $plan;
    }

    /**
     * The user's "after intro" video plays ONCE, full-frame, right after the intro.
     *
     * With its line ("Se queres vender casa, fala com…", said after the intro) found
     * in the voiceover, the video starts on that line; when the video outlasts the
     * line the voice waits after the line for the rest of it. Without the line,
     * the voice waits for the whole video at the pause nearest the intro's end.
     * The reel resumes when the video ends. Later scenes move back by the wait;
     * `voice_gap` tells the render where to open it in the audio and captions.
     * Runs once — a plan that already has the gap is returned as is.
     *
     * @param  string  $src  the video (clips disk path)
     * @param  list<array{word?:string,start:float,end:float}>  $words  the voiceover's words
     */
    public function insertAfterIntro(array $plan, string $src, float $seconds, array $words = [], string $line = ''): array
    {
        $scenes = array_values($plan['scenes'] ?? []);
        if (count($scenes) < 2 || $seconds <= 0 || isset($plan['voice_gap'])) {
            return $plan;
        }
        $min = (float) $scenes[0]['start'] + 1.0;
        $span = $line !== '' ? self::findLine($words, $line) : null;
        if ($span && $span[0] >= $min) {
            [$at, $gapAt] = $span;
            $gap = max(0.0, $seconds - ($gapAt - $at));
        } else {
            $at = $gapAt = self::voiceBreak($words, (float) $scenes[0]['end'], $min);
            $gap = $seconds;
        }
        $videoEnd = $at + $seconds;
        $shift = fn (float $t) => $t >= $gapAt - 1e-6 ? $t + $gap : $t;

        $out = [array_merge($scenes[0], ['end' => $at]), [
            // plain: the renderer shows the video as uploaded — no motion, fade, effect sound or text on top.
            'start' => $at, 'end' => $videoEnd, 'present' => 'animation', 'background' => 'ink', 'punchWord' => null, 'karaoke' => false, 'plain' => true,
            'layers' => [['type' => 'video-intro', 'text' => '', 'params' => ['src' => $src]]],
        ]];
        foreach (array_slice($scenes, 1) as $scene) {
            $end = $shift((float) $scene['end']);
            if ($end <= $videoEnd + self::EPS) {
                continue;   // covered by the video (or the intro, up to it)
            }
            $scene['start'] = max($shift((float) $scene['start']), $videoEnd);
            $scene['end'] = $end;
            $out[] = $scene;
        }
        // What's left of a scene the video cut into is a flash under a second: the next one takes its time.
        if (isset($out[3]) && $out[2]['end'] - $out[2]['start'] < 1.0) {
            $out[3]['start'] = $out[2]['start'];
            array_splice($out, 2, 1);
        }
        $plan['scenes'] = $out;
        $plan['duration'] = (float) ($plan['duration'] ?? end($scenes)['end']) + $gap;
        $plan['voice_gap'] = ['at' => round($gapAt, 3), 'seconds' => round($gap, 3)];

        return $plan;
    }

    /**
     * Undo insertAfterIntro (to retime a re-voiced clip, then insert again). The
     * time the video covered goes to the scene before it.
     *
     * @return array{0:array,1:?string,2:float} [plan without it, the video, its length]
     */
    public function removeAfterIntro(array $plan): array
    {
        $gap = $plan['voice_gap'] ?? null;
        $scenes = array_values($plan['scenes'] ?? []);
        $k = collect($scenes)->search(fn ($s) => ! empty($s['plain']));
        if (! $gap || $k === false || $k === 0) {
            return [$plan, null, 0.0];
        }
        $video = $scenes[$k];
        [$at, $d] = [(float) $gap['at'], (float) $gap['seconds']];
        $unshift = fn (float $t) => $t >= $at + $d - 1e-6 ? $t - $d : min($t, $at);
        array_splice($scenes, $k, 1);
        foreach ($scenes as $i => &$scene) {
            if ($i >= $k) {
                $scene['start'] = $unshift((float) $scene['start']);
                $scene['end'] = $unshift((float) $scene['end']);
            }
        }
        unset($scene);
        $scenes[$k - 1]['end'] = $scenes[$k]['start'] ?? $scenes[$k - 1]['end'];
        $plan['scenes'] = $scenes;
        $plan['duration'] = (float) ($plan['duration'] ?? 0) - $d;
        unset($plan['voice_gap']);

        return [$plan, $video['layers'][0]['params']['src'] ?? null, (float) $video['end'] - (float) $video['start']];
    }

    /**
     * Where $line is said in the transcript: [start of its first word, end of its
     * last], matching words loosely (case, accents, punctuation; 60% of them).
     *
     * @return array{0:float,1:float}|null
     */
    public static function findLine(array $words, string $line): ?array
    {
        $norm = fn (string $w) => preg_replace('/[^a-z0-9]/', '', strtolower(Str::ascii($w)));
        $want = array_values(array_filter(array_map($norm, preg_split('/\s+/u', trim($line)))));
        $have = array_map(fn ($w) => $norm((string) ($w['word'] ?? '')), $words);
        $n = count($want);
        $best = null;
        for ($i = 0; $n > 0 && $i + $n <= count($have); $i++) {
            $hits = count(array_filter(range(0, $n - 1), fn ($k) => $have[$i + $k] === $want[$k]));
            if ($hits >= ceil(0.6 * $n) && ($best === null || $hits > $best[0])) {
                $best = [$hits, $i];
            }
        }

        return $best ? [(float) $words[$best[1]]['start'], (float) $words[$best[1] + $n - 1]['end']] : null;
    }

    /**
     * Where to pause the voiceover: the gap between two words nearest $target
     * (not before $min); a sentence end counts as 1.5 s closer, a comma as
     * 0.5 s, and each second of silence (up to one) as 2 s. No words → $target.
     */
    public static function voiceBreak(array $words, float $target, float $min): float
    {
        $best = null;
        for ($i = 0; $i < count($words) - 1; $i++) {
            $at = ((float) $words[$i]['end'] + (float) $words[$i + 1]['start']) / 2;
            if ($at < $min) {
                continue;
            }
            $w = rtrim((string) ($words[$i]['word'] ?? ''));
            // A long pause is a sentence end too (some transcripts carry no punctuation).
            $pause = min(1.0, (float) $words[$i + 1]['start'] - (float) $words[$i]['end']);
            $score = abs($at - $target) - 2 * $pause - (preg_match('/[.!?…]["»]?$/u', $w) ? 1.5 : (preg_match('/[,;:]$/u', $w) ? 0.5 : 0));
            if ($best === null || $score < $best[0]) {
                $best = [$score, $at];
            }
        }

        return $best[1] ?? max($target, $min);
    }

    /**
     * Guarantee the video CLOSES with an outro effect (a contact card, a logo
     * sign-off). The planner is asked to; this makes it reliable. If the last
     * scene already carries one, nothing changes. Otherwise the outro takes the
     * last scene — all of it when it's short, else its final stretch (a typical
     * scene's length, never under 2.5s so a card can be read) with the rest kept.
     *
     * The outro's inputs (the agent's photo, name, phone…) are left to
     * EffectParamFiller, which picks the photo by DESCRIPTION — the closing
     * scene's picture is usually the house, not the agent. dropSlotDuplicates()
     * then removes that photo from wherever else it was shown.
     *
     * @param  string[]  $outroSlugs
     */
    public function enforceOutro(array $plan, array $outroSlugs): array
    {
        $scenes = array_values(is_array($plan['scenes'] ?? null) ? $plan['scenes'] : []);
        if ($outroSlugs === [] || $scenes === []) {
            return $plan;
        }
        $hasOutro = fn (array $scene): bool => collect(is_array($scene['layers'] ?? null) ? $scene['layers'] : [])
            ->contains(fn ($l) => is_array($l) && in_array($l['type'] ?? null, $outroSlugs, true));
        $i = count($scenes) - 1;
        $last = $scenes[$i];
        if ($hasOutro($last)) {
            return $plan;
        }
        // The planner closed one line early (the card on the price, then «fale com…»):
        // that card runs to the end, rather than a second one replaying it.
        if ($i > 0 && $hasOutro($scenes[$i - 1])) {
            $scenes[$i - 1]['end'] = $last['end'] ?? $scenes[$i - 1]['end'] ?? null;
            $scenes[$i - 1]['transitionOut'] = $last['transitionOut'] ?? $scenes[$i - 1]['transitionOut'] ?? null;
            array_pop($scenes);
            $plan['scenes'] = $scenes;

            return $plan;
        }

        $start = (float) ($last['start'] ?? 0);
        $end = (float) ($last['end'] ?? 0);
        $target = max(2.5, $this->typicalSceneDuration(array_slice($scenes, 0, $i)) ?? 2.5);
        $outroStart = ($end - $start) > $target + self::EPS && $i > 0 ? $end - $target : $start;

        // A leftover too short to register is a flash, not a beat: the outro takes it.
        if ($outroStart - $start < 1.5) {
            $outroStart = $start;
        }

        $outro = array_merge($last, [
            'start' => $outroStart,
            'layers' => [['type' => $outroSlugs[0], 'text' => '', 'params' => []]],
            'punchWord' => null,
        ]);
        if (isset($last['present']) && $last['present'] !== 'animation') {
            $outro['present'] = 'animation'; // full-frame, like the intro
        }

        if ($outroStart > $start + self::EPS) {
            // Keep what the planner put there for the time before the outro.
            array_splice($scenes, $i, 1, [array_merge($last, ['end' => $outroStart]), $outro]);
        } else {
            $scenes[$i] = $outro;
        }
        $plan['scenes'] = $scenes;

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

    /** A clip this much (4 frames) shorter than its scene still covers it: a hold that brief, at a cut, is not seen. */
    private const CLIP_SHORTFALL = 0.15;

    /** The shortest footage shot: under this a room reads as a flash. */
    private const MIN_SHOT = 2.0;

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
     * @param  string[]  $imageEffects  custom effects that show `params.src` — used, in rotation, when
     *                                  image-reveal is off (a project that replaced it with its own)
     */
    public function ensureProvidedImages(array $plan, array $images, ?array $allowedLayers = null, array $imageEffects = []): array
    {
        $scenes = $plan['scenes'] ?? [];
        if (! is_array($scenes) || $scenes === [] || $images === []) {
            return $plan;
        }
        $semReveal = $allowedLayers !== null && ! in_array('image-reveal', $allowedLayers, true);
        if ($semReveal && $imageEffects === []) {
            return $plan; // nothing can show an image — cannot enforce
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
        // Every scene already showing a provided image leaves no slot — the extras
        // then go straight to the split below instead of being dropped.
        $order = $this->placementOrder($scenes, $providedIds);
        $slots = count($order);

        foreach ($unused as $k => $imgId) {
            $idx = $slots > 0 ? $order[$k % $slots] : count($scenes) - 1;
            $imageLayer = $semReveal
                ? ['type' => $imageEffects[$k % count($imageEffects)], 'text' => null, 'params' => ['src' => $imgId]]
                : ['type' => 'image-reveal', 'text' => null, 'params' => ['src' => $imgId, 'variant' => 'fullscreen']];

            if ($k < $slots) {
                // First pass: the image OWNS this scene (keep any ambient underneath).
                $ambient = array_values(array_filter(
                    $scenes[$idx]['layers'] ?? [],
                    fn ($l) => is_array($l) && ($l['type'] ?? '') === 'ambient',
                ));
                $scenes[$idx]['layers'] = array_merge($ambient, [$imageLayer]);
                $scenes[$idx]['punchWord'] = null; // the image carries the frame
            } else {
                // More images than free scenes. Stacking it on a scene hid one
                // full-frame picture behind the other, so give it the second half
                // of the longest scene instead (only-reference clips hit this).
                $longest = null;
                foreach ($scenes as $j => $sc) {
                    $d = (float) ($sc['end'] ?? 0) - (float) ($sc['start'] ?? 0);
                    if ($d >= 3.0 && ($longest === null || $d > (float) ($scenes[$longest]['end'] ?? 0) - (float) ($scenes[$longest]['start'] ?? 0))) {
                        $longest = $j;
                    }
                }
                if ($longest === null) {
                    $scenes[$idx]['layers'][] = $imageLayer; // ponytail: nothing long enough to split — stack as before
                } else {
                    $meio = ((float) $scenes[$longest]['start'] + (float) $scenes[$longest]['end']) / 2;
                    $novo = array_merge($scenes[$longest], ['start' => $meio, 'layers' => [$imageLayer], 'punchWord' => null]);
                    $scenes[$longest]['end'] = $meio;
                    array_splice($scenes, $longest + 1, 0, [$novo]);
                }
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
    /**
     * Swap image FILE NAMES the planner wrote (taken from the instructions — "use
     * main.webp on the intro") for the id of the provided image with that name.
     *
     * @param  array<int,array<string,mixed>>  $images
     */
    public function resolveImageNames(array $plan, array $images): array
    {
        $porNome = [];
        foreach ($images as $i) {
            if (! empty($i['name']) && ! empty($i['id'])) {
                $porNome[mb_strtolower((string) $i['name'])] = $i['id'];
                $porNome[mb_strtolower(pathinfo((string) $i['name'], PATHINFO_FILENAME))] ??= $i['id'];
            }
        }
        if ($porNome === [] || ! is_array($plan['scenes'] ?? null)) {
            return $plan;
        }
        array_walk_recursive($plan['scenes'], function (&$v) use ($porNome) {
            if (is_string($v) && isset($porNome[mb_strtolower($v)])) {
                $v = $porNome[mb_strtolower($v)];
            }
        });

        return $plan;
    }

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
            // An image FILE NAME ("main.webp") that is neither a real id nor a file:
            // the planner copied it from the instructions. Staged as-is it 404s and
            // takes the whole render down.
            $eraLista = array_is_list($node);
            foreach ($node as $k => $v) {
                if (is_string($v) && preg_match('/\.(png|jpe?g|webp|gif|avif|bmp|svg)$/i', $v) && ! $ok($v)) {
                    unset($node[$k]);
                }
            }
            if ($eraLista) {
                $node = array_values($node); // srcs stays a JSON list, not an object
            }
            // A template's other image slots (avatar, logo…) carry an id too; one
            // that looks like an id but isn't a real image would reach <Img> as a
            // bogus URL and fail the render.
            foreach ($node as $k => $v) {
                if (is_string($v) && preg_match('/^img_[a-z0-9]+$/i', $v) && ! $ok($v)) {
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
            // Any image slot counts: a fan filled through `srcs`/`src2…` with no
            // `src` was dropped as empty, taking its photos with it.
            foreach ($p as $k => $v) {
                if (preg_match('/^(src\d*|srcs|images?\d*|photos?\d*|avatar)$/i', (string) $k) && ! empty($v)) {
                    return true;
                }
            }

            return false;
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
