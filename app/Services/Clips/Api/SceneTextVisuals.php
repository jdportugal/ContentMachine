<?php

namespace App\Services\Clips\Api;

use App\Services\Clips\EffectLibrary;
use App\Services\Clips\ImageRequests;
use App\Services\Clips\SceneVisualFiller;
use App\Services\Projects\ProjectLanguage;
use Illuminate\Support\Facades\Log;

/**
 * Turns the image suggestions the user DECLINED into non-image scenes: the words
 * spoken in each scene become a card / bullet-list / comparison / diagram — the
 * same vocabulary the planner uses when image generation is off. One LLM call for
 * all of them; anything it does not answer for just loses its image layer (the
 * bare-scene passes in SceneVisualFiller then cover that stretch).
 */
class SceneTextVisuals
{
    use RunsClaudeCli;

    /** Non-image layer types a declined image scene may become. */
    private const TEXT_TYPES = ['card', 'bullet-list', 'comparison', 'diagram', 'timeline', 'terminal'];

    /**
     * @param  string[]  $keys  ImageRequests keys the user chose to show as text instead
     * @param  array<string,mixed>  $transcript
     */
    public function replace(array $plan, array $keys, array $transcript): array
    {
        $scenes = $plan['scenes'] ?? [];
        if ($keys === [] || ! is_array($scenes) || $scenes === []) {
            return $plan;
        }

        // The scenes to convert, with what is said in each (already in the spoken
        // language — the LLM writes the visual in the PROJECT's language).
        $filler = app(SceneVisualFiller::class);
        $targets = [];
        foreach ($scenes as $i => $scene) {
            foreach ($scene['layers'] ?? [] as $layer) {
                $key = ImageRequests::layerKey($layer);
                if ($key !== '' && in_array($key, $keys, true)) {
                    $targets[$i] = $filler->spokenText($transcript, (float) ($scene['start'] ?? 0), (float) ($scene['end'] ?? 0));
                    break;
                }
            }
        }
        if ($targets === []) {
            return $plan;
        }

        $layers = $this->askForLayers($targets);

        foreach ($targets as $i => $_) {
            $scenes[$i]['layers'] = array_values(array_filter(
                $scenes[$i]['layers'] ?? [],
                fn ($l) => ! (is_array($l) && ImageRequests::layerKey($l) !== ''),
            ));
            if (isset($layers[$i])) {
                $scenes[$i]['layers'][] = $layers[$i];
            }
        }
        $plan['scenes'] = $scenes;

        return $plan;
    }

    /**
     * Build the data for scenes whose animation the USER picked in the editor.
     *
     * Swapping a scene to a timeline/diagram/chart leaves it with no items, so it
     * renders its empty-state placeholder — the grey em-dash. Only the planner
     * writes that data, and the scene editor has no field for it, so this asks
     * for it from what is spoken in the scene, in the type the user chose.
     *
     * @param  array<int,string>  $typeByScene  scene index => chosen layer type
     * @param  array<string,mixed>  $transcript
     */
    public function buildChosen(array $plan, array $typeByScene, array $transcript): array
    {
        $scenes = $plan['scenes'] ?? [];
        if ($typeByScene === [] || ! is_array($scenes)) {
            return $plan;
        }

        $filler = app(SceneVisualFiller::class);
        $targets = [];
        foreach ($typeByScene as $i => $type) {
            if (! isset($scenes[$i])) {
                continue;
            }
            $targets[$i] = $filler->spokenText(
                $transcript,
                (float) ($scenes[$i]['start'] ?? 0),
                (float) ($scenes[$i]['end'] ?? 0),
            );
        }

        foreach ($this->askForLayers($targets, $typeByScene) as $i => $layer) {
            // Keep the scene's other layers (an ambient backdrop); replace the first.
            $scenes[$i]['layers'][0] = $layer;
        }
        $plan['scenes'] = $scenes;

        return $plan;
    }

