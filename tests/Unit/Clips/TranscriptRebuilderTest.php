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

    public function test_empty_text_returns_empty(): void
    {
        $this->assertSame([], TranscriptRebuilder::rebuild('   ', [['word' => 'a', 'start' => 0, 'end' => 1]], 1.0));
    }
}
