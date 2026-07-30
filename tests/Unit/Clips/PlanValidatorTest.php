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

    public function test_fills_scene_gaps_with_ambient_scene_in_dense_mode(): void
    {
        $plan = [
            'duration' => 6.0, 'mode' => 'dense',
            'scenes' => [
                ['start' => 0, 'end' => 2, 'background' => 'papyrus', 'layers' => [['type' => 'kinetic-text', 'text' => 'a']]],
            ],
        ];

        $out = (new PlanValidator)->validate($plan);

        $this->assertSame([], (new PlanValidator)->coverageGaps($out['scenes'], 6.0));
        // an ambient scene was appended to cover 2..6
        $backgrounds = array_column($out['scenes'], 'background');
        $this->assertContains('papyrus', $backgrounds);
        $this->assertCount(2, $out['scenes']);
    }

    public function test_caps_scene_to_one_foreground_layer_preferring_visual(): void
    {
        $plan = [
            'duration' => 4.0, 'mode' => 'sparse',
            'scenes' => [[
                'start' => 0, 'end' => 4, 'background' => 'papyrus',
                'layers' => [
                    ['type' => 'kinetic-text', 'text' => 'Olá'],
                    ['type' => 'timeline', 'params' => ['items' => [['label' => 'A']]]],
                    ['type' => 'ambient'],
                ],
            ]],
        ];

        $out = (new PlanValidator)->validate($plan);
        $types = array_column($out['scenes'][0]['layers'], 'type');

        // ambient kept + the visual (timeline) chosen over the text layer
        $this->assertContains('ambient', $types);
        $this->assertContains('timeline', $types);
        $this->assertNotContains('kinetic-text', $types);
    }

    public function test_drops_punch_word_not_spoken_in_transcript(): void
    {
        $plan = [
            'duration' => 4.0, 'mode' => 'sparse',
            'scenes' => [
                ['start' => 0, 'end' => 3, 'background' => 'papyrus', 'punchWord' => 'INVENTADO', 'layers' => [['type' => 'timeline', 'params' => ['items' => [['label' => 'A']]]]]],
                ['start' => 0, 'end' => 2, 'background' => 'papyrus', 'punchWord' => 'Olá', 'layers' => [['type' => 'bar-chart', 'params' => ['bars' => [['label' => 'x', 'value' => 1]]]]]],
            ],
        ];

        $out = (new PlanValidator)->validate($plan, 'Olá mundo, isto é um teste.');

        $scenes = collect($out['scenes'])->keyBy(fn ($s) => $s['layers'][0]['type']);
        $this->assertNull($scenes['timeline']['punchWord']);      // not spoken → dropped
        $this->assertSame('Olá', $scenes['bar-chart']['punchWord']); // spoken → kept
    }

    public function test_drops_text_layer_when_scene_has_punch_word(): void
    {
        $plan = [
            'duration' => 4.0, 'mode' => 'sparse',
            'scenes' => [[
                'start' => 0, 'end' => 4, 'background' => 'papyrus', 'punchWord' => 'ÊNFASE',
                'layers' => [['type' => 'kinetic-text', 'text' => 'Olá']],
            ]],
        ];

        $out = (new PlanValidator)->validate($plan);

        $this->assertSame([], $out['scenes'][0]['layers']);
    }

    public function test_assigns_varied_present_modes_for_overlay_when_missing(): void
    {
        $plan = [
            'duration' => 9.0, 'mode' => 'dense',
            'scenes' => [
                ['start' => 0, 'end' => 3, 'background' => 'papyrus', 'layers' => []],
                ['start' => 3, 'end' => 6, 'background' => 'papyrus', 'layers' => [['type' => 'timeline', 'params' => ['items' => [['label' => 'A']]]]]],
                ['start' => 6, 'end' => 9, 'background' => 'papyrus', 'layers' => [['type' => 'bar-chart', 'params' => ['bars' => [['label' => 'x', 'value' => 1]]]]]],
            ],
        ];

        $out = (new PlanValidator)->validate($plan, '', true);
        $presents = array_column($out['scenes'], 'present');

        $this->assertSame('video', $presents[0]);          // no foreground → just the video
        $this->assertContains('over', $presents);          // layer scenes get varied modes
        $this->assertContains('split', $presents);
        $this->assertGreaterThan(1, count(array_unique($presents)));
    }

    public function test_restricts_present_modes_to_the_allowed_set(): void
    {
        // 'over' excluded — a scene the planner set to 'over' must be remapped.
        $plan = [
            'duration' => 6.0, 'mode' => 'dense',
            'scenes' => [
                ['start' => 0, 'end' => 3, 'background' => 'papyrus', 'present' => 'over', 'layers' => [['type' => 'bar-chart', 'params' => ['bars' => [['label' => 'x', 'value' => 1]]]]]],
                ['start' => 3, 'end' => 6, 'background' => 'papyrus', 'layers' => []],
            ],
        ];

        $out = (new PlanValidator)->validate($plan, '', true, ['video', 'split', 'animation']);
        $presents = array_column($out['scenes'], 'present');

        $this->assertNotContains('over', $presents);       // excluded style never used
        foreach ($presents as $pr) {
            $this->assertContains($pr, ['video', 'split', 'animation']);
        }
    }

    public function test_respects_planner_chosen_present_modes(): void
    {
        $plan = [
            'duration' => 3.0, 'mode' => 'dense',
            'scenes' => [['start' => 0, 'end' => 3, 'background' => 'papyrus', 'present' => 'animation', 'layers' => [['type' => 'timeline', 'params' => ['items' => [['label' => 'A']]]]]]],
        ];

        $out = (new PlanValidator)->validate($plan, '', true);

        $this->assertSame('animation', $out['scenes'][0]['present']);
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
