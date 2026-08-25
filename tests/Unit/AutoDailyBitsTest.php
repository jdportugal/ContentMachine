<?php

namespace Tests\Unit;

use App\Jobs\AutoDailyJob;
use PHPUnit\Framework\TestCase;

class AutoDailyBitsTest extends TestCase
{
    public function test_parses_bits_and_skips_prose_and_leads(): void
    {
        $redacao = implode("\n", [
            'All three items check out. Here are the news bits:',
            '',
            '---',
            '',
            "**DeepSeek's free Claude Code rival hits 180k stars**",
            '> DeepSeek Harness just made every AI agent piece swappable',
            'DeepSeek just dropped a free, open-source rival to Claude Code.',
            'It crossed 180k stars in under a week.',
            '',
            '---',
            '',
            '**Robot runs 100m in 9.39s**',
            "> A robot just beat Usain Bolt's record",
            'A robot just ran the 100 meters faster than any human ever has.',
            '',
            '---',
            '',
            'Skipped items: the data-center satellite photos (no concrete news).',
        ]);

        $bits = AutoDailyJob::bits($redacao);

        $this->assertCount(2, $bits);
        $this->assertSame("DeepSeek's free Claude Code rival hits 180k stars", $bits[0]['title']);
        $this->assertStringContainsString('open-source rival', $bits[0]['script']);
        $this->assertStringNotContainsString('swappable', $bits[0]['script']); // lead is on-screen, not spoken
        $this->assertSame('Robot runs 100m in 9.39s', $bits[1]['title']);
    }

    public function test_empty_or_prose_only_redacao_yields_nothing(): void
    {
        $this->assertSame([], AutoDailyJob::bits(''));
        $this->assertSame([], AutoDailyJob::bits("Nothing verified today.\n\n---\n\nSkipped: everything."));
    }
}
