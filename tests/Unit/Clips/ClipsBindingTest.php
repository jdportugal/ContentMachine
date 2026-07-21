<?php

namespace Tests\Unit\Clips;

use App\Services\Clips\Contracts\TranscriptionService;
use App\Services\Clips\Fake\FakeTranscriptionService;
use Tests\TestCase;

class ClipsBindingTest extends TestCase
{
    public function test_resolves_fake_services_by_default(): void
    {
        config()->set('contentmachine.clips.driver', 'fake');

        $this->assertInstanceOf(
            FakeTranscriptionService::class,
            $this->app->make(TranscriptionService::class)
        );
    }
}
