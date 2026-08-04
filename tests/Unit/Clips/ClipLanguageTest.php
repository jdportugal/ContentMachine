<?php

namespace Tests\Unit\Clips;

use App\Services\Clips\Api\BuildsAnimationPrompt;
use App\Services\Clips\ClipLanguage;
use Tests\TestCase;

/** Generated clip text follows the PROJECT's language, not the transcript's. */
class ClipLanguageTest extends TestCase
{
    private function builder(): object
    {
        return new class
        {
            use BuildsAnimationPrompt;

            public function sys(): string
            {
                return $this->systemPrompt('dense');
            }

            public function user(array $transcript): string
            {
                return $this->userPrompt($transcript, 'dense', 30.0);
            }
        };
    }

    public function test_name_follows_the_active_locale(): void
    {
        $this->app->setLocale('pt');
        $this->assertSame('European Portuguese', ClipLanguage::name());

        $this->app->setLocale('en');
        $this->assertSame('English', ClipLanguage::name());
    }

    public function test_planner_is_told_the_project_language_even_when_the_transcript_differs(): void
    {
        $this->app->setLocale('pt');
        $builder = $this->builder();

        $this->assertStringContainsString('European Portuguese', $builder->sys());

        // Whisper detected English audio — the visible text still follows the project.
        $user = $builder->user(['language' => 'en', 'text' => 'hello there', 'words' => []]);
        $this->assertStringContainsString('European Portuguese', $user);
        $this->assertStringNotContainsString('Transcript language', $user);
    }
}
