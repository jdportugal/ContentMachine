<?php

namespace App\Services\Clips;

class TranscriptRebuilder
{
    /**
     * Rebuild the word/timestamp list from an edited transcript text.
     *
     * If the edited word count matches the original, timestamps are preserved
     * per index (the common case: fixing spelling). Otherwise timestamps are
     * distributed evenly across the clip duration.
     *
     * @return array<int,array{word:string,start:float,end:float}>
     */
    public static function rebuild(string $text, array $originalWords, float $duration): array
    {
        $tokens = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($tokens === []) {
            return [];
        }

        if ($duration <= 0 && $originalWords !== []) {
            $duration = (float) (end($originalWords)['end'] ?? 0.0);
        }

        // Same word count → keep original timings, just swap the text.
        if (count($tokens) === count($originalWords)) {
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

        // Otherwise distribute evenly across the duration.
        $n = count($tokens);
        $slice = $duration > 0 ? $duration / $n : 0.0;
        $out = [];
        foreach ($tokens as $i => $word) {
            $out[] = [
                'word' => $word,
                'start' => round($i * $slice, 3),
                'end' => round(($i + 1) * $slice, 3),
            ];
        }

        return $out;
    }
}
