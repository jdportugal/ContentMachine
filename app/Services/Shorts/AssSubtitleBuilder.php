<?php

namespace App\Services\Shorts;

/**
 * Builds an ASS (Advanced SubStation Alpha) file from a clip's
 * editable `subtitle_data` — the LOCAL and independent equivalent of the
 * `add_subtitles_to_video` from the Flask "ShortsCreator" service (which used
 * MoviePy/PIL). Here the subtitling is burned by ffmpeg's libass, without
 * any external API.
 *
 * Keeps the SAME style logic as the original:
 *   - `position`     → ASS numeric alignment (3×3 grid);
 *   - `font-family`  → Fontname (fonts bundled in resources/fonts);
 *   - `font-size`    → Fontsize in video px (PlayRes = video dimensions);
 *   - `line-color`/`normal-color` → text color;
 *   - `outline-color`/`outline-width` → outline;
 *   - `word_level_mode`: off | karaoke | popup | typewriter.
 *
 * It is pure (no I/O, no ffmpeg) — the testable core of the subtitle engine.
 */
class AssSubtitleBuilder
{
    /** Position grid → ASS Alignment (numeric keypad: 1=bottom-left … 9=top-right). */
    private const ALIGN = [
        'bottom-left' => 1, 'bottom-center' => 2, 'bottom-right' => 3,
        'center-left' => 4, 'center-center' => 5, 'center-right' => 6,
        'top-left' => 7, 'top-center' => 8, 'top-right' => 9,
    ];

    /** font-family (as in the original) → internal font name for libass. */
    private const FONT_NAME = [
        'Luckiest Guy' => 'Luckiest Guy',
        'Anton' => 'Anton',
        'Pixelify Sans' => 'Pixelify Sans',
        'Impact' => 'Impact',
        'Times Bold Italic' => 'Liberation Serif',
        'DejaVu-Sans-Bold' => 'DejaVu Sans',
    ];

    /**
     * Generates the full ASS content.
     *
     * @param  array<int,array<string,mixed>>  $subtitleData  Segments {start,end,text,words[]} (seconds, relative to the clip).
     * @param  array<string,mixed>  $settings  Style (position, font-family, font-size, line-color, outline-*).
     * @param  string  $wordMode  off|karaoke|popup|typewriter.
     * @param  int  $videoW  Video width (px) — PlayResX.
     * @param  int  $videoH  Video height (px) — PlayResY.
     */
    public function build(array $subtitleData, array $settings, string $wordMode, int $videoW, int $videoH): string
    {
        $fontName = self::FONT_NAME[$settings['font-family'] ?? ''] ?? ($settings['font-family'] ?? 'DejaVu Sans');
        $fontSize = (int) round((float) ($settings['font-size'] ?? 100));
        $align = self::ALIGN[$settings['position'] ?? 'center-center'] ?? 5;

        $primaryHex = $settings['line-color'] ?? $settings['normal-color'] ?? '#FFFFFF';
        $highlightHex = $settings['highlight-color'] ?? '#F5C542';
        $primary = $this->toAssColor($primaryHex);
        $outlineColor = $this->toAssColor($settings['outline-color'] ?? '#000000');
        $outlineWidth = max(0.0, (float) ($settings['outline-width'] ?? 3));
        $marginV = (int) round((float) ($settings['margin-v'] ?? max(60, $videoH * 0.06)));
        $marginH = (int) round((float) ($settings['margin-h'] ?? max(40, $videoW * 0.05)));
        $bold = ! empty($settings['bold']) ? -1 : 0;
        $upper = ($settings['text-transform'] ?? '') === 'uppercase';
        $maxWords = max(1, (int) ($settings['max-words-per-line'] ?? 4));

        // Inline colors for the word-by-word highlight of karaoke mode.
        $corBase = $this->inlineColor($primaryHex);
        $corDestaque = $this->inlineColor($highlightHex);

        $styleDefault = sprintf(
            'Style: Default,%s,%d,%s,%s,%s,&H00000000,%d,0,0,0,100,100,0,0,1,%s,0,%d,%d,%d,%d,1',
            $fontName, $fontSize, $primary, $primary, $outlineColor,
            $bold, $this->num($outlineWidth), $align, $marginH, $marginH, $marginV,
        );

        $events = [];
        foreach ($subtitleData as $seg) {
            $start = max(0.0, (float) ($seg['start'] ?? 0));
            $end = max($start, (float) ($seg['end'] ?? 0));
            $text = trim((string) ($seg['text'] ?? ''));
            $words = array_values(array_filter(
                (array) ($seg['words'] ?? []),
                fn ($w) => trim((string) ($w['word'] ?? '')) !== '',
            ));

            if ($text === '' && ! $words) {
                continue;
            }

            match ($wordMode) {
                'karaoke' => $this->karaoke($events, $start, $end, $text, $words, $upper, $maxWords, $corBase, $corDestaque),
                'popup' => $this->popup($events, $words, $upper),
                'typewriter' => $this->typewriter($events, $words, $upper),
                default => $this->sentence($events, $start, $end, $text, $maxWords, $upper),
            };
        }

        return $this->header($videoW, $videoH, $styleDefault)."\n".implode("\n", $events)."\n";
    }

