<?php

namespace App\Services\Clips;

class TranscriptRebuilder
{
    /**
     * Rebuild the word/timestamp list from an edited transcript text.
     *
     * Real ASR timings are non-uniform (short words, pauses), so we preserve
     * them as far as possible:
     *  - same word count → keep timings per index (the common case: spelling).
     *  - otherwise → keep the real timings of the unchanged run of words at the
     *    start and end, and distribute only the edited span evenly across the
     *    gap between those anchors. A full rewrite (no shared prefix/suffix)
     *    falls back to an even distribution across the whole duration.
     *
     * @return array<int,array{word:string,start:float,end:float}>
     */
    public static function rebuild(string $text, array $originalWords, float $duration): array
    {
        $tokens = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($tokens === []) {
            return [];
        }
        $originalWords = array_values($originalWords);

        if ($duration <= 0 && $originalWords !== []) {
            $duration = (float) (end($originalWords)['end'] ?? 0.0);
        }

        $n = count($tokens);
        $m = count($originalWords);

        // Same word count → keep original timings, just swap the text.
        if ($n === $m) {
            $out = [];
            foreach ($tokens as $i => $word) {
                $out[] = [
                    'word' => $word,
                    'start' => (float) $originalWords[$i]['start'],
                    'end' => (float) $originalWords[$i]['end'],
                ];
            }

            return $out;
        }

        // Anchor the unchanged run of words at each end to their real timings.
        $prefix = 0;
        while ($prefix < $n && $prefix < $m
            && self::norm($tokens[$prefix]) === self::norm($originalWords[$prefix]['word'])) {
            $prefix++;
        }
        $suffix = 0;
        while ($suffix < $n - $prefix && $suffix < $m - $prefix
            && self::norm($tokens[$n - 1 - $suffix]) === self::norm($originalWords[$m - 1 - $suffix]['word'])) {
            $suffix++;
        }

        $out = [];
        // Prefix: keep real timings.
        for ($i = 0; $i < $prefix; $i++) {
            $out[] = ['word' => $tokens[$i], 'start' => (float) $originalWords[$i]['start'], 'end' => (float) $originalWords[$i]['end']];
        }

        // Middle (edited) span: distribute evenly across the gap between anchors.
        $midCount = $n - $prefix - $suffix;
        if ($midCount > 0) {
            $windowStart = $prefix > 0 ? (float) $originalWords[$prefix - 1]['end'] : 0.0;
            $windowEnd = $suffix > 0 ? (float) $originalWords[$m - $suffix]['start'] : $duration;
            if ($windowEnd < $windowStart) {
                $windowEnd = $windowStart;
            }
            $slice = ($windowEnd - $windowStart) / $midCount;
            for ($k = 0; $k < $midCount; $k++) {
                $out[] = [
                    'word' => $tokens[$prefix + $k],
                    'start' => round($windowStart + $k * $slice, 3),
                    'end' => round($windowStart + ($k + 1) * $slice, 3),
                ];
            }
        }

        // Suffix: keep real timings.
        for ($k = $suffix; $k > 0; $k--) {
            $oi = $m - $k;
            $out[] = ['word' => $tokens[$n - $k], 'start' => (float) $originalWords[$oi]['start'], 'end' => (float) $originalWords[$oi]['end']];
        }

        return $out;
    }

    /** Normalise a token for matching: lowercase, strip surrounding punctuation. */
    private static function norm(string $word): string
    {
        return preg_replace('/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/u', '', mb_strtolower($word));
    }
}
