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

            public function sys(string $mode, bool $overlay, array $allowed, string $instructions = ''): string
            {
                return $this->systemPrompt($mode, $overlay, $allowed, true, $instructions);
            }

            public function user(array $transcript): string
            {
                return $this->userPrompt($transcript, 'dense', (float) ($transcript['duration'] ?? 0.0));
            }
        };
    }

    /**
     * Per-clip direction has to land AFTER the project's style manual and say
     * plainly that it wins — otherwise "no charts on this one" loses to a manual
     * that asks for charts everywhere.
     */
    public function test_clip_instructions_come_last_and_override_the_style_manual(): void
    {
        $prompt = $this->builder()->sys('dense', false, [], 'Keep it dark and cinematic. No charts.');

        $this->assertStringContainsString('Keep it dark and cinematic. No charts.', $prompt);
        $this->assertStringContainsString('INSTRUCTIONS FOR THIS CLIP', $prompt);
        $this->assertStringContainsString('THESE WIN', $prompt);
        $this->assertGreaterThan(
            strpos($prompt, 'STYLE MANUAL'),
            strpos($prompt, 'INSTRUCTIONS FOR THIS CLIP'),
            'the clip block must come after the project style manual',
        );
    }

    /**
     * Word timings go in as compact [start, end, word] triples — the verbose
     * per-word objects overflowed the model's context on long videos.
     */
    public function test_word_timestamps_are_compact_triples(): void
    {
        $prompt = $this->builder()->user([
            'duration' => 1.0,
            'text' => 'hello world',
            'words' => [
                ['word' => 'hello', 'start' => 0.101, 'end' => 0.5],
                ['word' => 'world', 'start' => 0.5, 'end' => 1.0],
            ],
        ]);

        $this->assertStringContainsString('[[0.1,0.5,"hello"],[0.5,1,"world"]]', $prompt);
        $this->assertStringNotContainsString('"word":', $prompt);
    }

    public function test_no_instructions_means_no_block_at_all(): void
    {
        $prompt = $this->builder()->sys('dense', false, [], '   ');

        $this->assertStringNotContainsString('INSTRUCTIONS FOR THIS CLIP', $prompt);
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