    // --- Word modes ---------------------------------------------------

    /** off: one caption per segment, with a line break every N words. */
    private function sentence(array &$events, float $start, float $end, string $text, int $maxWords, bool $upper): void
    {
        $wrapped = $this->wrap($text, max(1, $maxWords));
        $events[] = $this->dialogue('Default', $start, $end, $this->esc($upper ? mb_strtoupper($wrapped) : $wrapped));
    }

    /**
     * karaoke: "shorts"-style caption — shows groups of up to $maxWords words
     * at a time and highlights (in yellow) the word being said, word by
     * word. Without words, falls back to `off`.
     *
     * For each group, it emits one event per word: shows the whole group with the
     * active word in the highlight color and the rest in the base color. The events are
     * contiguous (from one word to the start of the next) so there are no gaps.
     */
    private function karaoke(array &$events, float $start, float $end, string $text, array $words, bool $upper, int $maxWords, string $corBase, string $corDestaque): void
    {
        if (! $words) {
            $this->sentence($events, $start, $end, $text, $maxWords, $upper);

            return;
        }

        foreach (array_chunk($words, $maxWords) as $grupo) {
            $n = count($grupo);
            $grupoInicio = (float) ($grupo[0]['start'] ?? 0);
            $grupoFim = max($grupoInicio, (float) ($grupo[$n - 1]['end'] ?? $grupoInicio));

            // Group tokens (already escaped and, if requested, uppercased).
            $tokens = array_map(function ($w) use ($upper) {
                $t = trim((string) $w['word']);

                return $this->esc($upper ? mb_strtoupper($t) : $t);
            }, $grupo);

            for ($i = 0; $i < $n; $i++) {
                $ini = ($i === 0) ? $grupoInicio : (float) ($grupo[$i]['start'] ?? $grupoInicio);
                $fim = ($i === $n - 1) ? $grupoFim : (float) ($grupo[$i + 1]['start'] ?? $grupoFim);
                if ($fim <= $ini) {
                    $fim = $ini + 0.05;
                }

                // Group line with the i-th word highlighted.
                $partes = [];
                foreach ($tokens as $j => $tok) {
                    $partes[] = $j === $i
                        ? '{\1c'.$corDestaque.'}'.$tok.'{\1c'.$corBase.'}'
                        : $tok;
                }

                $events[] = $this->dialogue('Default', $ini, $fim, implode(' ', $partes));
            }
        }
    }

