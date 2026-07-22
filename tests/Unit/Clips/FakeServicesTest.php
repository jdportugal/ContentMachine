<?php

namespace Tests\Unit\Clips;

use App\Services\Clips\Fake\FakeAnimationPlanner;
use App\Services\Clips\Fake\FakeTranscriptionService;
use PHPUnit\Framework\TestCase;

class FakeServicesTest extends TestCase
{
    public function test_fake_planner_emits_dense_scenes(): void
    {
        $transcript = (new FakeTranscriptionService)->transcribe('x');
        $plan = (new FakeAnimationPlanner)->plan($transcript, 'dense');

        $this->assertCount(2, $plan['scenes']);
        $this->assertSame(3.0, $plan['duration']);
        $this->assertTrue($plan['scenes'][0]['karaoke']);
        $this->assertNotEmpty($plan['scenes'][0]['layers']);
    }

    public function test_fake_planner_sets_present_modes_for_overlay(): void
    {
        $transcript = (new FakeTranscriptionService)->transcribe('x');
        $plan = (new FakeAnimationPlanner)->plan($transcript, 'dense', ['overlay' => true]);

        $this->assertSame('video', $plan['scenes'][0]['present']);
        $this->assertSame('over', $plan['scenes'][1]['present']);
    }
}
