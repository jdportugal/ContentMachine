<?php

namespace App\Services\Shorts;

/**
 * PHP port of the logic of the "Prepare Clip Subtitles" node from the n8n
 * "Long to shorts" flow: trims the full video transcript to a clip's
 * window and shifts all the times (segment + word) to start
 * at zero.
 *
 * No external dependencies — it is the testable core of the pipeline.
 */
class SubtitleShifter
{
    /**
     * Converts an instant to seconds (float).
     *
     * Accepts, like the Flask `parse_time_to_seconds`:
     *   - number (int|float) → seconds;
     *   - numeric string "12.5" → seconds;
     *   - "HH:MM:SS" → h*3600 + m*60 + s;
     *   - "HH:MM:SS[,.:]mmm" with 1–3 millisecond digits.
     *
     * Parity NOTE: the milliseconds are padded to the RIGHT up to 3
     * digits (":5" → ".500", not ".005"), replicating the server's `ljust(3, '0')`.
     * Always use 3 digits to avoid surprises.
     */
    public static function parseTimeToSeconds(int|float|string|null $input): float
    {
        if ($input === null || $input === '') {
            return 0.0;
        }

        if (is_int($input) || is_float($input)) {
            return (float) $input;
        }

        $s = trim((string) $input);

        if (is_numeric($s)) {
            return (float) $s;
        }

        // HH:MM:SS with milliseconds (separator , . or :) and 1–3 digits.
        if (preg_match('/^(\d{1,2}):(\d{1,2}):(\d{1,2})[,.:]\s*(\d{1,3})$/', $s, $m)) {
            $ms = str_pad($m[4], 3, '0', STR_PAD_RIGHT);

            return (int) $m[1] * 3600 + (int) $m[2] * 60 + (int) $m[3] + (int) $ms / 1000.0;
        }

        // HH:MM:SS without milliseconds.
        if (preg_match('/^(\d{1,2}):(\d{1,2}):(\d{1,2})$/', $s, $m)) {
            return (int) $m[1] * 3600 + (int) $m[2] * 60 + (int) $m[3];
        }

        return 0.0;
    }

    /**
     * Formats seconds as "HH:MM:SS.mmm" (useful for calling /split-video).
     */
    public static function secondsToTimestamp(float $seconds): string
    {
        $seconds = max(0.0, $seconds);
        $whole = (int) floor($seconds);
        $ms = (int) round(($seconds - $whole) * 1000);

        if ($ms === 1000) {
            $ms = 0;
            $whole++;
        }

        $h = intdiv($whole, 3600);
        $m = intdiv($whole % 3600, 60);
        $s = $whole % 60;

        return sprintf('%02d:%02d:%02d.%03d', $h, $m, $s, $ms);
    }

    /**
     * Trims and shifts the transcript to the window [cutStart, cutEnd].
     *
     * Keeps the segments that overlap the window (end > cutStart &&
     * start < cutEnd), subtracts cutStart from all the times (segment and each
     * word) and clamps the minimum to 0.
     *
     * @param  array<int,array<string,mixed>>  $subtitleData  Segments {start,end,text,words:[{word,start,end}]} in seconds.
     * @param  int|float|string  $cutStart  Cut start (format accepted by parseTimeToSeconds).
     * @param  int|float|string  $cutEnd  Cut end.
     * @return array<int,array<string,mixed>> Segments shifted to start at 0.
     */
    public static function shift(array $subtitleData, int|float|string $cutStart, int|float|string $cutEnd): array
    {
        $start = self::parseTimeToSeconds($cutStart);
        $end = self::parseTimeToSeconds($cutEnd);

        $out = [];

        foreach ($subtitleData as $seg) {
            $segStart = self::parseTimeToSeconds($seg['start'] ?? 0);
            $segEnd = self::parseTimeToSeconds($seg['end'] ?? 0);

            // Only keep segments that overlap the clip window.
            if (! ($segEnd > $start && $segStart < $end)) {
                continue;
            }

            $span = max(0.0, $end - $start);

            // Words OUTSIDE the window are dropped, not clamped. Clamping them was
            // the bug behind "every caption on the first frame": a cut landing in
            // the middle of a segment kept all the words spoken before the in-point
            // and pulled each of them back to 0, so they all fired on frame one.
            $words = [];
            $descartou = false;
            foreach (($seg['words'] ?? []) as $word) {
                $wStart = self::parseTimeToSeconds($word['start'] ?? 0);
                $wEnd = self::parseTimeToSeconds($word['end'] ?? 0);

                if ($wEnd <= $start || $wStart >= $end) {
                    $descartou = true;

                    continue;
                }

                $words[] = [
                    'word' => $word['word'] ?? '',
                    'start' => min($span, max(0.0, $wStart - $start)),
                    'end' => min($span, max(0.0, $wEnd - $start)),
                ];
            }

            // The words that survived are the ones actually spoken in the clip, so
            // the text has to say the same — alignWords keeps real timings only
            // while text and words agree, and would otherwise re-space the whole
            // line evenly and lose them.
            $texto = trim((string) ($seg['text'] ?? ''));
            if ($descartou && $words !== []) {
                $texto = trim(implode(' ', array_map(fn ($w) => trim((string) $w['word']), $words)));
            }
            if ($texto === '') {
                continue;
            }

            $out[] = [
                'start' => min($span, max(0.0, $segStart - $start)),
                'end' => min($span, max(0.0, $segEnd - $start)),
                'text' => $texto,
                'words' => $words,
            ];
        }

        return $out;
    }

    /**
     * Ensures consistency between the text (possibly edited by the user)
     * and the word list used by karaoke mode.
     *
     * If the current text matches the existing words, keeps the original
     * (precise, Whisper) times. If the text was edited, redistributes the
     * new words uniformly across the window [start, end] so that karaoke
     * mode keeps working.
     *
     * @param  array<int,array<string,mixed>>  $existingWords
     * @return array<int,array<string,mixed>>
     */
    public static function alignWords(string $text, float $start, float $end, array $existingWords): array
    {
        $text = trim($text);

        $joined = trim(implode(' ', array_map(
            fn ($w) => trim((string) ($w['word'] ?? '')),
            $existingWords
        )));

        $normalize = fn (string $s) => preg_replace('/\s+/', ' ', trim($s));

        if ($existingWords && $normalize($joined) === $normalize($text)) {
            return $existingWords;
        }

        $tokens = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $n = count($tokens);

        if ($n === 0) {
            return [];
        }

        $span = max(0.0, $end - $start);
        $step = $span > 0 ? $span / $n : 0.0;

        $words = [];
        foreach ($tokens as $i => $tok) {
            $words[] = [
                'word' => $tok,
                'start' => round($start + $i * $step, 3),
                'end' => round($start + ($i + 1) * $step, 3),
            ];
        }

        return $words;
    }
}
