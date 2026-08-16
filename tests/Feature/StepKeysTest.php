<?php

namespace Tests\Feature;

use App\Services\Clips\Api\LocalWhisperTranscriptionService;
use App\Services\Clips\Api\OpenAiTranscriptionService;
use App\Services\Clips\Contracts\TranscriptionService;
use App\Services\Settings\SettingsOverlay;
use App\Services\Settings\SettingsRepository;
use App\Services\Settings\SharedKeys;
use App\Services\Settings\StepKey;
use Tests\TestCase;

/**
 * Several keys per provider, and pinning a pipeline step to one of them.
 */
class StepKeysTest extends TestCase
{
    private function apply(): void
    {
        app(SettingsOverlay::class)->apply(app(SettingsRepository::class));
    }

    public function test_a_provider_holds_several_keys_and_the_first_is_the_default(): void
    {
        $keys = app(SharedKeys::class);
        $a = $keys->add('openai', 'sk-one', 'Personal');
        $b = $keys->add('openai', 'sk-two', 'Client');

        $this->assertSame('openai:1', $a);
        $this->assertSame('openai:2', $b);
        $this->assertSame('sk-one', $keys->all()['openai'], 'the first key is the provider default');
        $this->assertSame('sk-two', $keys->value($b));
        $this->assertCount(2, $keys->entries()['openai']);
    }

    public function test_removing_one_key_leaves_the_others(): void
    {
        $keys = app(SharedKeys::class);
        $keys->add('openai', 'sk-one');
        $b = $keys->add('openai', 'sk-two');

        $keys->remove($b);

        $this->assertNull($keys->value($b));
        $this->assertSame('sk-one', $keys->all()['openai']);
    }

    public function test_the_legacy_flat_file_is_read_as_a_single_key(): void
    {
        file_put_contents(
            config('contentmachine.settings.keys_path'),
            json_encode(['openai' => 'sk-legacy'])
        );

        $keys = app(SharedKeys::class);

        $this->assertSame('sk-legacy', $keys->all()['openai']);
        $this->assertSame('sk-legacy', $keys->value('openai:1'));
    }

    public function test_a_step_pinned_to_a_key_uses_that_one_and_the_others_keep_the_default(): void
    {
        $keys = app(SharedKeys::class);
        $keys->add('openai', 'sk-default');
        $segunda = $keys->add('openai', 'sk-for-the-plan');

        app(SettingsRepository::class)->save(['passos' => ['clips_plano' => $segunda]]);
        $this->apply();

        $this->assertSame('sk-for-the-plan', StepKey::key('clips_plano', 'openai'));
        $this->assertSame('openai', StepKey::provider('clips_plano'));
        // An unbound step still gets the provider's default key.
        $this->assertSame('sk-default', StepKey::key('clips_metadados', 'openai'));
    }

    public function test_a_binding_whose_key_was_deleted_falls_back_to_auto(): void
    {
        $keys = app(SharedKeys::class);
        $keys->add('openai', 'sk-default');
        $segunda = $keys->add('openai', 'sk-gone');

        app(SettingsRepository::class)->save(['passos' => ['clips_plano' => $segunda]]);
        $keys->remove($segunda);
        $this->apply();

        $this->assertSame('', StepKey::provider('clips_plano'));
        $this->assertSame('sk-default', StepKey::key('clips_plano', 'openai'));
    }

    public function test_transcription_runs_on_local_whisper_when_there_is_no_openai_key(): void
    {
        config(['contentmachine.clips.driver' => 'api', 'services.openai.key' => null]);

        $this->assertInstanceOf(LocalWhisperTranscriptionService::class, app(TranscriptionService::class));
    }

    public function test_transcription_can_be_pinned_to_local_whisper_even_with_an_openai_key(): void
    {
        config(['contentmachine.clips.driver' => 'api']);
        app(SharedKeys::class)->add('openai', 'sk-present');
        app(SettingsRepository::class)->save(['passos' => ['clips_transcricao' => 'local']]);
        $this->apply();

        $this->assertInstanceOf(LocalWhisperTranscriptionService::class, app(TranscriptionService::class));

        // …and back to OpenAI when the step is pinned to an OpenAI key instead.
        app(SettingsRepository::class)->save(['passos' => ['clips_transcricao' => 'openai:1']]);
        $this->apply();

        $this->assertInstanceOf(OpenAiTranscriptionService::class, app(TranscriptionService::class));
    }
}
