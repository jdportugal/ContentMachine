<?php

namespace App\Services\Clips;

use App\Services\Clips\Api\RunsClaudeCli;
use RuntimeException;

/**
 * "Free SFX": instead of the planner picking each scene's visual from the fixed
 * layer vocabulary, the model WRITES a one-off Remotion component for each scene
 * of one clip. The components are clip-scoped — registered so the renderer can
 * use them, invisible to the planner and to the SFX library.
 *
 * The component contract is EffectGenerator's, verbatim (componentContract()), so
 * a Free-SFX component is an ordinary SFX in every way except who owns it. Only
 * the JSON envelope differs: an ARRAY of components, one per scene, written in a
 * single call so they are designed as a set and share a visual language.
 */
class FreeSfxGenerator
{
    use RunsClaudeCli;

    public function __construct(private EffectGenerator $effects) {}

    /**
     * Write one component per scene.
     *
     * Scenes arrive as {index, start, end, text, hint} — `index` is the scene's
     * position in the plan and is echoed back so a partial answer still lands on
     * the right scenes. Batched because every component is ~2k tokens and thinking
     * counts against CLIPS_MAX_TOKENS: one call for a normal clip, several for a
     * long one, instead of one truncated answer.
     *
     * A batch that fails outright is skipped, not fatal — those scenes keep the
     * layers the planner gave them.
     *
     * @param  array<int,array{index:int,start:float,end:float,text:string,hint:string}>  $scenes
     * @param  array{width?:int,height?:int}  $canvas
     * @return array<int,array{index:int,slug:string,displayName:string,description:string,paramSchema:string,params:array<string,mixed>,tsx:string}>
     */
    public function generate(array $scenes, string $subject, array $canvas = [], array $facts = []): array
    {
        $size = max(1, (int) config('contentmachine.clips.free_sfx_batch', 5));
        $out = [];

        foreach (array_chunk($scenes, $size) as $batch) {
            try {
                $out = array_merge($out, $this->batch($batch, $subject, $canvas, $facts));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $out;
    }

    /**
     * @param  array<int,array{index:int,start:float,end:float,text:string,hint:string}>  $batch
     * @return array<int,array<string,mixed>>
     */
    private function batch(array $batch, string $subject, array $canvas, array $facts): array
    {
        $envelope = $this->runClaude(
            $this->userPrompt($batch, $subject, $facts),
            $this->systemPrompt($canvas),
            ['timeout' => (int) config('contentmachine.clips.llm_timeout', 600), 'useRemotionSkills' => true]
        );

        $data = $this->effects->extractJson((string) ($envelope['result'] ?? ''), $envelope);
        $items = $data['components'] ?? $data;
        if (! is_array($items)) {
            throw new RuntimeException('Free SFX: the model returned no components.');
        }

        $valid = array_column($batch, 'index');
        $out = [];
        foreach ($items as $item) {
            $one = is_array($item) ? $this->accept($item, $valid) : null;
            if ($one !== null) {
                $out[] = $one;
            }
        }

        return $out;
    }

    /**
     * One component from the model's array, or null if it is unusable.
     *
     * Rejections here are per-component on purpose: a batch of five where one
     * broke the design system should ship four bespoke scenes, not none.
     *
     * @param  array<string,mixed>  $item
     * @param  int[]  $valid  scene indices this batch asked about
     * @return array<string,mixed>|null
     */
    private function accept(array $item, array $valid): ?array
    {
        $index = (int) ($item['scene'] ?? -1);
        $tsx = trim((string) ($item['tsx'] ?? ''));
        if (! in_array($index, $valid, true) || $tsx === '') {
            return null;
        }

        try {
            $this->effects->guard($tsx);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }

        return [
            'index' => $index,
            'slug' => '', // assigned by the job, namespaced to the clip
            'displayName' => trim((string) ($item['displayName'] ?? '')) ?: 'Scene animation',
            'description' => trim((string) ($item['description'] ?? '')) ?: 'One-off animation written for this clip.',
            'paramSchema' => is_array($item['paramSchema'] ?? null)
                ? (string) json_encode($item['paramSchema'])
                : (trim((string) ($item['paramSchema'] ?? '')) ?: '{}'),
            'params' => is_array($item['params'] ?? null) ? $item['params'] : [],
            'tsx' => $tsx,
        ];
    }

    /** @param array{width?:int,height?:int} $canvas */
    private function systemPrompt(array $canvas): string
    {
        $contract = $this->effects->componentContract($canvas);

        return <<<PROMPT
You are a senior Remotion + React engineer for the Brand Machine studio. You are given the
SCENES of one short video and you write a BESPOKE animation component for each one — no
library, no presets. Each component is judged on whether it makes THAT scene's point land.

Return a SINGLE JSON object (no markdown, no prose):
{ "components": [ {
  "scene":       the scene's index, copied EXACTLY from the input,
  "displayName": short human label for this animation, e.g. "Ledger slam",
  "description": ONE line describing what it does,
  "paramSchema": a JSON-schema-ish one-liner of the params it reads, e.g. { "value"?: string },
  "params":      the ACTUAL params for this scene, matching that schema — the real numbers,
                 labels and words this scene is about, not placeholders,
  "tsx":         the FULL component source (a string)
} ] }

Return one entry per scene given, in order. Omit a scene only if you truly cannot
animate it — a missing scene keeps its default animation, it is not an error.

# WRITING FOR THE SET
- The components play back to back in ONE video: they must feel like one designer made them.
  Share a rhythm and a spatial logic; vary what is being SAID, not the house style.
- Every scene is DIFFERENT. Do not ship five variations of the same reveal.
- The scene's own duration is `anim.end - anim.start` seconds — time the whole animation to
  it: land the main idea early, hold it, and do not still be animating in when it cuts.
- The spoken words for the scene are given. The animation must show WHAT IS SAID — a number
  said out loud appears, a comparison drawn in words is drawn on screen. Do not invent data.

{$contract}
PROMPT;
    }

    /**
     * @param  array<int,array{index:int,start:float,end:float,text:string,hint:string}>  $batch
     * @param  array<string,mixed>  $facts
     */
    private function userPrompt(array $batch, string $subject, array $facts): string
    {
        $scenes = '';
        foreach ($batch as $s) {
            $dur = round(((float) $s['end']) - ((float) $s['start']), 2);
            $scenes .= "\n- scene {$s['index']} · {$dur}s · spoken: \"{$s['text']}\""
                .($s['hint'] !== '' ? " · the planner would have used: {$s['hint']}" : '');
        }

        $research = $facts !== []
            ? "\n\n=== RESEARCH (real data you may show — never invent numbers) ===\n"
              .(string) json_encode($facts, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : '';

        return "The video is about: {$subject}\n\nSCENES to animate:{$scenes}{$research}\n\n"
            .'Write one component per scene listed above.';
    }
}
