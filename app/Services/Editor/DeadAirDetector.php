<?php

namespace App\Services\Editor;

/**
 * Finds the pauses worth cutting, from word timestamps alone — no AI, so it is
 * instant and gives the same answer every run.
 *
 * A gap longer than $threshold between one word ending and the next starting is
 * dead air. The removal is shrunk by $padding at each end so the cut never
 * clips the consonant at the start or end of a word, which is what makes a
 * silence cut sound abrupt.
 */
class DeadAirDetector
{
    /** @param array<int,array<string,mixed>> $segments transcript segments with words[] */
    public function detect(array $segments, float $threshold = 0.7, float $padding = 0.15, float $duration = 0.0): array
    {
        $words = $this->words($segments);
        $removals = [];

        // Leading silence before the first word.
        if ($words !== [] && $words[0]['start'] > $threshold) {
            $removals[] = new Removal(0.0, max(0.0, $words[0]['start'] - $padding), Removal::SILENCE, 'lead-in');
        }

        for ($i = 1, $n = count($words); $i < $n; $i++) {
            $gapStart = (float) $words[$i - 1]['end'];
            $gapEnd = (float) $words[$i]['start'];

            if ($gapEnd - $gapStart <= $threshold) {
                continue;
            }

            $start = $gapStart + $padding;
            $end = $gapEnd - $padding;

            // Padding can swallow a gap that only just cleared the threshold.
            if ($end > $start) {
                $removals[] = new Removal($start, $end, Removal::SILENCE, $this->rotulo($gapEnd - $gapStart));
            }
        }

        // Trailing silence after the last word, when the duration is known.
        if ($words !== [] && $duration > 0) {
            $last = (float) end($words)['end'];
            if ($duration - $last > $threshold) {
                $removals[] = new Removal(min($duration, $last + $padding), $duration, Removal::SILENCE, 'tail');
            }
        }

        return $removals;
    }

    private function rotulo(float $gap): string
    {
        return number_format($gap, 1).'s pause';
    }

    /**
     * Flatten to words. Falls back to the segment itself when Whisper returned
     * no word timings, so a segment-only transcript still yields sane gaps.
     *
     * @param  array<int,array<string,mixed>>  $segments
     * @return array<int,array{start:float,end:float}>
     */
    private function words(array $segments): array
    {
        $out = [];

        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $palavras = is_array($seg['words'] ?? null) ? $seg['words'] : [];

            if ($palavras === []) {
                $out[] = ['start' => (float) ($seg['start'] ?? 0), 'end' => (float) ($seg['end'] ?? 0)];

                continue;
            }
            foreach ($palavras as $w) {
                if (is_array($w)) {
                    $out[] = ['start' => (float) ($w['start'] ?? 0), 'end' => (float) ($w['end'] ?? 0)];
                }
            }
        }

        usort($out, fn ($a, $b) => $a['start'] <=> $b['start']);

        return $out;
    }
}
