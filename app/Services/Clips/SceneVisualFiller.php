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
     * Remove layers that would render an empty-state placeholder: an image-reveal
     * with no image (generation failed / no src) or a chart/diagram with no data.
     * Those show a striped block or a faint "—" and look broken. Run AFTER image
     * generation, so failed images are cleaned up and the scene can fall back.
     */
    public function dropDeadLayers(array $plan): array
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
                fn ($l) => is_array($l) && $this->layerHasContent($l),
            ));
        }
        unset($scene);
        $plan['scenes'] = $scenes;

        return $plan;
    }

    /** True unless the layer would render its empty-state placeholder. */
    private function layerHasContent(array $layer): bool
    {
        $p = is_array($layer['params'] ?? null) ? $layer['params'] : [];
        $text = $layer['text'] ?? null;

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

    private function hasForeground(array $scene): bool
    {
        return (bool) array_filter(
            $scene['layers'] ?? [],
            fn ($l) => is_array($l) && ($l['type'] ?? '') !== 'ambient',
        );
    }

    /** The words spoken within [start,end], as a short prompt seed. */
    private function spokenText(array $transcript, float $start, float $end): string
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
