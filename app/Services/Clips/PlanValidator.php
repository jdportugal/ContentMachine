<?php

namespace App\Services\Clips;

class PlanValidator
{
    private const EPS = 0.05;

    /**
     * Normalize a plan: clamp animations to [0,duration], drop zero-length ones,
     * sort by start, and — in dense mode — fill gaps with `ambient` so coverage
     * is total. In sparse mode gaps are preserved.
     */
    /**
     * @param  string[]|null  $allowedLayers  Layer types the clip may contain (from
     *                                        EffectLibrary::allowedLayerTypes()). Layers of any other type are stripped
     *                                        and disabled fillers (e.g. a toggled-off `ambient`) are never injected.
     *                                        null = no restriction (all types allowed), used by the pure unit tests.
     */
    public function validate(array $plan, string $transcriptText = '', bool $overlay = false, ?array $allowed = null, ?array $allowedLayers = null): array
    {
        // v2 scene-based plan.
        if (isset($plan['scenes'])) {
            $plan['scenes'] = $this->validateScenes(
                array_values($plan['scenes']),
                (float) ($plan['duration'] ?? 0.0),
                $plan['mode'] ?? 'dense',
                $transcriptText,
                $allowedLayers
            );

            if ($overlay) {
                $plan['scenes'] = $this->assignPresentModes($plan['scenes'], $allowed);
            }

            return $plan;
        }

        return $this->validateAnimations($plan, $allowedLayers);
    }

    /** Whether `ambient` may be injected as a gap/bare filler. */
    private function ambientAllowed(?array $allowedLayers): bool
    {
        return $allowedLayers === null || in_array('ambient', $allowedLayers, true);
    }

    /**
     * Ensure overlay scenes vary how they present against the video. Scenes where
     * the planner already chose a valid `present` are respected; the rest are
     * assigned so the edit intercuts (no-layer → just video; others rotate
     * over / split / animation) instead of everything overlapping.
     */
    private function assignPresentModes(array $scenes, ?array $allowed = null): array
    {
        $allowed = ! empty($allowed) ? array_values(array_intersect(['video', 'over', 'split', 'animation'], $allowed)) : ['video', 'over', 'split', 'animation'];
        if (empty($allowed)) {
            $allowed = ['video'];
        }
        // modes used for scenes that carry a graphic (excluding plain 'video')
        $graphicModes = array_values(array_intersect(['over', 'split', 'animation'], $allowed));
        $fallback = in_array('video', $allowed, true) ? 'video' : $allowed[0];
        $i = 0;

        foreach ($scenes as &$s) {
            $hasForeground = ! empty(array_filter(
                $s['layers'] ?? [],
                fn ($l) => ($l['type'] ?? '') !== 'ambient'
            ));

            // `animation` covers the video with the graphic, so a scene carrying no
            // graphic renders as bare backdrop — the faint em-dash and a punch word,
            // with the video hidden behind nothing. Over/split keep the video on
            // screen, so they stay honest when empty; only this one has to give.
            // Checked BEFORE the planner's choice is honoured, because the planner
            // is exactly who sets `animation` on a scene it then gives no layers.
            if (! $hasForeground && ($s['present'] ?? null) === 'animation') {
                $s['present'] = $fallback;

                continue;
            }

            if (in_array($s['present'] ?? null, $allowed, true)) {
                continue; // planner's choice, and it's allowed
            }
            if (! $hasForeground) {
                $s['present'] = $fallback;
            } elseif (! empty($graphicModes)) {
                $s['present'] = $graphicModes[$i % count($graphicModes)];
                $i++;
            } else {
                $s['present'] = $fallback; // only 'video' allowed → graphics won't show, honour the constraint
            }
        }
        unset($s);

        return $scenes;
    }

    /**
     * Normalize scenes: clamp to [0,duration], drop zero-length, sort by start,
     * and in dense mode fill gaps with an ambient scene so coverage is total.
     */
    private const VISUAL_LAYERS = [
        'timeline', 'bar-chart', 'line-chart', 'pie-chart', 'scatter-chart', 'comparison',
        'bullet-list', 'card', 'terminal', 'diagram', 'seal-stamp', 'fleuron-draw', 'count-up', 'image-reveal',
    ];

