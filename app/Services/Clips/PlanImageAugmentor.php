<?php

namespace App\Services\Clips;

/**
 * Fulfils the planner's image-generation requests: every image-reveal layer that
 * carries a `params.generate` prompt (and no provided `src`) gets an on-brand
 * image made by Nano Banana, appended to the clip's images, and its `src` set to
 * the new id. Directives are always stripped from the final plan; identical
 * prompts are generated once; the count is capped.
 */
class PlanImageAugmentor
{
    public function __construct(private ClipImageGenerator $generator) {}

    /**
     * @param  array<string,mixed>  $plan  the scenes plan
     * @param  array<int,array<string,mixed>>  $images  existing clip images
     * @return array{plan:array<string,mixed>,images:array<int,array<string,mixed>>}
     */
    public function augment(array $plan, array $images, string $style, int $max): array
    {
        $scenes = $plan['scenes'] ?? [];
        if (! is_array($scenes) || $scenes === []) {
            return ['plan' => $plan, 'images' => $images];
        }

        $configured = $this->generator->configured();
        $byPrompt = [];   // prompt => generated image id (dedup)
        $generated = 0;

        foreach ($scenes as &$scene) {
            if (! isset($scene['layers']) || ! is_array($scene['layers'])) {
                continue;
            }
            foreach ($scene['layers'] as &$layer) {
                if (($layer['type'] ?? null) !== 'image-reveal' || ! is_array($layer['params'] ?? null)) {
                    continue;
                }
                $params = &$layer['params'];
                $prompt = is_string($params['generate'] ?? null) ? trim($params['generate']) : '';
                unset($params['generate']); // never keep the directive in the final plan

                // Nothing to make, or a provided image already fills it.
                if ($prompt === '' || ! empty($params['src'])) {
                    unset($params);

                    continue;
                }
                if (! $configured || $generated >= $max) {
                    unset($params);

                    continue;
                }
                if (isset($byPrompt[$prompt])) {
                    $params['src'] = $byPrompt[$prompt];
                    unset($params);

                    continue;
                }

                $entry = $this->generator->generate($prompt, $style);
                if ($entry !== null) {
                    $images[] = $entry;
                    $params['src'] = $entry['id'];
                    $byPrompt[$prompt] = $entry['id'];
                    $generated++;
                }
                unset($params);
            }
            unset($layer);
        }
        unset($scene);

        $plan['scenes'] = $scenes;

        return ['plan' => $plan, 'images' => $images];
    }
}
