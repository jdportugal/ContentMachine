<?php

namespace App\Services\Clips\Api;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Runs Claude headlessly. Uses the Anthropic API when a key is configured
 * (`services.anthropic.key`); otherwise the authenticated `claude` CLI (the
 * subscription). Retries transient failures (overload, streaming hiccups).
 */
trait RunsClaudeCli
{
    /**
     * @param  array{maxTurns?:int,allowedTools?:string,timeout?:int}  $opts
     * @return array the parsed result envelope (has a `result` string)
     */
    protected function runClaude(string $user, ?string $system = null, array $opts = []): array
    {
        $tensorxReady = filled(config('services.tensorx.key'));

        // Tensorix (tensorx.ai) as the PRIMARY clip LLM — fully replaces Claude.
        if ($tensorxReady && config('contentmachine.clips.llm_primary') === 'tensorx') {
            return $this->runTensorx($user, $system, $opts);
        }

        try {
            // API only when a key is set — otherwise the authenticated CLI.
            return filled(config('services.anthropic.key'))
                ? $this->runClaudeApi($user, $system, $opts)
                : $this->runClaudeCli($user, $system, $opts);
        } catch (\Throwable $e) {
            // Automatic fallback: Claude is down but Tensorix is configured.
            if ($tensorxReady) {
                Log::warning('Claude failed — falling back to Tensorix: '.$e->getMessage());

                return $this->runTensorx($user, $system, $opts);
            }
            throw $e;
        }
    }

    /**
     * Call the authenticated `claude` CLI (subscription, no API key). Retries
     * transient failures (overload, streaming hiccups).
     *
     * @param  array{maxTurns?:int,allowedTools?:string,timeout?:int}  $opts
     * @return array the parsed result envelope (has a `result` string)
     */
    private function runClaudeCli(string $user, ?string $system, array $opts): array
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

    /**
     * Calls the Anthropic Messages API and returns a CLI-compatible envelope.
     * Enables web search when the caller requested web tools, so research-style
     * prompts keep working. Retries transient failures.
     *
     * @param  array{maxTurns?:int,allowedTools?:string,timeout?:int}  $opts
     * @return array{result:string}
     */
    private function runClaudeApi(string $user, ?string $system, array $opts): array
    {
        $attempts = max(1, (int) config('contentmachine.clips.claude_attempts', 3));
        $usaWeb = str_contains((string) ($opts['allowedTools'] ?? ''), 'Web');

        $payload = [
            'model' => (string) config('contentmachine.aggregation.anthropic_model', 'claude-opus-4-8'),
            'max_tokens' => (int) config('contentmachine.aggregation.anthropic_max_tokens', 8000),
            'messages' => [['role' => 'user', 'content' => $user]],
        ];
        if ($system !== null && $system !== '') {
            $payload['system'] = $system;
        }
        if ($usaWeb) {
            $payload['tools'] = [['type' => 'web_search_20250305', 'name' => 'web_search']];
        }

        $lastError = 'no detail';

        for ($i = 1; $i <= $attempts; $i++) {
            try {
                $r = Http::timeout($opts['timeout'] ?? 300)
                    ->withHeaders([
                        'x-api-key' => (string) config('services.anthropic.key'),
                        'anthropic-version' => '2023-06-01',
                    ])
                    ->post('https://api.anthropic.com/v1/messages', $payload);
            } catch (\Throwable $e) {
                $lastError = 'http: '.$e->getMessage();
                $this->claudeBackoff($i, $attempts);

                continue;
            }

            if (! $r->successful()) {
                $lastError = 'status '.$r->status().': '.substr((string) $r->body(), 0, 200);
                $this->claudeBackoff($i, $attempts);

                continue;
            }

            // Concatenate the text blocks of the response (skips tool_use blocks).
            $texto = collect($r->json('content', []))
                ->where('type', 'text')
                ->pluck('text')
                ->implode('');

            if (trim($texto) !== '') {
                return ['result' => $texto];
            }

            $lastError = 'empty response';
            $this->claudeBackoff($i, $attempts);
        }

        throw new RuntimeException("Claude API failed after {$attempts} attempt(s) — {$lastError}");
    }

    /**
     * Call Tensorix (tensorx.ai) — an OpenAI-compatible chat endpoint fronting
     * DeepSeek et al. Returns a CLI-compatible envelope ({result:string}). Web
     * search is not available here, so research runs on the model's own knowledge.
     * Retries transient failures.
     *
     * @param  array{maxTurns?:int,allowedTools?:string,timeout?:int}  $opts
     * @return array{result:string}
     */
    private function runTensorx(string $user, ?string $system, array $opts): array
    {
        $attempts = max(1, (int) config('contentmachine.clips.claude_attempts', 3));
        $base = rtrim((string) config('services.tensorx.base_url', 'https://api.tensorx.ai/v1'), '/');

        $payload = [
            'model' => (string) config('services.tensorx.model', 'deepseek/deepseek-r1-0528'),
            'messages' => array_values(array_filter([
                ($system !== null && $system !== '') ? ['role' => 'system', 'content' => $system] : null,
                ['role' => 'user', 'content' => $user],
            ])),
        ];

        $lastError = 'no detail';

        for ($i = 1; $i <= $attempts; $i++) {
            try {
                $r = Http::timeout($opts['timeout'] ?? 300)
                    ->withToken((string) config('services.tensorx.key'))
                    ->post($base.'/chat/completions', $payload);
            } catch (\Throwable $e) {
                $lastError = 'http: '.$e->getMessage();
                $this->claudeBackoff($i, $attempts);

                continue;
            }

            if (! $r->successful()) {
                $lastError = 'status '.$r->status().': '.substr((string) $r->body(), 0, 200);
                $this->claudeBackoff($i, $attempts);

                continue;
            }

            $texto = (string) $r->json('choices.0.message.content', '');
            if (trim($texto) !== '') {
                return ['result' => $texto];
            }

            $lastError = 'empty response';
            $this->claudeBackoff($i, $attempts);
        }

        throw new RuntimeException("Tensorix API failed after {$attempts} attempt(s) — {$lastError}");
    }
}
