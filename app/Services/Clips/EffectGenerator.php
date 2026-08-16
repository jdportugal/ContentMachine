<?php

namespace App\Services\Clips;

use App\Services\Clips\Api\RunsClaudeCli;
use App\Services\Clips\Store\EffectStore;
use App\Services\DesignSystem\DesignSystemRepository;
use RuntimeException;

/**
 * Turns a plain-language description into a Remotion primitive component (a
 * custom SFX). Claude writes the TSX against the PrimitiveProps contract and the
 * design-system tokens; guard() then enforces that it uses ONLY the tokens (no
 * hardcoded brand colours/fonts) before it is ever written to disk. The isolated
 * test-render (GenerateEffectJob) is the second gate that proves it runs.
 */
class EffectGenerator
{
    use RunsClaudeCli;

    /** Names that can never be a custom slug (built-ins + reserved). */
    private const RESERVED = ['_candidate', 'fade', 'slide', 'scale'];

    public function __construct(private DesignSystemRepository $design, private EffectStore $effects) {}

    /**
     * @param  string|null  $keepSlug  when editing, force this existing slug (skips the collision check)
     * @return array{slug:string,display_name:string,description:string,param_schema:string,sample_text:string,sample_params:array,tsx:string}
     *
     * @throws RuntimeException on malformed output or a design-system violation
     */
    public function generate(string $description, ?string $keepSlug = null): array
    {
        // If this OVERRIDES a built-in, the component must read the SAME params
        // the planner sends that built-in — otherwise clips using it render blank.
        $builtin = $keepSlug !== null ? strtolower(trim($keepSlug)) : '';
        $overrideSlug = array_key_exists($builtin, EffectLibrary::BUILTIN_SAMPLES) ? $builtin : null;

        $envelope = $this->runClaude($this->userPrompt($description, $overrideSlug), $this->systemPrompt(), ['timeout' => 300]);
        $data = $this->extractJson((string) ($envelope['result'] ?? ''));

        $slug = $keepSlug !== null
            ? $this->normalizeSlug($keepSlug, allowBuiltin: true) // editing / built-in override — keep the given slug
            : $this->slug((string) ($data['slug'] ?? ''));        // creating — a fresh, unused slug
        $tsx = trim((string) ($data['tsx'] ?? ''));
        if ($tsx === '') {
            throw new RuntimeException('The model returned no component code.');
        }
        $this->guard($tsx);

        $params = is_array($data['sampleParams'] ?? null) ? $data['sampleParams'] : [];

        return [
            'slug' => $slug,
            'display_name' => trim((string) ($data['displayName'] ?? $slug)) ?: $slug,
            'description' => trim((string) ($data['description'] ?? '')) ?: 'Custom effect.',
            'param_schema' => trim((string) ($data['paramSchema'] ?? '{}')) ?: '{}',
            'sample_text' => trim((string) ($data['sampleText'] ?? '')),
            'sample_params' => $params,
            'tsx' => $tsx,
        ];
    }

