<?php

namespace Tests\Unit\Shorts;

use App\Services\Shorts\AssSubtitleBuilder;
use App\Services\Shorts\SubtitleShifter;
use PHPUnit\Framework\TestCase;

/**
 * Shifting a transcript into a clip window used to CLAMP every out-of-window word
 * to zero instead of dropping it. A cut landing mid-segment therefore kept all the
 * words spoken before the in-point and fired them all on the first frame.
 */
class SubtitleShiftWindowTest extends TestCase
{
    /** One segment, 0-10s, a word a second. */
    private function transcricao(): array
    {
        $words = [];
        foreach (range(0, 9) as $i) {
            $words[] = ['word' => "w$i", 'start' => (float) $i, 'end' => (float) $i + 1];
        }

        return [['start' => 0.0, 'end' => 10.0, 'text' => 'w0 w1 w2 w3 w4 w5 w6 w7 w8 w9', 'words' => $words]];
    }

    public function test_words_before_the_cut_are_dropped_not_stacked_on_frame_one(): void
    {
        $out = SubtitleShifter::shift($this->transcricao(), 4, 8);

        $palavras = array_column($out[0]['words'], 'word');
        $this->assertSame(['w4', 'w5', 'w6', 'w7'], $palavras);

        // Exactly one word may start at 0 — the first one inside the window.
        $noZero = array_filter($out[0]['words'], fn ($w) => $w['start'] <= 0.0);
        $this->assertCount(1, $noZero, 'more than one caption starting at zero is the bug');
    }

    public function test_the_text_is_trimmed_to_the_words_that_survived(): void
    {
        $out = SubtitleShifter::shift($this->transcricao(), 4, 8);

        // alignWords keeps the real timings only while text and words agree.
        $this->assertSame('w4 w5 w6 w7', $out[0]['text']);
        $this->assertSame(
            $out[0]['words'],
            SubtitleShifter::alignWords($out[0]['text'], $out[0]['start'], $out[0]['end'], $out[0]['words']),
            'the real word timings must survive alignWords',
        );
    }

    public function test_nothing_runs_past_the_end_of_the_clip(): void
    {
        $out = SubtitleShifter::shift($this->transcricao(), 4, 8);
        $duracao = 8 - 4;

        foreach ($out[0]['words'] as $w) {
            $this->assertLessThanOrEqual($duracao, $w['end']);
            $this->assertGreaterThanOrEqual(0.0, $w['start']);
        }
        $this->assertLessThanOrEqual($duracao, $out[0]['end']);
    }

    public function test_a_segment_wholly_inside_the_window_is_untouched_apart_from_the_shift(): void
    {
        $out = SubtitleShifter::shift($this->transcricao(), 0, 10);

        $this->assertSame('w0 w1 w2 w3 w4 w5 w6 w7 w8 w9', $out[0]['text']);
        $this->assertCount(10, $out[0]['words']);
        $this->assertSame(0.0, $out[0]['words'][0]['start']);
        $this->assertSame(10.0, $out[0]['words'][9]['end']);
    }

    public function test_a_segment_with_no_words_still_shifts(): void
    {
        // Some transcripts carry segment times only; even spacing is all there is.
        $out = SubtitleShifter::shift([['start' => 5.0, 'end' => 8.0, 'text' => 'no word times']], 4, 10);

        $this->assertSame('no word times', $out[0]['text']);
        $this->assertSame(1.0, $out[0]['start']);
        $this->assertSame(4.0, $out[0]['end']);
    }

    /** The visible symptom: a wall of dialogue events at 0:00:00. */
    public function test_the_burned_subtitles_do_not_pile_up_at_the_first_frame(): void
    {
        $segs = SubtitleShifter::shift($this->transcricao(), 4, 8);
        foreach ($segs as &$seg) {
            $seg['words'] = SubtitleShifter::alignWords($seg['text'], $seg['start'], $seg['end'], $seg['words']);
        }
        unset($seg);

        // A literal style: this is about WHEN captions fire, not how they look
        // (and estiloPorDefeito() reads the Design System through the container).
        $estilo = ['position' => 'bottom-center', 'font-size' => 58, 'line-color' => '#EAF0FF', 'max-words-per-line' => 4];
        $ass = (new AssSubtitleBuilder)->build($segs, $estilo, 'karaoke', 1080, 1920);

        $emZero = array_filter(
            explode("\n", $ass),
            fn ($l) => str_starts_with($l, 'Dialogue: 0,0:00:00.00,'),
        );

        $this->assertLessThanOrEqual(1, count($emZero), 'captions are stacked on the first frame');
    }
}