    public function validateScenes(array $scenes, float $duration, string $mode, string $transcriptText = '', ?array $allowedLayers = null): array
    {
        $normalizedTranscript = $transcriptText !== '' ? $this->normalize($transcriptText) : '';

        $scenes = array_values(array_filter(array_map(function ($s) use ($duration, $normalizedTranscript, $allowedLayers) {
            $s['start'] = max(0.0, min((float) ($s['start'] ?? 0), $duration));
            $s['end'] = max(0.0, min((float) ($s['end'] ?? 0), $duration));
            // Drop layers whose type is disabled/unknown, so a toggled-off effect
            // can never survive the planner into the render.
            if ($allowedLayers !== null) {
                $s['layers'] = array_values(array_filter(
                    is_array($s['layers'] ?? null) ? $s['layers'] : [],
                    fn ($l) => is_array($l) && in_array($l['type'] ?? '', $allowedLayers, true),
                ));
            }
            $s['layers'] = $this->capLayers($s);

            // A full-frame image (or a custom effect, which draws its own text) already
            // carries the point → avoid punch-word overlap.
            $tipos = array_column($s['layers'], 'type');
            if (in_array('image-reveal', $tipos, true)
                || array_diff($tipos, ['ambient'], array_keys(EffectLibrary::BUILTIN_SAMPLES)) !== []) {
                $s['punchWord'] = null;
            }

            // The punch word must be spoken verbatim — drop it otherwise (it's on-screen text).
            if (! empty($s['punchWord']) && $normalizedTranscript !== ''
                && ! str_contains($normalizedTranscript, $this->normalize($s['punchWord']))) {
                $s['punchWord'] = null;
            }

            return $s;
        }, $scenes), fn ($s) => $s['end'] - $s['start'] > self::EPS));

        usort($scenes, fn ($x, $y) => $x['start'] <=> $y['start']);

        if ($mode === 'dense') {
            // A gap scene needs no foreground layer — the composition starfield shows
            // through. Only add an `ambient` layer if that effect is enabled.
            $gapLayers = $this->ambientAllowed($allowedLayers) ? [['type' => 'ambient', 'text' => null, 'params' => []]] : [];
            foreach ($this->coverageGaps($scenes, $duration) as [$gs, $ge]) {
                $scenes[] = [
                    'start' => $gs, 'end' => $ge,
                    'background' => 'papyrus', 'transitionIn' => 'crossfade', 'transitionOut' => 'cut',
                    'karaoke' => false, 'punchWord' => null,
                    'layers' => $gapLayers,
                ];
            }
            usort($scenes, fn ($x, $y) => $x['start'] <=> $y['start']);
        }

        return array_values($scenes);
    }

    /**
     * Keep at most one foreground layer per scene (prevents overlap). Prefer a
     * data-visual over text; drop text layers entirely when a punch word already
     * carries the text. An optional ambient layer is preserved underneath.
     */
    private function capLayers(array $scene): array
    {
        $layers = array_values(array_filter($scene['layers'] ?? [], 'is_array'));

        $ambient = array_values(array_filter($layers, fn ($l) => ($l['type'] ?? null) === 'ambient'));
        $foreground = array_values(array_filter($layers, fn ($l) => ($l['type'] ?? null) !== 'ambient'));

        $visual = array_values(array_filter($foreground, fn ($l) => $this->isVisual($l['type'] ?? null)));
        $text = array_values(array_filter($foreground, fn ($l) => ! $this->isVisual($l['type'] ?? null)));

        $hasPunch = ! empty($scene['punchWord']);
        $chosen = $visual[0] ?? ($hasPunch ? null : ($text[0] ?? null));

        $out = [];
        if (! empty($ambient)) {
            $out[] = $ambient[0];
        }
        if ($chosen !== null) {
            $out[] = $chosen;
        }

        return $out;
    }

    /**
     * A visual layer, as opposed to a text one. Every CUSTOM effect is a visual —
     * a photo effect, a listing card. Counting them as text got them dropped from
     * any scene with a punch word (nearly all of them), so a planner that used the
     * project's own effects handed over a plan with no layers left at all.
     */
    private function isVisual(?string $type): bool
    {
        return in_array($type, self::VISUAL_LAYERS, true)
            || ($type !== null && ! array_key_exists($type, EffectLibrary::BUILTIN_SAMPLES));
    }

    /** Lowercase + strip punctuation + collapse spaces, for verbatim matching. */
    private function normalize(string $s): string
    {
        $s = mb_strtolower($s);
        $s = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $s) ?? $s;
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;

        return trim($s);
    }

    private function validateAnimations(array $plan, ?array $allowedLayers = null): array
    {
        $duration = (float) ($plan['duration'] ?? 0.0);
        $mode = $plan['mode'] ?? 'dense';
        $anims = array_values($plan['animations'] ?? []);

        $anims = array_values(array_filter(array_map(function ($a) use ($duration) {
            $a['start'] = max(0.0, min((float) $a['start'], $duration));
            $a['end'] = max(0.0, min((float) $a['end'], $duration));
            $a['params'] = $a['params'] ?? [];
            $a['text'] = $a['text'] ?? null;

            return $a;
        }, $anims), fn ($a) => $a['end'] - $a['start'] > self::EPS));

        usort($anims, fn ($x, $y) => $x['start'] <=> $y['start']);

        if ($mode === 'dense' && $this->ambientAllowed($allowedLayers)) {
            foreach ($this->coverageGaps($anims, $duration) as [$gs, $ge]) {
                $anims[] = [
                    'start' => $gs, 'end' => $ge,
                    'primitive' => 'ambient', 'text' => null, 'params' => [],
                ];
            }
            usort($anims, fn ($x, $y) => $x['start'] <=> $y['start']);
        }

        $plan['animations'] = array_values($anims);

        return $plan;
    }

    /**
     * @return array<int,array{0:float,1:float}> list of [start,end] uncovered spans
     */
    public function coverageGaps(array $animations, float $duration): array
    {
        $intervals = array_map(fn ($a) => [(float) $a['start'], (float) $a['end']], $animations);
        usort($intervals, fn ($x, $y) => $x[0] <=> $y[0]);

        $gaps = [];
        $cursor = 0.0;
        foreach ($intervals as [$s, $e]) {
            if ($s - $cursor > self::EPS) {
                $gaps[] = [round($cursor, 3), round($s, 3)];
            }
            $cursor = max($cursor, $e);
        }
        if ($duration - $cursor > self::EPS) {
            $gaps[] = [round($cursor, 3), round($duration, 3)];
        }

        return $gaps;
    }
}
