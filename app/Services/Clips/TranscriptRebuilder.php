<?php

namespace App\Services\Clips;

class TranscriptRebuilder
{
    /**
     * Rebuild the word/timestamp list from an edited transcript text.
     *
     * Real ASR timings are non-uniform (short words, pauses) and everything
     * downstream is cut on them (scenes, the after-intro split, footage on the
     * voiceover's topics), so they are kept wherever the edit allows: the edited
     * words are aligned to the heard ones (longest common subsequence, ignoring
     * case and punctuation). Matched words keep their real timings; between two
     * matches, the edited run takes the heard run's timings one-for-one when
     * they are the same length (a spelling fix, "6.000" for "seis mil" is
     * not), else shares that run's real span evenly. Only a full rewrite ends up
     * evenly spread across the whole duration.
     *
     * @return array<int,array{word:string,start:float,end:float}>
     */
    public static function rebuild(string $text, array $originalWords, float $duration): array
    {
        $tokens = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($tokens === []) {
            return [];
        }
        $orig = array_values($originalWords);
        if ($duration <= 0 && $orig !== []) {
            $duration = (float) (end($orig)['end'] ?? 0.0);
        }
        [$n, $m] = [count($tokens), count($orig)];
        $a = array_map(self::norm(...), $tokens);
        $b = array_map(fn ($w) => self::norm((string) ($w['word'] ?? '')), $orig);

        // LCS table, then the matched pairs (token i ↔ heard word j), in order.
        $dp = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $dp[$i][$j] = $a[$i] !== '' && $a[$i] === $b[$j] ? $dp[$i + 1][$j + 1] + 1 : max($dp[$i + 1][$j], $dp[$i][$j + 1]);
            }
        }
        $pairs = [];
        for ($i = 0, $j = 0; $i < $n && $j < $m;) {
            if ($a[$i] !== '' && $a[$i] === $b[$j]) {
                $pairs[] = [$i++, $j++];
            } elseif ($dp[$i + 1][$j] >= $dp[$i][$j + 1]) {
                $i++;
            } else {
                $j++;
            }
        }
        $pairs[] = [$n, $m];   // sentinel: the end

        $out = [];
        [$ti, $oj] = [0, 0];   // the next token / heard word not yet placed
        foreach ($pairs as [$pi, $pj]) {
            // The edited run tokens[$ti..$pi) stands where the heard run orig[$oj..$pj) was.
            $run = $pi - $ti;
            $heard = $pj - $oj;
            if ($run > 0 && $run === $heard) {
                for ($k = 0; $k < $run; $k++) {
                    $out[] = ['word' => $tokens[$ti + $k], 'start' => (float) $orig[$oj + $k]['start'], 'end' => (float) $orig[$oj + $k]['end']];
                }
            } elseif ($run > 0) {
                $from = $heard > 0 ? (float) $orig[$oj]['start'] : ($oj > 0 ? (float) $orig[$oj - 1]['end'] : 0.0);
                $to = $heard > 0 ? (float) $orig[$pj - 1]['end'] : ($pj < $m ? (float) $orig[$pj]['start'] : $duration);
                $to = max($to, $from);
                $slice = ($to - $from) / $run;
                for ($k = 0; $k < $run; $k++) {
                    $out[] = ['word' => $tokens[$ti + $k], 'start' => round($from + $k * $slice, 3), 'end' => round($from + ($k + 1) * $slice, 3)];
                }
            }
            if ($pi < $n) {
                $out[] = ['word' => $tokens[$pi], 'start' => (float) $orig[$pj]['start'], 'end' => (float) $orig[$pj]['end']];
            }
            [$ti, $oj] = [$pi + 1, $pj + 1];
        }

        return $out;
    }

    /** Normalise a token for matching: lowercase, strip surrounding punctuation. */
    private static function norm(string $word): string
    {
        return preg_replace('/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/u', '', mb_strtolower($word));
    }
}
