<?php

namespace App\Providers;

use App\Services\Clips\Api\ElevenLabsVoiceoverService;
use App\Services\Clips\Api\OpenAiAnimationPlanner;
use App\Services\Clips\Api\OpenAiTranscriptionService;
use App\Services\Clips\CliRemotionRenderer;
use App\Services\Clips\Contracts\AnimationPlanner;
use App\Services\Clips\Contracts\RemotionRenderer;
use App\Services\Clips\Contracts\TranscriptionService;
use App\Services\Clips\Contracts\VideoCompositor;
use App\Services\Clips\Contracts\VoiceoverService;
use App\Services\Clips\Fake\FakeAnimationPlanner;
use App\Services\Clips\Fake\FakeRemotionRenderer;
use App\Services\Clips\Fake\FakeTranscriptionService;
use App\Services\Clips\Fake\FakeVideoCompositor;
use App\Services\Clips\Fake\FakeVoiceoverService;
use App\Services\Clips\FfmpegVideoCompositor;
use Illuminate\Support\ServiceProvider;

class ClipsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $api = config('contentmachine.clips.driver') === 'api';

        $this->app->bind(
            TranscriptionService::class,
            $api ? OpenAiTranscriptionService::class : FakeTranscriptionService::class
        );
        $this->app->bind(
            VoiceoverService::class,
            $api ? ElevenLabsVoiceoverService::class : FakeVoiceoverService::class
        );
        $this->app->bind(
            AnimationPlanner::class,
            $api ? OpenAiAnimationPlanner::class : FakeAnimationPlanner::class
        );
        $this->app->bind(
            RemotionRenderer::class,
            $api ? CliRemotionRenderer::class : FakeRemotionRenderer::class
        );
        $this->app->bind(
            VideoCompositor::class,
            $api ? FfmpegVideoCompositor::class : FakeVideoCompositor::class
        );
    }
}
