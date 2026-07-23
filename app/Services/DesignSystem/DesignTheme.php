<?php

namespace App\Services\DesignSystem;

/**
 * The design-token contract shared between the app and the Remotion renderer
 * (mirrors ClipTheme in remotion/src/types.ts). Defaults reproduce the NEBULA
 * look — deep-space navy, molten-gold accent, electric-blue secondary, Anton +
 * Space Grotesk, starfield — so a clip with no/failed extraction renders Nebula.
 *
 * Colours are #rrggbb; fonts are Google-Fonts family names; texture.kind is one
 * of paper|starfield|gradient|solid. `sanitize()` validates arbitrary input
 * (e.g. LLM output) and fills any gap with the default.
 */
class DesignTheme
{
    private const TEXTURES = ['paper', 'starfield', 'gradient', 'solid'];

    /** NEBULA defaults. */
    public static function defaults(): array
    {
        return [
            'colors' => [
                'bg' => '#05060E',          // void
                'bgAlt' => '#0C1225',       // panel
                'bgContrast' => '#0A1030',  // deep-navy
                'textOnBg' => '#EAF0FF',    // star-white
                'textOnContrast' => '#EAF0FF',
                'mutedOnBg' => '#A9B6D6',
                'mutedOnContrast' => 'rgba(169,182,214,0.72)',
                'accent' => '#FFB347',      // molten-gold (mid)
                'accent2' => '#2A3BEB',     // electric-blue
                'accent3' => '#FF7A3D',     // warm-orange
            ],
            'fonts' => [
                'display' => 'Anton',
                'body' => 'Space Grotesk',
                'mono' => 'JetBrains Mono',
            ],
            'texture' => ['kind' => 'starfield'],
        ];
    }

    /**
     * Normalise arbitrary input into a valid theme, filling gaps with defaults.
     *
     * @param  array<string,mixed>  $input
     * @return array<string,mixed>
     */
    public static function sanitize(array $input): array
    {
        $def = self::defaults();

        $colors = [];
        foreach ($def['colors'] as $key => $fallback) {
            $val = $input['colors'][$key] ?? null;
            $colors[$key] = is_string($val) && self::isColor($val) ? trim($val) : $fallback;
        }

        $fonts = [];
        foreach ($def['fonts'] as $key => $fallback) {
            $val = $input['fonts'][$key] ?? null;
            $fonts[$key] = is_string($val) && trim($val) !== '' ? self::cleanFont($val) : $fallback;
        }

        $kind = $input['texture']['kind'] ?? null;
        $texture = ['kind' => in_array($kind, self::TEXTURES, true) ? $kind : $def['texture']['kind']];
        if (! empty($input['texture']['css']) && is_string($input['texture']['css'])) {
            $texture['css'] = trim($input['texture']['css']);
        }

        return ['colors' => $colors, 'fonts' => $fonts, 'texture' => $texture];
    }

    /** #rgb, #rrggbb, or rgb()/rgba() strings are accepted as colours. */
    private static function isColor(string $v): bool
    {
        $v = trim($v);

        return (bool) preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $v)
            || (bool) preg_match('/^rgba?\(\s*[\d.]+\s*,\s*[\d.]+\s*,\s*[\d.]+\s*(,\s*[\d.]+\s*)?\)$/i', $v);
    }

    /** Keep a plausible Google-Fonts family name (letters, digits, spaces). */
    private static function cleanFont(string $v): string
    {
        $v = trim(preg_replace('/["\']/', '', $v) ?? '');
        $v = preg_replace('/\s+/', ' ', $v) ?? $v;

        return mb_substr($v, 0, 60);
    }
}
