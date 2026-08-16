<?php

namespace App\Providers;

use App\Services\Clips\Api\ClaudeAnimationPlanner;
use App\Services\Clips\Api\ClaudeMetadataService;
use App\Services\Clips\Api\ClaudeResearchService;
use App\Services\Clips\Api\ElevenLabsVoiceoverService;
use App\Services\Clips\Api\LocalWhisperTranscriptionService;
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
use App\Services\Settings\StepKey;
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

        // Transcription: local Whisper needs NO key, so it is what runs unless
        // OpenAI is available (or the step is pinned to a key/engine in Settings).
        $this->app->bind(TranscriptionService::class, function ($app) use ($api) {
            if (! $api()) {
                return $app->make(FakeTranscriptionService::class);
            }

            return $app->make(
                $this->useOpenAiStt() ? OpenAiTranscriptionService::class : LocalWhisperTranscriptionService::class
            );
        });
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

    /**
     * Whether to transcribe through OpenAI rather than local Whisper. A key
     * pinned to the transcription step decides it outright ('local' = local
     * Whisper); otherwise `clips.transcriber`, whose 'auto' default means "OpenAI
     * only if there is a key" — so an install with no OpenAI key still transcribes.
     */
    private function useOpenAiStt(): bool
    {
        $fixado = StepKey::provider('clips_transcricao');
        if ($fixado !== '') {
            return $fixado === 'openai';
        }

        return match ((string) config('contentmachine.clips.transcriber', 'auto')) {
            'openai' => true,
            'local' => false,
            default => filled(config('services.openai.key')),
        };
    }
}
