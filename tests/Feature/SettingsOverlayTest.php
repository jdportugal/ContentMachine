<?php

namespace Tests\Feature;

use App\Services\Settings\SettingsOverlay;
use App\Services\Settings\SettingsRepository;
use Tests\TestCase;

class SettingsOverlayTest extends TestCase
{
    private function apply(): void
    {
        app(SettingsOverlay::class)->apply(app(SettingsRepository::class));
    }

    public function test_production_enables_real_monitoring_with_no_env(): void
    {
        $this->app['env'] = 'production';
        config(['contentmachine.monitoring.driver' => 'fake']);

        $this->apply();

        $this->assertSame('ytdlp', config('contentmachine.monitoring.driver'));
    }

    public function test_production_enables_real_clip_and_news_generation_once_an_llm_key_is_set(): void
    {
        $this->app['env'] = 'production';
        config([
            'contentmachine.clips.driver' => 'fake',
            'contentmachine.news.driver' => 'fake',
        ]);
        app(SettingsRepository::class)->save(['chaves' => ['anthropic' => 'sk-ant-test']]);

        $this->apply();

        $this->assertSame('api', config('contentmachine.clips.driver'));
        $this->assertSame('api', config('contentmachine.news.driver'));
    }

    public function test_production_keeps_fake_generation_without_any_llm_key(): void
    {
        $this->app['env'] = 'production';
        config([
            'contentmachine.clips.driver' => 'fake',
            'contentmachine.news.driver' => 'fake',
        ]);

        $this->apply(); // no keys saved

        $this->assertSame('fake', config('contentmachine.clips.driver'));
        $this->assertSame('fake', config('contentmachine.news.driver'));
        // monitoring still goes real (yt-dlp needs no key)
        $this->assertSame('ytdlp', config('contentmachine.monitoring.driver'));
    }

    public function test_local_and_testing_keep_the_fake_defaults(): void
    {
        $this->app['env'] = 'testing';
        config(['contentmachine.monitoring.driver' => 'fake', 'contentmachine.clips.driver' => 'fake']);
        app(SettingsRepository::class)->save(['chaves' => ['anthropic' => 'sk-ant-test']]);

        $this->apply();

        $this->assertSame('fake', config('contentmachine.monitoring.driver'));
        $this->assertSame('fake', config('contentmachine.clips.driver'));
    }

    public function test_an_explicit_non_fake_driver_is_left_alone(): void
    {
        $this->app['env'] = 'production';
        config(['contentmachine.monitoring.driver' => 'api']); // explicit choice

        $this->apply();

        $this->assertSame('api', config('contentmachine.monitoring.driver'));
    }
}
