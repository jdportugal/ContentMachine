<?php

namespace Tests\Unit\Clips;

use App\Services\Clips\Fake\FakeAnimationPlanner;
use App\Services\Clips\Fake\FakeTranscriptionService;
use PHPUnit\Framework\TestCase;

class FakeServicesTest extends TestCase
{
    public function test_fake_planner_emits_one_dense_anim_per_word(): void
    {
        $transcript = (new FakeTranscriptionService)->transcribe('x');
        $plan = (new FakeAnimationPlanner)->plan($transcript, 'dense');

        $this->assertCount(3, $plan['animations']);
        $this->assertSame(3.0, $plan['duration']);
    }

    public function test_fake_planner_is_sparse_with_transparent_flag(): void
    {
        $transcript = (new FakeTranscriptionService)->transcribe('x');
        $plan = (new FakeAnimationPlanner)->plan($transcript, 'sparse');

        $this->assertCount(1, $plan['animations']);
        $this->assertTrue($plan['transparent']);
    }
}
