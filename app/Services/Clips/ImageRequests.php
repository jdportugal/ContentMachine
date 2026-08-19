<?php

namespace App\Services\Clips;

use Illuminate\Support\Str;

/**
 * The planner leaves image-reveal layers carrying a `params.generate` prompt (and
 * no `src`) — the images it wants made. Before those get generated we let the user
 * upload their own for any suggestion; whatever they skip is generated as before.
 * This gathers the distinct suggestions and applies the user's uploads onto the
 * plan. Suggestions are deduped by prompt (identical prompts share one image), so
 * the key is a stable hash of the prompt.
 */
final class ImageRequests
{
    public static function key(string $prompt): string
    {
        return substr(md5(trim($prompt)), 0, 8);
    }

    /**
     * Distinct pending image suggestions in the plan.
     *
     * Each carries a `label` — what to show the user. The planner writes its image
     * prompts in ENGLISH (that is what the image model wants), so with a transcript
     * the label is instead the words spoken in that scene: the suggestion reads in
     * the video's own language. Without one it falls back to the prompt.
     *
     * A suggestion with a `site` is not an image to make but a page to FILM — the
     * planner named a real website and the studio scroll-captures it to a video.
     *
     * @return array<int,array{key:string,prompt:string,label:string,site:?string}>
     */
    public static function collect(array $plan, array $transcript = [], ?SceneVisualFiller $filler = null): array
    {
        $out = [];
        foreach ($plan['scenes'] ?? [] as $scene) {
            foreach ($scene['layers'] ?? [] as $layer) {
                $key = self::layerKey($layer);
                if ($key === '' || isset($out[$key])) {
                    continue;
                }
                $site = self::pendingSite($layer);
                $prompt = $site !== '' ? $site : self::pendingPrompt($layer);
                $spoken = $transcript !== [] && $filler !== null
                    ? $filler->spokenText($transcript, (float) ($scene['start'] ?? 0), (float) ($scene['end'] ?? 0))
                    : '';
                $out[$key] = [
                    'key' => $key,
                    'prompt' => $prompt,
                    'label' => $spoken !== '' ? $spoken : trim(Str::after($prompt, 'Illustrate this moment:')),
                    'site' => $site !== '' ? $site : null,
                ];
            }
        }

        return array_values($out);
    }

    /**
     * Set `src` on every suggestion the user uploaded an image for (key => image id),
     * so PlanImageAugmentor skips it and generates only the rest.
     *
     * @param  array<string,string>  $uploads
     */
    public static function applyUploads(array $plan, array $uploads): array
    {
        if ($uploads === [] || ! is_array($plan['scenes'] ?? null)) {
            return $plan;
        }
        foreach ($plan['scenes'] as &$scene) {
            if (! is_array($scene['layers'] ?? null)) {
                continue;
            }
            foreach ($scene['layers'] as &$layer) {
                $key = self::layerKey($layer);
                $id = $key === '' ? null : ($uploads[$key] ?? null);
                if ($id) {
                    $layer['params']['src'] = $id;
                }
            }
            unset($layer);
        }
        unset($scene);

        return $plan;
    }

    /** The generate prompt of an unfulfilled image-reveal layer, or '' if none. */
    public static function pendingPrompt(mixed $layer): string
    {
        if (! is_array($layer) || ($layer['type'] ?? null) !== 'image-reveal' || ! empty($layer['params']['src'])) {
            return '';
        }
        $prompt = $layer['params']['generate'] ?? null;

        return is_string($prompt) ? trim($prompt) : '';
    }

    /** The website URL an unfulfilled image-reveal layer wants filmed, or '' if none. */
    public static function pendingSite(mixed $layer): string
    {
        if (! is_array($layer) || ($layer['type'] ?? null) !== 'image-reveal' || ! empty($layer['params']['src'])) {
            return '';
        }
        $site = $layer['params']['site'] ?? null;

        return is_string($site) ? trim($site) : '';
    }

    /** The suggestion key an unfulfilled layer belongs to ('' if it needs nothing). Site wins over generate. */
    public static function layerKey(mixed $layer): string
    {
        $site = self::pendingSite($layer);
        if ($site !== '') {
            return self::key('site:'.$site);
        }
        $prompt = self::pendingPrompt($layer);

        return $prompt === '' ? '' : self::key($prompt);
    }
}
