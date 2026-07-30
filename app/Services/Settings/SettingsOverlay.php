<?php

namespace App\Services\Settings;

use Throwable;

/**
 * Overlays the vault-stored settings (API keys, model config) onto Laravel's
 * config(). Applied at boot for the default project, and re-applied by the
 * SetActiveProject middleware after switching so each project's own keys/models
 * win. An empty stored value is skipped, keeping the .env/config default.
 */
class SettingsOverlay
{
    /**
     * setting path (in the vault note) => config path it overrides.
     *
     * @var array<string,string>
     */
    private const MAP = [
        'chaves.anthropic' => 'services.anthropic.key',
        'chaves.openai' => 'services.openai.key',
        'chaves.gemini' => 'services.gemini.key',
        'chaves.apify' => 'services.apify.token',
        'chaves.tubelab' => 'services.tubelab.token',
        'chaves.elevenlabs' => 'services.elevenlabs.key',
        'chaves.youtube' => 'services.youtube.key',
        'chaves.reddit_client_id' => 'services.reddit.client_id',
        'chaves.reddit_client_secret' => 'services.reddit.client_secret',
        'chaves.kie' => 'services.kie.key',
        'chaves.blotato' => 'services.blotato.key',
        'modelos.llm_provider' => 'contentmachine.aggregation.llm_provider',
        'modelos.anthropic_model' => 'contentmachine.aggregation.anthropic_model',
        'modelos.openai_model' => 'contentmachine.aggregation.openai_model',
        'modelos.gemini_model' => 'contentmachine.aggregation.gemini_model',
        'modelos.aggregation_limit' => 'contentmachine.aggregation.limite_por_canal',
        'modelos.aggregation_timeout' => 'contentmachine.aggregation.timeout',
        'modelos.elevenlabs_voice' => 'contentmachine.clips.voice_id',
    ];

    public function apply(SettingsRepository $settings): void
    {
        try {
            $all = $settings->all();
        } catch (Throwable) {
            return; // vault not ready — keep .env defaults
        }

        foreach (self::MAP as $from => $to) {
            $value = data_get($all, $from);
            if (is_string($value) ? trim($value) !== '' : filled($value)) {
                config([$to => is_string($value) ? trim($value) : $value]);
            }
        }

        $this->autoDrivers();
    }

    /**
     * Derive the real drivers automatically on the deployed image (production), so
     * a deploy needs NO .env: the image already ships yt-dlp (real monitoring +
     * aggregation), and clip/news generation switches on the moment the keys are
     * set in Settings. Local/testing keep the 'fake' defaults. An explicit non-fake
     * env value (e.g. MONITORING_DRIVER=api) is always honoured.
     */
    private function autoDrivers(): void
    {
        if (! app()->environment('production')) {
            return;
        }

        // Real monitoring — yt-dlp is built into the image; YouTube needs no key.
        if (config('contentmachine.monitoring.driver') === 'fake') {
            config(['contentmachine.monitoring.driver' => 'ytdlp']);
        }

        // Clip + news generation go real once an LLM key is configured (in Settings).
        $hasLlm = filled(config('services.anthropic.key'))
            || filled(config('services.openai.key'))
            || filled(config('services.gemini.key'));

        if ($hasLlm) {
            if (config('contentmachine.news.driver') === 'fake') {
                config(['contentmachine.news.driver' => 'api']);
            }
            if (config('contentmachine.clips.driver') === 'fake') {
                config(['contentmachine.clips.driver' => 'api']);
            }
        }
    }
}
