<?php

namespace App\Services\Clips;

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
     * @return array<int,array{key:string,prompt:string}>
     */
    public static function collect(array $plan): array
    {
        $out = [];
        foreach ($plan['scenes'] ?? [] as $scene) {
            foreach ($scene['layers'] ?? [] as $layer) {
                $prompt = self::pendingPrompt($layer);
                if ($prompt !== '') {
                    $out[self::key($prompt)] = ['key' => self::key($prompt), 'prompt' => $prompt];
                }
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
                $prompt = self::pendingPrompt($layer);
                $id = $prompt === '' ? null : ($uploads[self::key($prompt)] ?? null);
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
    private static function pendingPrompt(mixed $layer): string
    {
        if (! is_array($layer) || ($layer['type'] ?? null) !== 'image-reveal' || ! empty($layer['params']['src'])) {
            return '';
        }
        $prompt = $layer['params']['generate'] ?? null;

        return is_string($prompt) ? trim($prompt) : '';
    }
}
