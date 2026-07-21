<?php

namespace Tests\Unit\Clips;

use App\Services\Clips\PlanValidator;
use PHPUnit\Framework\TestCase;

class PlanValidatorTest extends TestCase
{
    public function test_reports_gaps_in_a_timeline(): void
    {
        $gaps = (new PlanValidator)->coverageGaps(
            [['start' => 0, 'end' => 2], ['start' => 3, 'end' => 5]],
            6.0
        );

        $this->assertSame([[2.0, 3.0], [5.0, 6.0]], $gaps);
    }

    public function test_fills_gaps_with_ambient_in_dense_mode(): void
    {
        $plan = [
            'duration' => 6.0, 'mode' => 'dense', 'width' => 1080, 'height' => 1920, 'fps' => 30,
            'animations' => [
                ['start' => 0, 'end' => 2, 'primitive' => 'kinetic-text', 'text' => 'a', 'params' => []],
            ],
        ];

        $out = (new PlanValidator)->validate($plan);

        $this->assertSame([], (new PlanValidator)->coverageGaps($out['animations'], 6.0));
        $primitives = array_column($out['animations'], 'primitive');
        $this->assertContains('ambient', $primitives);
    }

    public function test_keeps_gaps_in_sparse_mode_but_clamps_and_drops_empties(): void
    {
        $plan = [
            'duration' => 5.0, 'mode' => 'sparse', 'width' => 1080, 'height' => 1920, 'fps' => 30,
            'animations' => [
                ['start' => 1, 'end' => 2, 'primitive' => 'highlight', 'text' => null, 'params' => []],
                ['start' => 4, 'end' => 9, 'primitive' => 'seal-stamp', 'text' => null, 'params' => []],
                ['start' => 3, 'end' => 3, 'primitive' => 'fade', 'text' => null, 'params' => []],
            ],
        ];

        $out = (new PlanValidator)->validate($plan);

        $this->assertCount(2, $out['animations']);
        // second animation clamped to duration
        $this->assertSame(5.0, $out['animations'][1]['end']);
    }
}
