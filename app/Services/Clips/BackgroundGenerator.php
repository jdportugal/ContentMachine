<?php

namespace App\Services\Clips;

use App\Services\Clips\Api\RunsClaudeCli;
use App\Services\Clips\Store\BackgroundStore;
use RuntimeException;

/**
 * Turns a plain-language description into a full-frame Remotion BACKDROP component
 * (a custom "code" background). Same PrimitiveProps contract as an SFX — so the
 * isolated candidate test-render reuses SampleEffect — but the prompt asks for an
 * ambient, seamlessly-looping full-screen backdrop that sits BEHIND scene content.
 *
 * A background is INTENTIONALLY independent of the design system: it paints the
 * EXACT colours the user describes and never reads the themeable tokens (which are
 * re-mapped per project — that would turn a requested gold into another brand's
 * accent). guard() enforces that independence before it is written to disk.
 */
class BackgroundGenerator
{
    use RunsClaudeCli;

    public function __construct(private BackgroundStore $store) {}

    /**
     * @param  string|null  $keepSlug  when editing, force this existing slug
     * @return array{slug:string,display_name:string,description:string,tsx:string}
     *
     * @throws RuntimeException on malformed output or a contract violation
     */
    public function generate(string $description, ?string $keepSlug = null): array
    {
        $envelope = $this->runClaude($this->userPrompt($description), $this->systemPrompt(), ['timeout' => (int) config('contentmachine.clips.llm_timeout', 600)]);
        $data = $this->extractJson((string) ($envelope['result'] ?? ''));

        $slug = $keepSlug !== null ? $this->normalizeSlug($keepSlug) : $this->uniqueSlug((string) ($data['slug'] ?? ''));
        $tsx = trim((string) ($data['tsx'] ?? ''));
        if ($tsx === '') {
            throw new RuntimeException('The model returned no component code.');
        }
        $this->guard($tsx);

        return [
            'slug' => $slug,
            'display_name' => trim((string) ($data['displayName'] ?? $slug)) ?: $slug,
            'description' => trim((string) ($data['description'] ?? '')) ?: 'Custom background.',
            'tsx' => $tsx,
        ];
    }

    /** Validate a fresh slug's format, then make it UNIQUE within the backgrounds store. */
    private function uniqueSlug(string $raw): string
    {
        $base = $this->normalizeSlug($raw);
        $slug = $base;
        $i = 2;
        while ($this->store->slugExists($slug)) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    private function normalizeSlug(string $raw): string
    {
        $slug = strtolower(trim($raw));
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', $slug), '-');

        if ($slug === '' || ! preg_match('/^[a-z][a-z0-9-]*$/', $slug) || strlen($slug) > 40) {
            throw new RuntimeException("Invalid background name «{$raw}».");
        }

        return $slug;
    }

    /**
     * Safety + independence gate. A background must be a default-exported component
     * and must NOT read the design-system tokens (importing "../style-tokens" would
     * let a per-project theme re-map its colours — the exact "gold turns purple"
     * bug). Colours are hardcoded from the user's description, so hex/rgb are fine.
     */
    private function guard(string $tsx): void
    {
        $fail = fn (string $why) => throw new RuntimeException("Background rejected: {$why}");

        if (! preg_match('/export\s+default/', $tsx)) {
            $fail('it must `export default` the React component.');
        }
        if (str_contains($tsx, 'style-tokens')) {
            $fail('a background must NOT import "../style-tokens" — it is independent of the design system and paints the exact colours you describe.');
        }
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You are a senior Remotion + React engineer. You write ONE new full-screen BACKGROUND
(a "backdrop") as a self-contained TSX component, from the user's description. It
renders BEHIND every scene of a vertical clip, so it must be ambient and subtle — it
sets mood, it never competes with the foreground content.

Return a SINGLE JSON object (no markdown, no prose) with EXACTLY these keys:
{
  "slug":        kebab-case id, e.g. "aurora-drift" (a-z, 0-9, hyphens),
  "displayName": short human label, e.g. "Aurora drift",
  "description": ONE line describing the mood/look (shown to the planner so it can pick it),
  "tsx":         the FULL component source (a string)
}

# COLOURS — PAINT EXACTLY WHAT THE USER ASKS
- A background is INDEPENDENT of any design system. Use the EXACT colours named in the
  description — if the user gives a hex like #C8941E, use THAT hex verbatim. If they name
  a colour with no hex, choose a faithful hex for it.
- NEVER read or import design-system tokens. Do NOT import "../style-tokens" and do NOT
  use COLORS.* — those are re-mapped per project and would change the colour you were
  asked for. Hardcode the colours directly.

# THE COMPONENT CONTRACT (must match EXACTLY)
- It is a file at remotion/src/backgrounds/<slug>.tsx.
- `export default` a `React.FC<PrimitiveProps>`.
- Imports allowed ONLY from: "react", "remotion", and
  `import type { PrimitiveProps } from "../primitives"`. NOTHING else (no "../style-tokens").
- Signature:
    import React from "react";
    import { AbsoluteFill, useCurrentFrame, interpolate, Easing } from "remotion";
    import type { PrimitiveProps } from "../primitives";
    const MyBackground: React.FC<PrimitiveProps> = ({ fps }) => { ... };
    export default MyBackground;
- PrimitiveProps = { anim: Animation; fps: number; dark?: boolean }. A BACKGROUND
  IGNORES anim.text and anim.params — it takes no text and no data. Use only `fps`
  and `useCurrentFrame()` for the animation.
- Fill the WHOLE 1080x1920 frame with <AbsoluteFill>. It is opaque — paint a base.

# CRITICAL — IT MUST WORK FOR ANY CLIP LENGTH
- The clip may be 5 seconds or 5 minutes. `useCurrentFrame()` grows without bound, so
  the motion MUST be seamless and endless: drive it with continuous/periodic functions
  (Math.sin/Math.cos of frame, or interpolate with `Easing` on a repeating phase).
  NEVER a one-shot intro that finishes and freezes. NO spring() that settles.
- Keep it CALM and readable so foreground text/charts stay legible: slow drifts, soft
  gradients, gentle motion. Avoid rapid flashing or busy detail — but honour the exact
  colours and look the user asked for.
PROMPT;
    }

    private function userPrompt(string $description): string
    {
        return "Create this background:\n\n{$description}\n\n"
            .'Return ONLY the JSON object described above. The "tsx" must be a complete, compiling '
            .'component that loops seamlessly for any duration and paints the EXACT colours described '
            .'(hardcoded — never via design-system tokens).';
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
            // Without the output there is nothing to debug: a truncation, a refusal
            // and a chatty preamble all read as this one sentence. The tail is the
            // tell — a cut-off response ends mid-token.
            throw new RuntimeException(sprintf(
                'The model did not return valid JSON (%s, %d chars). It ended with: %s',
                json_last_error_msg(),
                strlen($content),
                $content === '' ? '(nothing)' : '…'.substr($content, -180)
            ));
        }

        return $data;
    }
}
