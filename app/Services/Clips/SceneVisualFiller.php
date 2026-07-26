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

        return match ($layer['type'] ?? '') {
            'image-reveal' => ! empty($p['src']),
            'timeline', 'bullet-list' => ! empty($p['items']),
            'bar-chart' => ! empty($p['bars']),
            'line-chart' => ! empty($p['series']),
            'pie-chart' => ! empty($p['slices']),
            'scatter-chart' => ! empty($p['points']),
            'diagram' => ! empty($p['nodes']),
            'terminal' => ! empty($p['lines']),
            'comparison' => ! empty($p['left']) || ! empty($p['right']),
            'card' => ! empty($p['title']) || ! empty($p['lines']),
            default => true, // text / ornament layers (kinetic-text, seal-stamp, ambient, count-up, …)
        };
    }

    /** Any scene still without a foreground layer gets ambient motion (never a blank frame). */
    public function fallbackAmbient(array $plan): array
    {
        $scenes = $plan['scenes'] ?? [];
        if (! is_array($scenes)) {
            return $plan;
        }
        foreach ($scenes as &$scene) {
            if ($this->hasForeground($scene)) {
                continue;
            }
            $scene['layers'] = [['type' => 'ambient', 'text' => null, 'params' => []]];
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
