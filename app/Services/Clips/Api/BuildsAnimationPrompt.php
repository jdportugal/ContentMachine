<?php

namespace App\Services\Clips\Api;

use App\Services\Clips\BackgroundLibrary;
use App\Services\Clips\EffectLibrary;
use App\Services\DesignSystem\DesignSystemRepository;

/**
 * Shared prompt-building + output-sanitizing for LLM animation planners
 * (OpenAI and Claude). Produces the scene-based plan (v2): a list of scenes,
 * each with a background, transitions, optional karaoke + punch word, and
 * layered elements drawn from the primitive/visualization vocabulary.
 */
trait BuildsAnimationPrompt
{
    protected array $backgrounds = ['papyrus', 'vellum', 'ink', 'video'];

    protected array $transitions = ['cut', 'crossfade', 'whip', 'slide', 'zoom'];

    protected array $presents = ['animation', 'over', 'video', 'split'];

    protected function systemPrompt(string $mode, bool $overlay = false, array $allowedPresents = [], bool $canGenerateImages = true): string
    {
        $style = @file_get_contents(config('contentmachine.clips.style_md')) ?: '';
        $designRepo = app(DesignSystemRepository::class);
        $design = $designRepo->read();
        $designBlock = trim($design) !== ''
            ? "\n\n=== DESIGN SYSTEM (brand identity — follow it in ALL scenes) ===\n".$design
            : '';
        // Also hand the LLM the CONCRETE extracted tokens (the exact palette, fonts and
        // treatment the renderer will apply), so scene choices line up with the render.
        $tokens = $designRepo->readTokens();
        $tokensBlock = $tokens
            ? "\n\n=== EXTRACTED DESIGN TOKENS (the concrete palette/fonts/style the renderer uses) ===\n"
                .json_encode($tokens, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : '';
        $customBlock = app(EffectLibrary::class)->promptBlock();
        $backgroundBlock = app(BackgroundLibrary::class)->promptBlock();
        $layers = implode(', ', $this->allLayerTypes());
        // Intro effects: the FIRST scene should open with one of these (if any are marked).
        $introSlugs = app(EffectLibrary::class)->introSlugs();
        $introBlock = empty($introSlugs) ? '' : "\n# OPENING (INTRO)\n"
            .'The FIRST scene MUST open the video with one of these INTRO effects as its main layer '
            .'(type = one of them), chosen to fit the topic: ['.implode(', ', $introSlugs).']. '
            ."Give it a short hook title, keep it brief, then move on to the content.\n";
        $allowed = ! empty($allowedPresents) ? array_values(array_intersect($this->presents, $allowedPresents)) : $this->presents;
        $allowedLine = 'USE ONLY these "present" values: ['.implode(', ', $allowed).'] — never others.';
        $rule = $overlay
            ? "\n".$allowedLine."\n".<<<'R'
                VIDEO+ANIMATIONS MODE: there is a background VIDEO that plays from start to finish. The scenes cover
                100% of the duration (no gaps) and each scene picks a "present" field (how it presents relative to the video):
                  - "video"     → show only the original video (with karaoke subtitles). Use for the person speaking / parts with no data to add.
                  - "over"      → overlay an animation/chart ON TOP of the video (transparent background).
                  - "split"     → animation on TOP, video on the BOTTOM (to show data next to the person).
                  - "animation" → FULL-SCREEN animation (hides the video) when the chart should dominate.
                MANDATORY: ALL scenes HAVE the "present" field. INTERLEAVE the modes like a good editor —
                DO NOT put everything in "over". Target distribution: mostly "video"; use "over" for quick charts,
                "split" when you show data next to the person, and "animation" (full screen) for 1-2 strong moments.
                In "video"/"over"/"split" scenes do not use a graphic "background" (the video is the background). karaoke=true for most.
                R
            : "ANIMATION MODE (animation only): the scenes cover 100% of the duration, no gaps. Backgrounds 'papyrus'/'vellum'/'ink'. Do not use 'present'.\n"
                .'THERE IS NO BACKGROUND VIDEO: a scene with no layer is a BLANK screen with only a caption. So carry a visual layer whenever you can — a chart/card/diagram/timeline when there is a real point, '
                .($canGenerateImages
                    ? 'and OTHERWISE an image-reveal with "generate" describing an image of what is being said.'
                    : 'or a card/bullet-list/diagram/comparison built from the point (you CANNOT create images — never use image-reveal without a PROVIDED src). EVERY scene MUST carry a visual layer: when no chart/data fits, distil the spoken point into a card (short title + 1–2 supporting lines) or a bullet-list. NEVER leave a scene as bare karaoke.');

        // Image guidance + image-reveal schema depend on whether the studio can
        // actually generate images (kie credits). No credits → provided images
        // and non-image visuals only.
        $imageGuidance = $canGenerateImages
            ? <<<'G'
                - IF IMAGES are provided, you MUST use each one in at least one scene (layer "image-reveal" with params.src = image id), unless clearly irrelevant.
                - GENERATE AN IMAGE whenever a scene talks about something CONCRETE or VISUAL (a person, place, object, product, situation, metaphor) and no provided image fits: add an "image-reveal" layer with "generate": "<a vivid, specific description of the image to CREATE>" (instead of "src"). Describe it in ENGLISH, concrete and photographic — subject, setting, mood, lighting — and NEVER ask for any text, words, letters or logos in the image.
                - BE VISUAL AND DENSE: most scenes should SHOW something tied to the EXACT words spoken there — a generated image, a provided image, a chart, a diagram or a card. Aim for a fresh, RELEVANT visual and vary the motion so the video never feels static.
                G
            : <<<'G'
                - IF IMAGES are provided, you MUST use each one in at least one scene (layer "image-reveal" with params.src = image id), unless clearly irrelevant.
                - IMAGE GENERATION IS UNAVAILABLE: you CANNOT create new images, so do NOT use "generate" and do NOT use image-reveal without a PROVIDED src. For a concrete point, build a NON-image visual instead — card, bullet-list, diagram, comparison, timeline, or a chart when the point is quantitative — from what is said. EVERY scene MUST carry a visual layer: when no chart/data fits, distil the spoken words into a card (short title + 1–2 supporting lines) or a bullet-list. NEVER leave a scene bare.
                - BE VISUAL: give EVERY scene a visual tied to the EXACT words spoken there — chart/card/diagram/bullet-list — and vary the motion so no scene is empty.
                G;

        $imageRevealSchema = $canGenerateImages
            ? '- image-reveal:{ "src"?: "<id of a PROVIDED image>", "generate"?: "<describe an image to CREATE when none provided fits>", "caption"?: str, "variant"?: "fullscreen"|"drop-float"|"rise"|"zoom"|"slide"|"pan"|"framed", "direction"?: "left"|"right" }'."\n"
                .'  - Use EITHER "src" (a provided image id) OR "generate" (a description → AI image), never both. variant controls the motion; prefer "fullscreen"/"pan" and VARY it across scenes.'
            : '- image-reveal:{ "src": "<id of a PROVIDED image>", "caption"?: str, "variant"?: "fullscreen"|"drop-float"|"rise"|"zoom"|"slide"|"pan"|"framed", "direction"?: "left"|"right" }'."\n"
                .'  - Use ONLY with a PROVIDED image id (src) — image generation is OFF, there is no "generate". VARY the variant across scenes.';

        // What to do with a scene that has no natural chart/data visual. A lone big
        // word is never a whole scene; with images off the fallback is a card/list
        // (never bare), with images on it is a generated image.
        $bareRule = $canGenerateImages
            ? 'If a scene has no real visual, give it an image — never a lone big word, never a bare caption-only frame.'
            : 'If a scene has no chart/data visual, build a card or bullet-list from the spoken point — never a lone big word, and NEVER a bare caption-only frame.';

        return <<<PROMPT
You are the clip director of the Brand Machine studio. You plan the video as a sequence of SCENES.
You ALWAYS return a JSON object with the "scenes" key: a list of scenes
{start, end, background, transitionIn, transitionOut, karaoke, punchWord, layers}.
Times in seconds (float). No markdown or explanations — JSON only.

# SCENE
- present (only in VIDEO+ANIMATIONS): one of [video, over, split, animation] — see rule above.
- background: one of [papyrus, vellum, ink, video]. ('ink' = dark background.)
- transitionIn / transitionOut: one of [cut, crossfade, whip, slide, zoom]. VARY the transitions between scenes
  ('whip' energetic, 'crossfade' smooth, 'slide' slides from below, 'zoom' moves closer). Avoid repeating the same one.
- karaoke: true to show the word-by-word synced subtitles (for spoken/presenter segments).
- punchWord: a short word/expression for EMPHASIS (italic serif), or null. Use it at key moments.
- layers: list of elements {type, text, params}. type ∈ {$layers}.

# LANGUAGE
Write ALL visible text (punchWord, text and labels in params) in the SAME language as the transcript (given below). Do not translate.
{$introBlock}

# CLIP TYPE — CLASSIFY FIRST (like a professional editor)
Before planning, infer the TYPE of clip from the transcript and adapt the visual vocabulary.
Different types call for different animations and information:
- TUTORIAL / DEMONSTRATION (step-by-step, "how to"): terminal (commands/code),
  bullet-list of the STEPS in the spoken order, diagram of the flow/process, card for a definition,
  image-reveal of the screen/UI. DO NOT add market shares, version timelines or
  statistics — they are interesting but IRRELEVANT to a tutorial.
- EXPLAINER / EDUCATIONAL (concepts, whys): diagram, comparison, bullet-list, card;
  timeline only if there is a real chronology; charts only when the point is quantitative.
- NEWS / DATA (numbers, trends, market): this is WHERE the RESEARCH charts
  belong — bar/line/pie/scatter/timeline depending on the data.
- STORY / OPINION (account, thesis): mostly video/karaoke + punchWord; ornaments
  (seal-stamp, fleuron) for rhythm; visualizations very sparingly. DO NOT force data.
- TIPS / LIST: one bullet-list or card per tip/point.

# GOLDEN RULE 1 — RELEVANCE IS LAW
Each layer MUST illustrate or reinforce EXACTLY what is being said in that scene.
NEVER add information just because it is interesting — if it does not serve what is spoken THERE, it does not go in.
When nothing genuinely clarifies the moment, leave the scene as plain video/karaoke (or a minimal
card): an honest, clean scene is worth more than a decorative chart out of context.

# GOLDEN RULE 2 — VISUAL, NOT TEXT
The karaoke already shows the SPOKEN WORDS. NEVER repeat the speech in text layers.
When you ADD a layer, it gives VISUAL CONTEXT to the spoken point (a step, a diagram,
a data point that the RESEARCH confirms) — it does not describe what is said.
- Choose a VISUALIZATION as the main layer WHEN it clarifies the point (not by default).
  Fit the type to the CLIP TYPE above and VARY them:
  "timeline" (evolution/versions over time, most recent with highlight:true),
  "bar-chart" (compare discrete quantities),
  "line-chart" (trend/evolution — SEVERAL lines; TWO Y axes when scales/units differ),
  "pie-chart" (proportions/percentages/market share),
  "scatter-chart" (compare items on TWO axes/dimensions — e.g. price vs performance),
  "comparison" (two sides), "bullet-list" (facts/steps), "card" (one fact/definition),
  "terminal" (simulate TYPING in a terminal — commands/code appearing letter by letter, 'ink' background),
  "diagram" (schematic with nodes linked by ARROWS — flows, processes, relationships, cycles; nodes can have images).
- DO NOT always use the same chart type — alternate bar/line/pie/timeline/diagram depending on the data.
- The RESEARCH is OPTIONAL RAW MATERIAL, not a quota to fill: use a fact ONLY when it directly
  reinforces what is said in that scene. Ignore (do not illustrate) the facts that do not fit what
  is being spoken, however interesting they may be.
- RELIABLE DATA: use ONLY numbers/facts that come from the RESEARCH. NEVER invent values. If there is
  no reliable numeric data, use qualitative visualizations (bullet-list, comparison, diagram, card).
- VERTICAL FORMAT (9:16 portrait): prefer stacking top/bottom, NOT side by side. Diagrams in
  "vertical" or "cycle" layout (avoid "horizontal"). Use the width for LARGE TEXT.
- SHORT text everywhere: labels ≤ 3 words, "comparison" points ≤ 6 words (at most 4 per side).
- Use "punchWord" (1–3 words) for occasional emphasis. The word MUST be EXACTLY a
  word/expression from the SPOKEN TEXT (copied from the transcript), never invented or paraphrased.
- NEVER make a scene whose whole content is a single word or short text. Do NOT use "kinetic-text"
  as a standalone "title card", and a punchWord is emphasis ON TOP of a real visual (chart / image /
  card / diagram), NEVER a scene's only content. {$bareRule}
- EACH SCENE: at most ONE main layer (do not overlap elements). You can have varied backgrounds
  and transitions to give rhythm. Fill the data from the RESEARCH below.
{$imageGuidance}

# PARAMETER SCHEMAS (params by type)
- timeline:    { "items": [{ "label": str, "sublabel"?: str, "highlight"?: bool, "image"?: "<id>" }], "caption"?: str }
- bar-chart:   { "title"?: str, "unit"?: str, "bars": [{ "label": str, "value": number, "highlight"?: bool, "image"?: "<id>" }] }
- line-chart:  { "title"?: str, "unit"?: str, "unitRight"?: str, "series": [{ "label": str, "points": [number], "highlight"?: bool, "axis"?: "left"|"right" }] }
               // 1–4 series; for TWO Y axes (different scales/units) put "axis":"right" on some series and use "unitRight".
- pie-chart:   { "title"?: str, "slices": [{ "label": str, "value": number, "highlight"?: bool }] }   // 2–6 slices
- scatter-chart: { "title"?: str, "xLabel"?: str, "yLabel"?: str, "points": [{ "label": str, "x": number, "y": number, "highlight"?: bool }] }
               // COMPARISON on TWO axes: position items by two dimensions (e.g. price vs quality). 2–8 points.
- comparison:  { "left": { "title": str, "points": [str], "image"?: "<id>" }, "right": { "title": str, "points": [str], "image"?: "<id>" } }
- bullet-list: { "title"?: str, "items": [str] }
- card:        { "title": str, "lines"?: [str] }
- terminal:    { "lines": [str] }
- diagram:     { "title"?: str, "layout"?: "vertical"|"horizontal"|"cycle", "nodes": [{ "label": str, "image"?: "<id>", "highlight"?: bool }], "edges"?: [{ "from": <index>, "to": <index> }] }   // 2–6 nodes; without edges it links in sequence/cycle
{$imageRevealSchema}
- The provided IMAGES can go into image-reveal OR as "image" in timeline/bar-chart/comparison (use ONLY ids from the list).
- kinetic-text / fade / highlight / seal-stamp / etc.: the text goes in the layer's "text" field, params = {}.

{$rule}
{$designBlock}
{$tokensBlock}
{$customBlock}
{$backgroundBlock}

=== STYLE MANUAL (estilo-animacao.md) ===
{$style}
PROMPT;
    }

    /** Built-in layer types + active custom SFX slugs (both usable by the planner). */
    protected function allLayerTypes(): array
    {
        return app(EffectLibrary::class)->allowedLayerTypes();
    }

    protected function userPrompt(array $transcript, string $mode, float $duration, array $facts = [], array $images = []): string
    {
        $words = json_encode($transcript['words'] ?? [], JSON_UNESCAPED_UNICODE);
        $language = $transcript['language'] ?? '(detect from text)';
        $text = $transcript['text'] ?? '';
        $research = ! empty($facts)
            ? json_encode($facts, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            : '(no research — use what you know, with short labels)';

        $imgList = array_map(fn ($i) => ['id' => $i['id'] ?? '', 'description' => $i['description'] ?? '', 'tone' => $i['tone'] ?? 'mixed'], $images);
        $imagesBlock = ! empty($imgList)
            ? "\n=== PROVIDED IMAGES (use in 'image-reveal' with params.src = id, where the description fits) ===\n"
                .json_encode($imgList, JSON_UNESCAPED_UNICODE)."\n"
                ."CONTRAST: image with 'light' tone → 'ink' (dark) background; 'dark' tone → 'papyrus'/'vellum' (light) background. Never put a light image on a light background nor a dark one on a dark background.\n"
            : '';

        return "Transcript language: {$language}. Write all visible text in this language.\n"
            ."Total duration: {$duration}s. Mode: {$mode}.\n"
            ."Spoken text: {$text}\n"
            ."Words with timestamps (for karaoke and rhythm): {$words}\n\n"
            ."=== RESEARCH (use this real data in the visualizations) ===\n{$research}\n"
            .$imagesBlock
            ."\nReturn the SCENES plan in JSON. Classify the clip TYPE first and keep each "
            .'scene RELEVANT to what is said — use the RESEARCH and the IMAGES only when they reinforce the spoken point.';
    }

    protected function envelope(array $transcript, string $mode, array $options, array $scenes, ?string $backgroundPick = null): array
    {
        $env = [
            'duration' => (float) ($transcript['duration'] ?? 0.0),
            'mode' => $mode,
            'width' => $options['width'] ?? config('contentmachine.clips.width'),
            'height' => $options['height'] ?? config('contentmachine.clips.height'),
            'fps' => $options['fps'] ?? config('contentmachine.clips.fps'),
            'transparent' => $mode === 'sparse',
            'scenes' => $this->sanitizeScenes($scenes),
        ];
        // The planner's raw background suggestion — PlanAnimationsJob resolves it
        // against the clip's manual choice into the final `background` slug.
        if (is_string($backgroundPick) && $backgroundPick !== '') {
            $env['background_pick'] = $backgroundPick;
        }

        return $env;
    }

    protected function sanitizeScenes(array $scenes): array
    {
        $out = [];
        foreach ($scenes as $s) {
            if (! isset($s['start'], $s['end'])) {
                continue;
            }
            $out[] = [
                'start' => (float) $s['start'],
                'end' => (float) $s['end'],
                'background' => in_array($s['background'] ?? null, $this->backgrounds, true) ? $s['background'] : 'papyrus',
                'transitionIn' => in_array($s['transitionIn'] ?? null, $this->transitions, true) ? $s['transitionIn'] : 'cut',
                'transitionOut' => in_array($s['transitionOut'] ?? null, $this->transitions, true) ? $s['transitionOut'] : 'cut',
                'karaoke' => (bool) ($s['karaoke'] ?? false),
                'punchWord' => isset($s['punchWord']) && is_string($s['punchWord']) && $s['punchWord'] !== '' ? $s['punchWord'] : null,
                'present' => in_array($s['present'] ?? null, $this->presents, true) ? $s['present'] : null,
                'layers' => $this->sanitizeLayers($s['layers'] ?? []),
            ];
        }

        return $out;
    }

    protected function sanitizeLayers(array $layers): array
    {
        $out = [];
        $allowed = $this->allLayerTypes();
        foreach ($layers as $l) {
            if (! in_array($l['type'] ?? null, $allowed, true)) {
                continue;
            }
            $out[] = [
                'type' => $l['type'],
                'text' => $l['text'] ?? null,
                'params' => is_array($l['params'] ?? null) ? $l['params'] : [],
            ];
        }

        return $out;
    }

    /** Extract a JSON object from model output that may be fenced or prefixed. */
    protected function extractJson(string $content): array
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

        return json_decode($content, true) ?: [];
    }
}