    /** popup: one word at a time, within its interval. */
    private function popup(array &$events, array $words, bool $upper): void
    {
        foreach ($words as $w) {
            $ws = max(0.0, (float) ($w['start'] ?? 0));
            $we = max($ws + 0.05, (float) ($w['end'] ?? $ws));
            $token = trim((string) $w['word']);
            $events[] = $this->dialogue('Default', $ws, $we, $this->esc($upper ? mb_strtoupper($token) : $token));
        }
    }

    /** typewriter: text that accumulates word by word. */
    private function typewriter(array &$events, array $words, bool $upper): void
    {
        $acc = '';
        foreach ($words as $w) {
            $ws = max(0.0, (float) ($w['start'] ?? 0));
            $we = max($ws + 0.05, (float) ($w['end'] ?? $ws));
            $acc = trim($acc.' '.trim((string) $w['word']));
            $events[] = $this->dialogue('Default', $ws, $we, $this->esc($upper ? mb_strtoupper($acc) : $acc));
        }
    }

    // --- Helpers ------------------------------------------------------

    private function header(int $w, int $h, string $styleDefault): string
    {
        return implode("\n", [
            '[Script Info]',
            'ScriptType: v4.00+',
            // WrapStyle 0 = smart automatic wrapping: long lines adjust
            // to the video width.
            'WrapStyle: 0',
            'ScaledBorderAndShadow: yes',
            'YCbCr Matrix: TV.709',
            'PlayResX: '.$w,
            'PlayResY: '.$h,
            '',
            '[V4+ Styles]',
            'Format: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding',
            $styleDefault,
            '',
            '[Events]',
            'Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text',
        ]);
    }

    private function dialogue(string $style, float $start, float $end, string $text): string
    {
        return sprintf('Dialogue: 0,%s,%s,%s,,0,0,0,,%s', $this->ts($start), $this->ts($end), $style, $text);
    }

    /** Seconds → "H:MM:SS.cc" (hundredths), ASS time format. */
    private function ts(float $seconds): string
    {
        $seconds = max(0.0, $seconds);
        $cs = (int) round($seconds * 100);
        $h = intdiv($cs, 360000);
        $m = intdiv($cs % 360000, 6000);
        $s = intdiv($cs % 6000, 100);
        $c = $cs % 100;

        return sprintf('%d:%02d:%02d.%02d', $h, $m, $s, $c);
    }

    /** '#RRGGBB' → 'BBGGRR' (ASS uses BGR). */
    private function bgr(string $hex): string
    {
        $hex = ltrim(trim($hex), '#');
        $hex = ltrim($hex, '#'); // tolerates "##RRGGBB"

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (! preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            $hex = 'FFFFFF';
        }

        return strtoupper(substr($hex, 4, 2).substr($hex, 2, 2).substr($hex, 0, 2));
    }

    /** '#RRGGBB' → '&H00BBGGRR' (ASS style color, with alpha; 00 = opaque). */
    private function toAssColor(string $hex): string
    {
        return '&H00'.$this->bgr($hex);
    }

    /** '#RRGGBB' → '&HBBGGRR&' (inline color override, e.g. {\1c&HBBGGRR&}). */
    private function inlineColor(string $hex): string
    {
        return '&H'.$this->bgr($hex).'&';
    }

    /** Escapes text for an ASS event (braces and line breaks). */
    private function esc(string $text): string
    {
        $text = str_replace(['{', '}'], ['(', ')'], $text);
        $text = preg_replace('/\r\n|\r|\n/', '\\N', $text);

        return $text;
    }

    /** Breaks the text every $max words using \N (ASS hard break). */
    private function wrap(string $text, int $max): string
    {
        $words = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $lines = [];
        foreach (array_chunk($words, $max) as $chunk) {
            $lines[] = implode(' ', $chunk);
        }

        return implode("\n", $lines);
    }

    /** Formats a float without superfluous decimals (for the ASS Outline field). */
    private function num(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.') ?: '0';
    }
}
