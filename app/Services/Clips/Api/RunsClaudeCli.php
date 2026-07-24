<?php

namespace App\Services\Clips\Api;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Runs the authenticated `claude` CLI headlessly (uses the Claude subscription).
 * Retries transient failures (API overload, streaming hiccups, concurrent use).
 */
trait RunsClaudeCli
{
    /**
     * @param  array{maxTurns?:int,allowedTools?:string,timeout?:int}  $opts
     * @return array the parsed result envelope
     */
    protected function runClaude(string $user, ?string $system = null, array $opts = []): array
    {
        $binary = config('contentmachine.clips.claude_binary');
        $attempts = max(1, (int) config('contentmachine.clips.claude_attempts', 3));
        $lastError = 'no detail';

        $args = [$binary, '-p', $user];
        if ($system !== null) {
            $args[] = '--append-system-prompt';
            $args[] = $system;
        }
        $args[] = '--output-format';
        $args[] = 'json';
        if (isset($opts['maxTurns'])) {
            $args[] = '--max-turns';
            $args[] = (string) $opts['maxTurns'];
        }
        if (isset($opts['allowedTools'])) {
            $args[] = '--allowedTools';
            $args[] = $opts['allowedTools'];
        }

        for ($i = 1; $i <= $attempts; $i++) {
            $process = new Process($args);
            $process->setWorkingDirectory(sys_get_temp_dir());
            $process->setTimeout($opts['timeout'] ?? 300);

            try {
                $process->run();
            } catch (\Throwable $e) {
                $lastError = 'process: '.$e->getMessage();
                $this->claudeBackoff($i, $attempts);

                continue;
            }

            if (! $process->isSuccessful()) {
                $lastError = 'exit '.$process->getExitCode().': '
                    .(trim($process->getErrorOutput()) ?: trim(substr($process->getOutput(), 0, 200)) ?: 'no output');
                $this->claudeBackoff($i, $attempts);

                continue;
            }

            $envelope = json_decode($process->getOutput(), true) ?: [];

            if (($envelope['is_error'] ?? false) || ! empty($envelope['api_error_status']) || ! isset($envelope['result'])) {
                $lastError = 'envelope: '.(($envelope['api_error_status'] ?? null) ?: substr($process->getOutput(), 0, 200));
                $this->claudeBackoff($i, $attempts);

                continue;
            }

            return $envelope;
        }

        throw new RuntimeException("Claude CLI failed after {$attempts} attempt(s) — {$lastError}");
    }

    private function claudeBackoff(int $attempt, int $attempts): void
    {
        if ($attempt < $attempts) {
            sleep($attempt * 3);
        }
    }
}
