<?php

namespace Tests\Unit\Editor;

use App\Services\Editor\CutPlan;
use App\Services\Editor\DeadAirDetector;
use App\Services\Editor\MultiCutEngine;
use App\Services\Editor\Removal;
use Tests\TestCase;

/**
 * The parts that decide what gets cut. All pure, so they are tested directly —
 * a mistake here silently deletes footage or desynchronises the two tracks.
 */
class CutLogicTest extends TestCase
{
    /** @param array<int,array{0:float,1:float}> $words */
    private function segmento(array $words, string $text = 'x'): array
    {
        return [
            'start' => $words[0][0],
            'end' => $words[count($words) - 1][1],
            'text' => $text,
            'words' => array_map(fn ($w) => ['word' => 'w', 'start' => $w[0], 'end' => $w[1]], $words),
        ];
    }

    // ── dead air ─────────────────────────────────────────────────────────

    public function test_gaps_longer_than_the_threshold_become_removals_with_padding_kept(): void
    {
        // words at 0-1, then a 2s gap, then 3-4.
        $segments = [$this->segmento([[0.0, 1.0], [3.0, 4.0]])];

        $removals = (new DeadAirDetector)->detect($segments, threshold: 0.7, padding: 0.15);

        $this->assertCount(1, $removals);
        // Padding is kept on BOTH sides so the cut never clips a consonant.
        $this->assertEqualsWithDelta(1.15, $removals[0]->start, 0.001);
        $this->assertEqualsWithDelta(2.85, $removals[0]->end, 0.001);
        $this->assertSame(Removal::SILENCE, $removals[0]->reason);
    }

    public function test_a_short_gap_is_left_alone(): void
    {
        $segments = [$this->segmento([[0.0, 1.0], [1.4, 2.0]])];

        $this->assertSame([], (new DeadAirDetector)->detect($segments, threshold: 0.7));
    }

    /** Padding must never invert the range on a gap only just over the line. */
    public function test_a_gap_swallowed_by_padding_produces_no_removal(): void
    {
        $segments = [$this->segmento([[0.0, 1.0], [1.75, 2.0]])];   // 0.75s gap, 0.30s of padding

        $removals = (new DeadAirDetector)->detect($segments, threshold: 0.7, padding: 0.4);

        $this->assertSame([], $removals);
    }

    public function test_leading_and_trailing_silence_are_trimmed(): void
    {
        $segments = [$this->segmento([[5.0, 6.0]])];

        $removals = (new DeadAirDetector)->detect($segments, threshold: 0.7, padding: 0.15, duration: 10.0);

        $this->assertCount(2, $removals);
        $this->assertEqualsWithDelta(0.0, $removals[0]->start, 0.001);
        $this->assertEqualsWithDelta(10.0, $removals[1]->end, 0.001);
    }

    public function test_a_segment_without_word_timings_still_yields_gaps(): void
    {
        $segments = [
            ['start' => 0.0, 'end' => 1.0, 'text' => 'a', 'words' => []],
            ['start' => 4.0, 'end' => 5.0, 'text' => 'b', 'words' => []],
        ];

        $this->assertCount(1, (new DeadAirDetector)->detect($segments));
    }

    // ── cut plan ─────────────────────────────────────────────────────────

    public function test_removals_invert_into_keep_ranges(): void
    {
        $plan = new CutPlan;
        $ranges = $plan->keepRanges([new Removal(2.0, 4.0)], 10.0);

        $this->assertSame([[0.0, 2.0], [4.0, 10.0]], $ranges);
        $this->assertEqualsWithDelta(8.0, $plan->keptDuration([new Removal(2.0, 4.0)], 10.0), 0.001);
    }

    /**
     * Two detectors routinely propose overlapping cuts. Un-merged, the second
     * would rewind the cursor and emit a backwards range that ffmpeg would
     * silently drop — losing footage with no error.
     */
    public function test_overlapping_removals_are_merged(): void
    {
        $ranges = (new CutPlan)->keepRanges([
            new Removal(2.0, 5.0),
            new Removal(4.0, 6.0),
            new Removal(1.0, 3.0),
        ], 10.0);

        $this->assertSame([[0.0, 1.0], [6.0, 10.0]], $ranges);
    }