    /**
     * Ask for one non-image layer per scene. Returns scene index => layer; scenes
     * the model skipped (or everything, when there is no LLM) are simply absent.
     *
     * @param  array<int,string>  $targets  scene index => spoken words
     * @param  array<int,string>  $forced  scene index => the type that scene MUST use
     * @return array<int,array<string,mixed>>
     */
    private function askForLayers(array $targets, array $forced = []): array
    {
        if (config('contentmachine.clips.driver') !== 'api') {
            return []; // no LLM configured — the scenes just lose their image layer
        }

        $allowed = array_values(array_intersect(self::TEXT_TYPES, app(EffectLibrary::class)->allowedLayerTypes()));
        // A type the user picked by hand is allowed by that act — the allow-list
        // steers the planner's own choices, it is not a veto on the editor's.
        $allowed = array_values(array_unique(array_merge($allowed, array_values($forced))));
        if ($allowed === []) {
            return [];
        }

        $language = ProjectLanguage::name();
        $list = json_encode(
            array_map(
                fn (int $i, string $spoken) => array_filter([
                    'scene' => $i,
                    'spoken' => $spoken,
                    'must_use_type' => $forced[$i] ?? null,
                ], fn ($v) => $v !== null),
                array_keys($targets),
                $targets,
            ),
            JSON_UNESCAPED_UNICODE,
        );
        $types = implode(', ', $allowed);

        $prompt = <<<PROMPT
For each scene below, build ONE visual layer that illustrates the point spoken there,
WITHOUT any image (the user declined the picture for these scenes).

Use ONLY these layer types: [{$types}]. Parameter schemas:
- card:        { "title": str, "lines"?: [str] }
- bullet-list: { "title"?: str, "items": [str] }
- comparison:  { "left": { "title": str, "points": [str] }, "right": { "title": str, "points": [str] } }
- diagram:     { "title"?: str, "layout"?: "vertical"|"cycle", "nodes": [{ "label": str }] }
- timeline:    { "items": [{ "label": str, "sublabel"?: str, "highlight"?: bool }], "caption"?: str }
- terminal:    { "lines": [str] }

RULES:
- Write ALL text in {$language}, however the spoken text is written.
- The subtitles already show the speech: DISTIL the point, never copy the sentence.
- SHORT text: titles up to 4 words, lines/items up to 6 words, at most 4 items.
- Never invent numbers or facts that are not in what is said.
- Pick the type that fits the point (a list of steps → bullet-list, two sides → comparison,
  a flow → diagram, a chronology → timeline, one idea/definition → card).
- A scene carrying "must_use_type" MUST use exactly that type — the user chose it.
  Shape what is said into it; never substitute a type you think fits better.

Return ONLY JSON (no markdown), one entry per scene, keyed by the scene number:
{ "<scene>": { "type": "<one of the types>", "params": { … } } }

SCENES:
{$list}
PROMPT;

        try {
            $envelope = $this->runClaude($prompt, null, ['maxTurns' => 1]);
            $decoded = $this->decode((string) ($envelope['result'] ?? ''));
        } catch (\Throwable $e) {
            Log::warning('Text visuals for declined images failed: '.$e->getMessage());

            return [];
        }

        $out = [];
        foreach ($targets as $i => $_) {
            $entry = $decoded[(string) $i] ?? null;
            $type = is_array($entry) ? ($entry['type'] ?? null) : null;
            $params = is_array($entry) ? ($entry['params'] ?? null) : null;
            $exigido = $forced[$i] ?? null;
            $ok = is_string($type) && ($exigido === null ? in_array($type, $allowed, true) : $type === $exigido);
            if ($ok && is_array($params) && $params !== []) {
                $out[$i] = ['type' => $type, 'text' => null, 'params' => $params];
            }
        }

        return $out;
    }

    /** @return array<string,mixed> */
    private function decode(string $content): array
    {
        $content = trim($content);
        if (preg_match('/```(?:json)?\s*(.*?)```/s', $content, $m)) {
            $content = trim($m[1]);
        }
        $start = strpos($content, '{');
        $end = strrpos($content, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $content = substr($content, $start, $end - $start + 1);
        }
        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : [];
    }
}
