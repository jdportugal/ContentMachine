<?php

namespace App\Services\DesignSystem;

use App\Services\Clips\Api\RunsClaudeCli;
use Throwable;

/**
 * Turns the freeform design-system Markdown into a structured theme (colours,
 * fonts, texture) that the Remotion renderer consumes. Uses the authenticated
 * `claude` CLI. Any failure (no CLI, bad JSON, timeout) falls back to the IATECA
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
            "body": "Google Fonts family name",     // body
            "mono": "Google Fonts family name"       // monospaced
          },
          "texture": { "kind": "paper" | "starfield" | "gradient" | "solid" }
        }

        RULES:
        - Extract the REAL colors from the design (palette, backgrounds, accents). Use hex #rrggbb.
        - Ensure CONTRAST: if the background is dark, the text must be light (and vice-versa).
        - Fonts MUST be real Google Fonts names (e.g. "Anton", "Inter",
          "Montserrat", "Playfair Display"). If the design specifies a non-Google font,
          choose the closest Google Fonts alternative.
        - texture.kind: "starfield" for dark/space themes with stars; "gradient"
          for gradients; "solid" for a flat background; "paper" for paper/organic texture.
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
