<?php

namespace App\Providers;

use App\Services\Clips\Api\ClaudeAnimationPlanner;
use App\Services\Clips\Api\ClaudeMetadataService;
use App\Services\Clips\Api\ClaudeResearchService;
use App\Services\Clips\Api\ElevenLabsVoiceoverService;
use App\Services\Clips\Api\OpenAiAnimationPlanner;
use App\Services\Clips\Api\OpenAiTranscriptionService;
use App\Services\Clips\CliRemotionRenderer;
use App\Services\Clips\Contracts\AnimationPlanner;
use App\Services\Clips\Contracts\MetadataService;
use App\Services\Clips\Contracts\RemotionRenderer;
use App\Services\Clips\Contracts\ResearchService;
use App\Services\Clips\Contracts\TranscriptionService;
use App\Services\Clips\Contracts\VideoCompositor;
use App\Services\Clips\Contracts\VoiceoverService;
use App\Services\Clips\Fake\FakeAnimationPlanner;
use App\Services\Clips\Fake\FakeMetadataService;
use App\Services\Clips\Fake\FakeRemotionRenderer;
use App\Services\Clips\Fake\FakeResearchService;
use App\Services\Clips\Fake\FakeTranscriptionService;
use App\Services\Clips\Fake\FakeVideoCompositor;
use App\Services\Clips\Fake\FakeVoiceoverService;
use App\Services\Clips\FfmpegVideoCompositor;
use Illuminate\Support\ServiceProvider;

class ClipsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The clips driver is read at RESOLVE time, not here at register time: the
        // SettingsOverlay (which derives clips.driver=api from the configured LLM
        // key on the deployed image) only runs in a provider's boot(), AFTER every
        // register(). Binding the concrete classes eagerly would freeze them to the
        // pre-overlay 'fake' default with no .env → fake renders in production.
        $api = fn () => config('contentmachine.clips.driver') === 'api';

        $this->app->bind(
            TranscriptionService::class,
            fn ($app) => $app->make($api() ? OpenAiTranscriptionService::class : FakeTranscriptionService::class)
        );
        $this->app->bind(
            VoiceoverService::class,
            fn ($app) => $app->make($api() ? ElevenLabsVoiceoverService::class : FakeVoiceoverService::class)
        );
        $this->app->bind(AnimationPlanner::class, function () use ($api) {
            if (! $api()) {
                return new FakeAnimationPlanner;
            }

            return config('contentmachine.clips.planner') === 'openai'
                ? new OpenAiAnimationPlanner
                : new ClaudeAnimationPlanner;
        });
        $this->app->bind(
            ResearchService::class,
            fn ($app) => $app->make($api() ? ClaudeResearchService::class : FakeResearchService::class)
        );
        $this->app->bind(
            MetadataService::class,
            fn ($app) => $app->make($api() ? ClaudeMetadataService::class : FakeMetadataService::class)
        );
        $this->app->bind(
            RemotionRenderer::class,
            fn ($app) => $app->make($api() ? CliRemotionRenderer::class : FakeRemotionRenderer::class)
        );
        $this->app->bind(
            VideoCompositor::class,
            fn ($app) => $app->make($api() ? FfmpegVideoCompositor::class : FakeVideoCompositor::class)
        );
    }
}