    /** Validate a fresh slug's format, then make it UNIQUE (append -2, -3, …). */
    private function slug(string $raw): string
    {
        // Format only — a built-in/existing name is not an error here; we uniquify it.
        $base = $this->normalizeSlug($raw, allowBuiltin: true);
        $slug = $base;
        $i = 2;
        while ($this->isTaken($slug)) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    /** A slug already used by a built-in, a reserved name, or an existing effect. */
    private function isTaken(string $slug): bool
    {
        return in_array($slug, self::RESERVED, true)
            || array_key_exists($slug, EffectLibrary::BUILTIN_SAMPLES)
            || $this->effects->slugExists($slug);
    }

    /**
     * Validate/normalise a slug's format (no uniqueness check). $allowBuiltin
     * permits a built-in slug — used when the effect intentionally OVERRIDES a
     * standard VFX (same slug replaces the built-in in the render registry).
     */
    private function normalizeSlug(string $raw, bool $allowBuiltin = false): string
    {
        $slug = strtolower(trim($raw));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim((string) $slug, '-');

        if ($slug === '' || $slug === '_candidate' || ! preg_match('/^[a-z][a-z0-9-]*$/', $slug) || strlen($slug) > 40) {
            throw new RuntimeException("Invalid effect name «{$raw}».");
        }
        if (! $allowBuiltin && (in_array($slug, self::RESERVED, true) || array_key_exists($slug, EffectLibrary::BUILTIN_SAMPLES))) {
            throw new RuntimeException("«{$slug}» is a built-in effect name — choose another.");
        }

        return $slug;
    }

    /**
     * Design-system compliance + safety gate. Rejects anything that hardcodes
     * brand colours/fonts instead of reading the tokens, or that isn't a
     * default-exported primitive importing from style-tokens.
     */
    private function guard(string $tsx): void
    {
        $fail = fn (string $why) => throw new RuntimeException("Effect rejected: {$why}");

        if (! str_contains($tsx, 'style-tokens')) {
            $fail('it must import colours/fonts from "../style-tokens" (the design system).');
        }
        if (! preg_match('/export\s+default/', $tsx)) {
            $fail('it must `export default` the React component.');
        }
        if (preg_match('/#[0-9a-fA-F]{3,8}\b/', $tsx)) {
            $fail('hardcoded hex colour found — use COLORS.* tokens so it follows the design system.');
        }
        if (preg_match('/\b(rgb|rgba|hsl|hsla)\s*\(\s*\d/', $tsx)) {
            $fail('hardcoded colour literal found — use COLORS.* tokens.');
        }
        if (preg_match('/fontFamily\s*:\s*["\'`]/', $tsx)) {
            $fail('hardcoded fontFamily found — use FONTS.* tokens.');
        }
    }

    private function systemPrompt(): string
    {
        $tokens = @file_get_contents(base_path('remotion/src/style-tokens.ts')) ?: '';
        $design = $this->design->readOrTemplate();

        return <<<PROMPT
You are a senior Remotion + React engineer for the Brand Machine studio. You write ONE new
animation primitive (a "SFX") as a self-contained TSX component, from the user's
description. The component becomes a reusable layer in the clip renderer.

Return a SINGLE JSON object (no markdown, no prose) with EXACTLY these keys:
{
  "slug":        kebab-case id used as the layer type, e.g. "glitch-flicker" (a-z, 0-9, hyphens),
  "displayName": short human label, e.g. "Glitch flicker",
  "description": ONE line describing what it does (for the planner vocabulary),
  "paramSchema": a JSON-schema-ish one-liner of the params it reads, e.g. { "intensity"?: number },
  "sampleText":  a short text to preview it with (or "" if it takes no text),
  "sampleParams": an object with representative params for the preview (or {}),
  "tsx":         the FULL component source (a string)
}

# THE COMPONENT CONTRACT (must match EXACTLY)
- It is a file at remotion/src/effects/<slug>.tsx.
- `export default` a `React.FC<PrimitiveProps>`.
- Imports allowed ONLY from: "react", "remotion", "../style-tokens", "../types",
  and `import type {{ PrimitiveProps }} from "../primitives"`. NOTHING else.
- Signature:
    import React from "react";
    import {{ AbsoluteFill, useCurrentFrame, interpolate, spring, Easing }} from "remotion";
    import {{ COLORS, FONTS, headlineGradient, ENGRAVE_SHADOW }} from "../style-tokens";
    import type {{ PrimitiveProps }} from "../primitives";
    const MyEffect: React.FC<PrimitiveProps> = ({{ anim, fps, dark }}) => {{ ... }};
    export default MyEffect;
- PrimitiveProps = {{ anim: Animation; fps: number; dark?: boolean }}.
  Animation = {{ start:number; end:number; primitive:string; text?:string; params?:Record<string,unknown> }}.
- `useCurrentFrame()` is LOCAL (starts at 0 when the layer begins). The layer's
  length in frames is `Math.round((anim.end - anim.start) * fps)`.
- Read the text from `anim.text` and options from `anim.params` (coerce/guard — params may be missing).
- Fill the whole 1080x1920 frame; centre content. Use <AbsoluteFill>.

# DESIGN SYSTEM — LAW (this is what "follow the design system" means)
- NEVER hardcode a colour (no #hex, no rgb()/hsl()) and NEVER hardcode a font family.
  Use ONLY the tokens: COLORS.papyrus/vellum/ink (backgrounds), COLORS.textOnLight/textOnDark
  (text — pick by `dark`), COLORS.mutedOnLight/mutedOnDark, COLORS.teal/tealBright/leather/gold (accents).
  Fonts: FONTS.display (headlines, condensed/uppercase), FONTS.body, FONTS.mono. Headline gold
  treatment: headlineGradient() clipped to text. Soft shadow: ENGRAVE_SHADOW.
- These tokens are re-themed per render from the brand below, so using them = following the brand.

Below are the exact tokens (contract) and the brand guide. Match their spirit.

=== style-tokens.ts (the tokens you must use) ===
{$tokens}

=== DESIGN SYSTEM (brand identity) ===
{$design}
PROMPT;
    }

    private function userPrompt(string $description, ?string $overrideSlug = null): string
    {
        $contract = '';
        if ($overrideSlug !== null) {
            $sample = EffectLibrary::BUILTIN_SAMPLES[$overrideSlug]['params'] ?? [];
            $example = json_encode($sample) ?: '{}';
            $contract = "\n\n# OVERRIDE CONTRACT — CRITICAL\n"
                ."This effect REPLACES the built-in \"{$overrideSlug}\". The clip planner sends it the EXACT SAME params as that built-in, "
                .'so you MUST read those same fields from `anim.params` (and `anim.text` where the built-in uses it) — do NOT invent new param names, '
                ."or existing clips will render blank.\n"
                ."The params it will receive look EXACTLY like this example — read these keys:\n{$example}\n"
                .'Keep that data contract; only change how it LOOKS/animates per the request below. Set sampleParams to a value of this same shape.';
        }

        return "Create this effect:\n\n{$description}{$contract}\n\n"
            .'Return ONLY the JSON object described above. The "tsx" must be a complete, '
            .'compiling component that uses ONLY the design-system tokens for colours and fonts.';
    }

    /** Extract a JSON object from model output that may be fenced or prefixed. */
    private function extractJson(string $content): array
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
        $data = json_decode($content, true);
        if (! is_array($data)) {
            throw new RuntimeException('The model did not return valid JSON.');
        }

        return $data;
    }
}
