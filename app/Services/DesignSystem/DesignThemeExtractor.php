<?php

namespace App\Services\DesignSystem;

use App\Services\Clips\Api\RunsClaudeCli;
use Throwable;

/**
 * Turns the freeform design-system Markdown into a structured theme (colours,
 * fonts, texture) that the Remotion renderer consumes. Uses the authenticated
 * `claude` CLI. Any failure (no CLI, bad JSON, timeout) falls back to the Brand Machine
 * defaults so a render is never blocked.
 */
class DesignThemeExtractor
{
    use RunsClaudeCli;

    /**
     * @return array<string,mixed> a sanitized DesignTheme
     */
    public function extract(string $markdown): array
    {
        if (trim($markdown) === '') {
            return DesignTheme::defaults();
        }

        try {
            $envelope = $this->runClaude(
                $this->userPrompt($markdown),
                $this->systemPrompt(),
                ['maxTurns' => 1, 'timeout' => 120],
            );

            $json = $this->decodeJson((string) ($envelope['result'] ?? ''));

            return is_array($json) ? DesignTheme::sanitize($json) : DesignTheme::defaults();
        } catch (Throwable) {
            return DesignTheme::defaults();
        }
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
        You are a system that converts a design guide (Markdown) into a set of tokens
        for a video rendering engine. You ALWAYS return ONLY a valid JSON object
        (no markdown, no comments, no surrounding text), in this EXACT shape:

        {
          "colors": {
            "bg": "#rrggbb",            // main background
            "bgAlt": "#rrggbb",         // alternative surface (cards)
            "bgContrast": "#rrggbb",    // contrast background (the darkest/strongest)
            "textOnBg": "#rrggbb",      // text over bg/bgAlt (MUST contrast)
            "textOnContrast": "#rrggbb",// text over bgContrast (MUST contrast)
            "mutedOnBg": "#rrggbb",     // secondary text over bg
            "mutedOnContrast": "#rrggbb",
            "accent": "#rrggbb",        // primary accent color
            "accent2": "#rrggbb",       // secondary accent
            "accent3": "#rrggbb"        // third accent/ornament
          },
          "fonts": {
            "display": "Google Fonts family name",  // headings
            "displayWeight": 400,                   // primary heading weight (100-900)
            "body": "Google Fonts family name",     // body
            "bodyWeight": 400,                      // primary body weight (100-900)
            "mono": "Google Fonts family name"       // monospaced
          },
          "texture": { "kind": "paper" | "starfield" | "gradient" | "solid" },
          "style": {
            "headline": "gradient" | "flat",   // flat = solid ink colour titles, no gradient
            "shadow": "soft" | "hard",          // hard = offset block shadow (Npx Npx 0)
            "panelBorder": 0,                    // px of solid ink border on cards (0-8)
            "sharp": true | false,               // true = square corners (no radius)
            "uppercaseTitles": true | false
          }
        }

        RULES:
        - Extract the REAL colors from the design (palette, backgrounds, accents). Use hex #rrggbb.
        - Ensure CONTRAST: if the background is dark, the text must be light (and vice-versa).
        - Fonts MUST be real Google Fonts names (e.g. "Anton", "Inter",
          "Montserrat", "Playfair Display"). If the design specifies a non-Google font,
          choose the closest Google Fonts alternative.
        - displayWeight / bodyWeight: the WEIGHT the design specifies for headings and
          body (e.g. "Fraunces 900" -> displayWeight 900; "Spline Sans 400/600/700" ->
          bodyWeight 400, the base weight). Use the heaviest weight named for headings.
          If no weight is stated, use 700 for display and 400 for body.
        - texture.kind: "starfield" for dark/space themes with stars; "gradient"
          for gradients; "solid" for a FLAT single-colour background; "paper" for a flat
          background with a subtle dot/halftone/grain texture. If the design says "flat",
          "no gradients", "tinta plana" or shows a solid colour with dots, use "paper" or
          "solid" — NOT "gradient".
        - style: read the design's TREATMENT. Bold serif titles, hard/offset block shadows
          ("Npx Npx 0", "sombra dura"), thick borders and square corners = a print/brutalist
          look -> headline "flat", shadow "hard", panelBorder 3, sharp true, uppercaseTitles
          true (if titles are caixa alta/uppercase). Soft glows, gradients and rounded cards
          -> headline "gradient", shadow "soft", panelBorder 0, sharp false. Choose per the design.
        - Reply ONLY with the JSON.
        PROMPT;
    }

    private function userPrompt(string $markdown): string
    {
        return "Design guide (Markdown):\n\n".$markdown."\n\nReturn the tokens JSON.";
    }

    /** Strip code fences and decode the first JSON object found. */
    private function decodeJson(string $text): mixed
    {
        $text = trim($text);
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text) ?? $text;
        $text = preg_replace('/\s*```$/', '', $text) ?? $text;

        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false && $end >= $start) {
            $text = substr($text, $start, $end - $start + 1);
        }

        return json_decode($text, true);
    }
}
