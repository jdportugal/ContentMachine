<?php

namespace Tests\Unit\Clips;

use App\Services\Clips\BeatOpening;
use PHPUnit\Framework\TestCase;

class BeatOpeningTest extends TestCase
{
    /** Clicks every 0.6 s, each bar's first one loudest: three clips, each change on a bar's first beat. */
    public function test_cuts_on_the_first_beat_of_each_bar(): void
    {
        $sr = 11025;
        $samples = array_fill(0, 16 * $sr, 0);
        for ($k = 0; $k * 0.6 < 15.5; $k++) {
            $at = (int) round($k * 0.6 * $sr);
            $gain = $k % 4 === 0 ? 12000 : 4000;
            for ($j = 0; $j < 600; $j++) {
                $samples[$at + $j] = (int) (($j % 2 ? 1 : -1) * $gain * exp(-$j / 120));
            }
        }

        $cuts = BeatOpening::fromSamples($samples, 3);

        $this->assertCount(4, $cuts);
        $this->assertSame(0.0, $cuts[0]);
        foreach ([2.4, 4.8, 7.2] as $i => $bar) {
            $this->assertEqualsWithDelta($bar, $cuts[$i + 1], 0.05);
        }
    }
}
