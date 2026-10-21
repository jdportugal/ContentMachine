<?php

namespace Tests\Unit\Clips;

use App\Services\Clips\TranscriptRebuilder;
use PHPUnit\Framework\TestCase;

class TranscriptRebuilderTest extends TestCase
{
    public function test_preserves_timings_when_word_count_matches(): void
    {
        $original = [
            ['word' => 'Marquina', 'start' => 0.0, 'end' => 1.0],
            ['word' => 'de', 'start' => 1.0, 'end' => 1.5],
        ];

        $out = TranscriptRebuilder::rebuild('Máquina de', $original, 1.5);

        $this->assertSame('Máquina', $out[0]['word']);
        $this->assertSame(0.0, $out[0]['start']);
        $this->assertSame(1.0, $out[0]['end']);
        $this->assertSame('de', $out[1]['word']);
    }

    public function test_distributes_evenly_when_word_count_differs(): void
    {
        $original = [['word' => 'a', 'start' => 0.0, 'end' => 4.0]];

        $out = TranscriptRebuilder::rebuild('um dois três quatro', $original, 4.0);

        $this->assertCount(4, $out);
        $this->assertSame(0.0, $out[0]['start']);
        $this->assertSame(1.0, $out[0]['end']);
        $this->assertSame(4.0, $out[3]['end']);
    }

    public function test_inserting_a_word_preserves_real_timings_of_unchanged_words(): void
    {
        // Real (non-uniform) ASR timings, with a pause between "manter" and "isto".
        $original = [
            ['word' => 'Para', 'start' => 0.0, 'end' => 0.3],
            ['word' => 'manter', 'start' => 0.3, 'end' => 0.9],
            ['word' => 'isto', 'start' => 1.2, 'end' => 1.6],
            ['word' => 'simples', 'start' => 1.6, 'end' => 2.4],
        ];

        // User inserts "bem" between "manter" and "isto".
        $out = TranscriptRebuilder::rebuild('Para manter bem isto simples', $original, 2.4);

        $this->assertCount(5, $out);
        // Prefix words keep their real timings.
        $this->assertSame(0.3, $out[1]['start']);    // manter
        $this->assertSame(0.9, $out[1]['end']);      // manter
        // Inserted word slots into the gap between the anchors.
        $this->assertSame('bem', $out[2]['word']);
        $this->assertSame(0.9, $out[2]['start']);
        $this->assertSame(1.2, $out[2]['end']);
        // Suffix words keep their REAL timings — not redistributed evenly.
        $this->assertSame('isto', $out[3]['word']);
        $this->assertSame(1.2, $out[3]['start']);
        $this->assertSame(1.6, $out[3]['end']);
        $this->assertSame('simples', $out[4]['word']);
        $this->assertSame(1.6, $out[4]['start']);
        $this->assertSame(2.4, $out[4]['end']);
    }

    public function test_empty_text_returns_empty(): void
    {
        $this->assertSame([], TranscriptRebuilder::rebuild('   ', [['word' => 'a', 'start' => 0, 'end' => 1]], 1.0));
    }

    /** An edit near the start (the heard "seis mil" written "6.000") must not throw away every later timing. */
    public function test_an_early_edit_keeps_the_real_timings_after_it(): void
    {
        $original = [
            ['word' => 'Em', 'start' => 0.0, 'end' => 0.2], ['word' => 'Montargil', 'start' => 0.2, 'end' => 0.9],
            ['word' => 'seis', 'start' => 1.0, 'end' => 1.3], ['word' => 'mil', 'start' => 1.3, 'end' => 1.6],
            ['word' => 'metros', 'start' => 1.7, 'end' => 2.1],
            ['word' => 'Se', 'start' => 3.9, 'end' => 4.0], ['word' => 'queres', 'start' => 4.0, 'end' => 4.4],
        ];

        $out = TranscriptRebuilder::rebuild('Em Montargil, 6.000 metros. Se queres', $original, 4.4);

        $this->assertSame(['Em', 'Montargil,', '6.000', 'metros.', 'Se', 'queres'], array_column($out, 'word'));
        $this->assertSame([1.0, 1.6], [$out[2]['start'], $out[2]['end']]);   // "6.000" takes the span of "seis mil"
        $this->assertSame([3.9, 4.0], [$out[4]['start'], $out[4]['end']]);   // "Se" keeps its real time
    }

    /** Same word count but words moved: aligned, not paired by position. */
    public function test_a_moved_word_does_not_shift_the_rest(): void
    {
        $original = [
            ['word' => 'comprar', 'start' => 0.0, 'end' => 0.5], ['word' => 'ou', 'start' => 0.5, 'end' => 0.7],
            ['word' => 'vender', 'start' => 0.7, 'end' => 1.2], ['word' => 'casa', 'start' => 1.2, 'end' => 1.6],
        ];

        $out = TranscriptRebuilder::rebuild('comprar ou vender casa', $original, 1.6);

        $this->assertSame([1.2, 1.6], [$out[3]['start'], $out[3]['end']]);
    }
}
