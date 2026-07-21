<?php

namespace App\Services\Clips;

class PlanValidator
{
    private const EPS = 0.05;

    /**
     * Normalize a plan: clamp animations to [0,duration], drop zero-length ones,
     * sort by start, and — in dense mode — fill gaps with `ambient` so coverage
     * is total. In sparse mode gaps are preserved.
     */
    public function validate(array $plan): array
    {
        $duration = (float) ($plan['duration'] ?? 0.0);
        $mode = $plan['mode'] ?? 'dense';
        $anims = array_values($plan['animations'] ?? []);

        $anims = array_values(array_filter(array_map(function ($a) use ($duration) {
            $a['start'] = max(0.0, min((float) $a['start'], $duration));
            $a['end'] = max(0.0, min((float) $a['end'], $duration));
            $a['params'] = $a['params'] ?? [];
            $a['text'] = $a['text'] ?? null;

            return $a;
        }, $anims), fn ($a) => $a['end'] - $a['start'] > self::EPS));

        usort($anims, fn ($x, $y) => $x['start'] <=> $y['start']);

        if ($mode === 'dense') {
            foreach ($this->coverageGaps($anims, $duration) as [$gs, $ge]) {
                $anims[] = [
                    'start' => $gs, 'end' => $ge,
                    'primitive' => 'ambient', 'text' => null, 'params' => [],
                ];
            }
            usort($anims, fn ($x, $y) => $x['start'] <=> $y['start']);
        }

        $plan['animations'] = array_values($anims);

        return $plan;
    }

    /**
     * @return array<int,array{0:float,1:float}> list of [start,end] uncovered spans
     */
    public function coverageGaps(array $animations, float $duration): array
    {
        $intervals = array_map(fn ($a) => [(float) $a['start'], (float) $a['end']], $animations);
        usort($intervals, fn ($x, $y) => $x[0] <=> $y[0]);

        $gaps = [];
        $cursor = 0.0;
        foreach ($intervals as [$s, $e]) {
            if ($s - $cursor > self::EPS) {
                $gaps[] = [round($cursor, 3), round($s, 3)];
            }
            $cursor = max($cursor, $e);
        }
        if ($duration - $cursor > self::EPS) {
            $gaps[] = [round($cursor, 3), round($duration, 3)];
        }

        return $gaps;
    }
}
