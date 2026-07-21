<?php

namespace App\Services\Clips\Api;

use App\Services\Clips\Contracts\AnimationPlanner;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Animation planner backed by the authenticated `claude` CLI (uses the user's
 * Claude subscription, not per-token API billing). Runs headlessly with
 * --output-format json and parses the model's JSON from the result envelope.
 */
class ClaudeAnimationPlanner implements AnimationPlanner
{
    use BuildsAnimationPrompt;

    public function plan(array $transcript, string $mode, array $options = []): array
    {
        $binary = config('contentmachine.clips.claude_binary');

        $process = new Process([
            $binary,
            '-p', $this->userPrompt($transcript, $mode, (float) ($transcript['duration'] ?? 0.0)),
            '--append-system-prompt', $this->systemPrompt($mode),
            '--output-format', 'json',
            '--max-turns', '1',
        ]);
        // Run from a neutral dir so it doesn't inherit this project's CLAUDE.md context.
        $process->setWorkingDirectory(sys_get_temp_dir());
        $process->setTimeout(180);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Claude CLI falhou: '.$process->getErrorOutput());
        }

        $envelope = json_decode($process->getOutput(), true) ?: [];

        if (($envelope['is_error'] ?? false) || ! isset($envelope['result'])) {
            throw new RuntimeException('Resposta inesperada do Claude CLI: '.substr($process->getOutput(), 0, 300));
        }

        $decoded = $this->extractJson((string) $envelope['result']);

        return $this->envelope($transcript, $mode, $options, $decoded['animations'] ?? []);
    }
}
