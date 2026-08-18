<?php

namespace App\Services\Editor;

/**
 * Turns removals into the ranges to KEEP.
 *
 * Pure and deterministic: the same removals always give the same plan. This is
 * the piece both sources share — feeding the identical keep-ranges to the camera
 * and the screen feed is the whole of the sync guarantee.
 */
class CutPlan
{
    /**
     * @param  array<int,Removal>  $removals
     * @return array<int,array{0:float,1:float}> ordered, non-overlapping [start, end] pairs
     */
    public function keepRanges(array $removals, float $duration): array
    {
        if ($duration <= 0) {
            return [];
        }

        $merged = $this->merge($removals, $duration);

        $ranges = [];
        $cursor = 0.0;

        foreach ($merged as [$start, $end]) {
            if ($start > $cursor) {
                $ranges[] = [$cursor, $start];
            }
            $cursor = max($cursor, $end);
        }

        if ($cursor < $duration) {
            $ranges[] = [$cursor, $duration];
        }

        // Drop slivers a frame or two long — they add cuts nobody can perceive.
        return array_values(array_filter($ranges, fn ($r) => $r[1] - $r[0] > 0.05));
    }

    /**
     * Where a moment on the ORIGINAL timeline lands after the cuts, or null if it
     * was removed. Effects are placed on the edited video, so a time taken from
     * the raw transcript would sit late by everything cut before it.
     *
     * @param  array<int,array{0:float,1:float}>  $keepRanges
     */
    public function toEditedTime(float $t, array $keepRanges): ?float
    {
        $decorrido = 0.0;

        foreach ($keepRanges as [$start, $end]) {
            if ($t < $start) {
                return null;    // inside a removed span
            }
            if ($t < $end) {
                return round($decorrido + ($t - $start), 3);
            }
            $decorrido += $end - $start;
        }

        return null;
    }

    /**
     * The transcript as it reads AFTER cutting: segments that survive, with their
     * times rebased. A segment straddling a cut keeps the part that survived.
     *
     * @param  array<int,array<string,mixed>>  $segments
     * @param  array<int,array{0:float,1:float}>  $keepRanges
     * @return array<int,array{start:float,end:float,text:string}>
     */
    public function editedSegments(array $segments, array $keepRanges): array
    {
        $out = [];

        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $texto = trim((string) ($seg['text'] ?? ''));
            if ($texto === '') {
                continue;
            }

            $inicio = $this->toEditedTime((float) ($seg['start'] ?? 0), $keepRanges);
            $fim = $this->toEditedTime((float) ($seg['end'] ?? 0), $keepRanges);

            // Dropped entirely when its start was cut; a cut tail just ends early.
            if ($inicio === null) {
                continue;
            }

            $out[] = ['start' => $inicio, 'end' => $fim ?? $inicio, 'text' => $texto];
        }

        return $out;
    }

    /** Total length of the result, for a progress/summary line. */
    public function keptDuration(array $removals, float $duration): float
    {
        return array_reduce(
            $this->keepRanges($removals, $duration),
            fn (float $t, array $r) => $t + ($r[1] - $r[0]),
            0.0
        );
    }

    /**
     * Clamp to the recording, drop empties, sort, then coalesce overlapping and
     * touching spans — two detectors can easily propose overlapping cuts, and an
     * un-merged overlap would rewind the cursor and emit a negative range.
     *
     * @param  array<int,Removal>  $removals
     * @return array<int,array{0:float,1:float}>
     */
    private function merge(array $removals, float $duration): array
    {
        $spans = [];

        foreach ($removals as $r) {
            $start = max(0.0, min($r->start, $duration));
            $end = max(0.0, min($r->end, $duration));
            if ($end > $start) {
                $spans[] = [$start, $end];
            }
        }

        usort($spans, fn ($a, $b) => $a[0] <=> $b[0]);

        $merged = [];
        foreach ($spans as $span) {
            $ultimo = count($merged) - 1;
            if ($ultimo >= 0 && $span[0] <= $merged[$ultimo][1]) {
                $merged[$ultimo][1] = max($merged[$ultimo][1], $span[1]);

                continue;
            }
            $merged[] = $span;
        }

        return $merged;
    }
}
