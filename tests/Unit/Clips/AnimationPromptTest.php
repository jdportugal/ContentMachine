<?php

namespace Tests\Unit\Clips;

use App\Services\Clips\Api\BuildsAnimationPrompt;
use Tests\TestCase;

class AnimationPromptTest extends TestCase
{
    private function builder(): object
    {
        return new class
        {
            use BuildsAnimationPrompt;

            public function sys(string $mode, bool $overlay, array $allowed): string
            {
                return $this->systemPrompt($mode, $overlay, $allowed);
            }
        };
    }

    public function test_prompt_is_relevance_first_and_clip_type_aware(): void
    {
        $prompt = $this->builder()->sys('dense', true, ['video', 'over', 'split', 'animation']);

        // Classifies the clip type and gives per-type guidance.
        $this->assertStringContainsString('CLIP TYPE', $prompt);
        $this->assertStringContainsString('TUTORIAL', $prompt);

        // Relevance is the golden rule; nothing irrelevant gets in.
        $this->assertStringContainsString('RELEVANCE IS LAW', $prompt);

        // Research is no longer mandatory in every scene.
        $this->assertStringContainsString('OPTIONAL RAW MATERIAL', $prompt);
        $this->assertStringNotContainsString('MAJORITY of scenes has a VISUALIZATION', $prompt);
    }
}
