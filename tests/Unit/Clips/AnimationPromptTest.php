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

        // Classifica o tipo de clip e dá orientação por tipo.
        $this->assertStringContainsString('TIPO DE CLIP', $prompt);
        $this->assertStringContainsString('TUTORIAL', $prompt);

        // A relevância é a regra de ouro; nada irrelevante entra.
        $this->assertStringContainsString('RELEVÂNCIA É LEI', $prompt);

        // A pesquisa deixou de ser obrigatória em todas as cenas.
        $this->assertStringContainsString('MATÉRIA-PRIMA OPCIONAL', $prompt);
        $this->assertStringNotContainsString('A GRANDE MAIORIA das cenas tem uma VISUALIZAÇÃO', $prompt);
    }
}
