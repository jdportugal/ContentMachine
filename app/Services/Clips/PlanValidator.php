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
    public function validate(array $plan, string $transcriptText = '', bool $overlay = false, ?array $allowed = null): array
    {
        // v2 scene-based plan.
        if (isset($plan['scenes'])) {
            $plan['scenes'] = $this->validateScenes(
                array_values($plan['scenes']),
                (float) ($plan['duration'] ?? 0.0),
                $plan['mode'] ?? 'dense',
                $transcriptText
            );

            if ($overlay) {
                $plan['scenes'] = $this->assignPresentModes($plan['scenes'], $allowed);
            }

            return $plan;
        }

        return $this->validateAnimations($plan);
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
            if (in_array($s['present'] ?? null, $allowed, true)) {
                continue; // planner's choice, and it's allowed
            }
            $hasForeground = ! empty(array_filter(
                $s['layers'] ?? [],
                fn ($l) => ($l['type'] ?? '') !== 'ambient'
            ));
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

    public function validateScenes(array $scenes, float $duration, string $mode, string $transcriptText = ''): array
    {
        $normalizedTranscript = $transcriptText !== '' ? $this->normalize($transcriptText) : '';

        $scenes = array_values(array_filter(array_map(function ($s) use ($duration, $normalizedTranscript) {
            $s['start'] = max(0.0, min((float) ($s['start'] ?? 0), $duration));
            $s['end'] = max(0.0, min((float) ($s['end'] ?? 0), $duration));
            $s['layers'] = $this->capLayers($s);

            // A full-frame image already carries the point → avoid punch-word overlap.
            if (in_array('image-reveal', array_column($s['layers'], 'type'), true)) {
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
            foreach ($this->coverageGaps($scenes, $duration) as [$gs, $ge]) {
                $scenes[] = [
                    'start' => $gs, 'end' => $ge,
                    'background' => 'papyrus', 'transitionIn' => 'crossfade', 'transitionOut' => 'cut',
                    'karaoke' => false, 'punchWord' => null,
                    'layers' => [['type' => 'ambient', 'text' => null, 'params' => []]],
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

        $visual = array_values(array_filter($foreground, fn ($l) => in_array($l['type'] ?? null, self::VISUAL_LAYERS, true)));
        $text = array_values(array_filter($foreground, fn ($l) => ! in_array($l['type'] ?? null, self::VISUAL_LAYERS, true)));

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

    /** Lowercase + strip punctuation + collapse spaces, for verbatim matching. */
    private function normalize(string $s): string
    {
        $s = mb_strtolower($s);
        $s = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $s) ?? $s;
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;

        return trim($s);
    }

    private function validateAnimations(array $plan): array
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

        if ($mode === 'dense') {
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
