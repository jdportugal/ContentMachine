<?php

namespace App\Services\Clips\Api;

use App\Services\Clips\Contracts\AnimationPlanner;

/**
 * Animation planner backed by the authenticated `claude` CLI (uses the user's
 * Claude subscription, not per-token API billing).
 */
class ClaudeAnimationPlanner implements AnimationPlanner
{
    use BuildsAnimationPrompt;
    use RunsClaudeCli;

    protected function passo(): string
    {
        return 'clips_plano';
    }

    public function plan(array $transcript, string $mode, array $options = []): array
    {
        $envelope = $this->runClaude(
            $this->userPrompt($transcript, $mode, (float) ($transcript['duration'] ?? 0.0), $options['facts'] ?? [], $options['images'] ?? []),
            $this->systemPrompt($mode, (bool) ($options['overlay'] ?? false), $options['presents'] ?? [], (bool) ($options['can_generate_images'] ?? true)),
            ['maxTurns' => 1],
        );

        $decoded = $this->extractJson((string) ($envelope['result'] ?? ''));

        return $this->envelope($transcript, $mode, $options, $decoded['scenes'] ?? [], is_string($decoded['background'] ?? null) ? $decoded['background'] : null);
    }
}