    public function test_removals_are_clamped_to_the_recording(): void
    {
        $ranges = (new CutPlan)->keepRanges([new Removal(-5.0, 2.0), new Removal(8.0, 99.0)], 10.0);

        $this->assertSame([[2.0, 8.0]], $ranges);
    }

    public function test_cutting_everything_leaves_no_ranges(): void
    {
        $this->assertSame([], (new CutPlan)->keepRanges([new Removal(0.0, 10.0)], 10.0));
    }

    public function test_slivers_are_dropped(): void
    {
        // Leaves a 0.01s island, too short to be a real cut.
        $ranges = (new CutPlan)->keepRanges([new Removal(0.0, 5.0), new Removal(5.01, 10.0)], 10.0);

        $this->assertSame([], $ranges);
    }

    // ── edited timeline ──────────────────────────────────────────────────

    /**
     * Effects are placed on the CUT video, so times must be rebased. A time taken
     * from the raw transcript would land late by everything removed before it.
     */
    public function test_times_are_rebased_onto_the_edited_timeline(): void
    {
        $plan = new CutPlan;
        $ranges = $plan->keepRanges([new Removal(2.0, 4.0)], 10.0);   // keep 0-2 and 4-10

        $this->assertEqualsWithDelta(1.0, $plan->toEditedTime(1.0, $ranges), 0.001);
        $this->assertEqualsWithDelta(2.0, $plan->toEditedTime(4.0, $ranges), 0.001);
        $this->assertEqualsWithDelta(4.0, $plan->toEditedTime(6.0, $ranges), 0.001);
        $this->assertNull($plan->toEditedTime(3.0, $ranges), 'a removed moment has no edited time');
    }

    public function test_edited_segments_drop_the_removed_ones_and_shift_the_rest(): void
    {
        $plan = new CutPlan;
        $segments = [
            ['start' => 0.0, 'end' => 1.5, 'text' => 'kept first'],
            ['start' => 2.2, 'end' => 3.5, 'text' => 'this was a bad take'],
            ['start' => 4.5, 'end' => 6.0, 'text' => 'kept last'],
        ];
        $ranges = $plan->keepRanges([new Removal(2.0, 4.0)], 10.0);

        $editados = $plan->editedSegments($segments, $ranges);

        $this->assertCount(2, $editados);
        $this->assertSame('kept first', $editados[0]['text']);
        $this->assertSame('kept last', $editados[1]['text']);
        $this->assertEqualsWithDelta(2.5, $editados[1]['start'], 0.001);
    }

    // ── the ffmpeg command ───────────────────────────────────────────────

    /**
     * One pass, not N cuts and a concat. `+` is a logical OR in an ffmpeg
     * expression, and setpts/asetpts are what close the gaps — without them the
     * removed spans stay as frozen video.
     */
    public function test_the_cut_filter_selects_every_kept_range_and_rebuilds_timestamps(): void
    {
        $args = (new MultiCutEngine)->buildCutArgs('in.mp4', [[0.0, 2.0], [4.0, 10.5]], 'out.mp4');

        $vf = $args[array_search('-vf', $args, true) + 1];
        $af = $args[array_search('-af', $args, true) + 1];

        $this->assertSame("select='between(t,0,2)+between(t,4,10.5)',setpts=N/FRAME_RATE/TB", $vf);
        $this->assertSame("aselect='between(t,0,2)+between(t,4,10.5)',asetpts=N/SR/TB", $af);
    }

    /** Both tracks must get the SAME filter — that is the whole sync guarantee. */
    public function test_both_sources_are_cut_with_an_identical_filter(): void
    {
        $engine = new MultiCutEngine;
        $ranges = [[0.0, 2.0], [5.0, 9.0]];

        $camera = $engine->buildCutArgs('camera.mp4', $ranges, 'camera-out.mp4');
        $screen = $engine->buildCutArgs('screen.mp4', $ranges, 'screen-out.mp4');

        $this->assertSame(
            $camera[array_search('-vf', $camera, true) + 1],
            $screen[array_search('-vf', $screen, true) + 1]
        );
    }

    public function test_cutting_with_no_ranges_refuses_rather_than_writing_an_empty_file(): void
    {
        $this->expectExceptionMessage('Nothing left to keep');

        (new MultiCutEngine)->cut('in.mp4', [], 'out.mp4');
    }
}
