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
                'displayWeight' => 400, // primary weight for headings/titles
                'bodyWeight' => 400,    // primary weight for body text
            ],
            'texture' => ['kind' => 'starfield'],
            'style' => [
                'headline' => 'gradient', // 'gradient' | 'flat' (solid ink colour, no clip)
                'shadow' => 'soft',       // 'soft' (blur) | 'hard' (Npx Npx 0 offset)
                'panelBorder' => 0,       // px ink border around panels/cards (0 = none)
                'sharp' => false,         // true = 0 corner radius (print look)
                'uppercaseTitles' => false,
            ],
        ];
    }

    private const HEADLINES = ['gradient', 'flat'];

    private const SHADOWS = ['soft', 'hard'];

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
        foreach (['display', 'body', 'mono'] as $key) {
            $val = $input['fonts'][$key] ?? null;
            $fonts[$key] = is_string($val) && trim($val) !== '' ? self::cleanFont($val) : $def['fonts'][$key];
        }
        $fonts['displayWeight'] = self::cleanWeight($input['fonts']['displayWeight'] ?? null, $def['fonts']['displayWeight']);
        $fonts['bodyWeight'] = self::cleanWeight($input['fonts']['bodyWeight'] ?? null, $def['fonts']['bodyWeight']);

        $kind = $input['texture']['kind'] ?? null;
        $texture = ['kind' => in_array($kind, self::TEXTURES, true) ? $kind : $def['texture']['kind']];
        if (! empty($input['texture']['css']) && is_string($input['texture']['css'])) {
            $texture['css'] = trim($input['texture']['css']);
        }

        $ds = $def['style'];
        $s = is_array($input['style'] ?? null) ? $input['style'] : [];
        $style = [
            'headline' => in_array($s['headline'] ?? null, self::HEADLINES, true) ? $s['headline'] : $ds['headline'],
            'shadow' => in_array($s['shadow'] ?? null, self::SHADOWS, true) ? $s['shadow'] : $ds['shadow'],
            'panelBorder' => is_numeric($s['panelBorder'] ?? null) ? max(0, min(8, (int) $s['panelBorder'])) : $ds['panelBorder'],
            'sharp' => is_bool($s['sharp'] ?? null) ? $s['sharp'] : $ds['sharp'],
            'uppercaseTitles' => is_bool($s['uppercaseTitles'] ?? null) ? $s['uppercaseTitles'] : $ds['uppercaseTitles'],
        ];

        return ['colors' => $colors, 'fonts' => $fonts, 'texture' => $texture, 'style' => $style];
    }

    /** #rgb, #rrggbb, or rgb()/rgba() strings are accepted as colours. */
    private static function isColor(string $v): bool
    {
        $v = trim($v);

        return (bool) preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $v)
            || (bool) preg_match('/^rgba?\(\s*[\d.]+\s*,\s*[\d.]+\s*,\s*[\d.]+\s*(,\s*[\d.]+\s*)?\)$/i', $v);
    }

    /** Coerce a font weight to a valid CSS weight (100–900, nearest 100). */
    private static function cleanWeight(mixed $v, int $fallback): int
    {
        if (! is_numeric($v)) {
            return $fallback;
        }
        $w = (int) round(((int) $v) / 100) * 100;

        return max(100, min(900, $w));
    }

    /** Keep a plausible Google-Fonts family name (letters, digits, spaces). */
    private static function cleanFont(string $v): string
    {
        $v = trim(preg_replace('/["\']/', '', $v) ?? '');
        $v = preg_replace('/\s+/', ' ', $v) ?? $v;

        return mb_substr($v, 0, 60);
    }
}
