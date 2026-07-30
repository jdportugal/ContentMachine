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
}
