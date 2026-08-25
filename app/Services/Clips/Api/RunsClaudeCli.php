<?php

namespace App\Services\Clips\Api;

use App\Services\Settings\StepKey;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Runs the clip pipeline's LLM calls against whichever provider is configured,
 * in the house order: CLAUDE (Anthropic API when a key is set, else the
 * authenticated `claude` CLI) → GPT (OpenAI) → DEEPSEEK (Tensorix). The provider
 * picked in Settings (`clips.llm_primary`) goes first; the others stay behind it,
 * so one being down or unconfigured never stops the pipeline. Each retries its own
 * transient failures (overload, streaming hiccups).
 */
trait RunsClaudeCli
{
    /**
     * Cached `claude` binary lookup (per process) — probing costs a subprocess.
     * Keyed BY BINARY PATH: a single bool would answer for whichever path was
     * probed first, so changing clips.claude_binary would get a stale verdict.
     *
     * @var array<string,bool>
     */
    private static array $claudeBinExists = [];

    /**
     * Pipeline step this service is (see config contentmachine.passos), so the
     * user can pin it to one specific key in Settings. '' = not a listed step.
     */
    protected function passo(): string
    {
        return '';
    }

    /** The key to use for a chain provider, honouring this step's binding. */
    private function chaveLlm(string $fornecedor): string
    {
        return StepKey::key($this->passo(), $fornecedor === 'claude' ? 'anthropic' : $fornecedor);
    }

    /**
     * @param  array{maxTurns?:int,allowedTools?:string,timeout?:int}  $opts
     * @return array the parsed result envelope (has a `result` string)
     */
    protected function runClaude(string $user, ?string $system = null, array $opts = []): array
    {
        $erro = null;

        foreach ($this->cadeiaLlm() as $fornecedor) {
            try {
                return match ($fornecedor) {
                    'claude' => $this->usaApiClaude()
                        ? $this->runClaudeApi($user, $system, $opts)
                        : $this->runClaudeCli($user, $system, $opts),
                    'openai' => $this->runOpenAi($user, $system, $opts),
                    'tensorx' => $this->runTensorx($user, $system, $opts),
                };
            } catch (\Throwable $e) {
                $erro = $e;
                Log::warning("Clip LLM '{$fornecedor}' failed — trying the next: ".$e->getMessage());
            }
        }

        throw $erro ?? new RuntimeException('No LLM is configured for clips — set a Claude, OpenAI or Tensorix key in Settings.');
    }

    /**
     * Providers to try, in order: the key pinned to THIS step first (Settings →
     * Steps), else the one chosen globally, then Claude → GPT → DeepSeek. Only
     * those actually configured. The rest stay behind as fallback, so a pinned
     * provider being down never stops the pipeline.
     *
     * @return array<int,string>
     */
    private function cadeiaLlm(): array
    {
        $ordem = ['claude', 'openai', 'tensorx'];

        // A step pinned to one key implies its provider — that wins over the global choice.
        $fixado = match (StepKey::provider($this->passo())) {
            'anthropic' => 'claude',
            'openai' => 'openai',
            'tensorx' => 'tensorx',
            default => '',
        };

        $escolhido = $fixado ?: (string) config('contentmachine.clips.llm_primary', '');
        if (in_array($escolhido, $ordem, true)) {
            $ordem = array_merge([$escolhido], array_values(array_diff($ordem, [$escolhido])));
        }

        return array_values(array_filter($ordem, fn (string $f) => match ($f) {
            'claude' => filled($this->chaveLlm('claude')) || $this->claudeBinaryExists(),
            'openai' => filled($this->chaveLlm('openai')),
            'tensorx' => filled($this->chaveLlm('tensorx')),
        }));
    }

    /**
     * Reach Claude over the HTTP API, or over the CLI?
     *
     * There are two kinds of Anthropic credential and they are not
     * interchangeable. An API key (`sk-ant-api…`) is an API credential — use the
     * API. An OAuth token (`sk-ant-oat…`) is a SUBSCRIPTION credential: it
     * authenticates against the Messages API but is rate-limited to uselessness
     * there (a flat 429), and it is the very credential the authenticated `claude`
     * CLI already holds. So when that CLI is installed, that is where it belongs.
     *
     * Without a CLI (a container) an OAuth token still tries the API, so the
     * failure is reported rather than hidden.
     *
     * Routing OAuth installs to the CLI also means Remotion-writing callers
     * finally get `useRemotionSkills` — the skills only exist on the CLI path.
     */
    private function usaApiClaude(): bool
    {
        $key = $this->chaveLlm('claude');
        if ($key === '') {
            return false;   // no key at all → the authenticated CLI, as before
        }

        return ! (str_starts_with($key, 'sk-ant-oat') && $this->claudeBinaryExists());
    }

    /** Whether the `claude` CLI is actually installed (a server usually has no session). */
    private function claudeBinaryExists(): bool
    {
        $bin = (string) config('contentmachine.clips.claude_binary', 'claude');

        if (isset(self::$claudeBinExists[$bin])) {
            return self::$claudeBinExists[$bin];
        }

        if (str_contains($bin, '/')) {
            return self::$claudeBinExists[$bin] = is_executable($bin);
        }

        $p = new Process(['bash', '-lc', 'command -v '.escapeshellarg($bin)]);
        $p->setTimeout(10);
        $p->run();

        return self::$claudeBinExists[$bin] = $p->isSuccessful() && trim($p->getOutput()) !== '';
    }

    /**
     * GPT (OpenAI). Same chat-completions shape as Tensorix — the clip prompts are
     * plain text in, text out, so any of the three can serve them.
     *
     * @param  array{maxTurns?:int,allowedTools?:string,timeout?:int}  $opts
     */
    private function runOpenAi(string $user, ?string $system, array $opts): array
    {
        return $this->runChatCompletions(
            'OpenAI',
            'https://api.openai.com/v1',
            $this->chaveLlm('openai'),
            (string) config('contentmachine.clips.openai_model', 'gpt-4o'),
            $user,
            $system,
            $opts,
        );
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

        // Remotion-writing callers (SFX/background generators) set useRemotionSkills:
        // when the official Remotion skills are installed locally (npx skills add
        // remotion-dev/skills), run the CLI from the project root so it can load them,
        // and tell the model to consult them before writing the component. API/chat
        // providers can't use skills, so this lives only on the CLI path.
        $remotionSkills = ($opts['useRemotionSkills'] ?? false)
            && is_dir(base_path('.claude/skills/remotion-best-practices'));
        if ($remotionSkills) {
            $system = trim(($system ?? '')."\n\nThe official Remotion skills are installed in this project. BEFORE writing the component, load the relevant Remotion guidance with the Skill tool — start with 'remotion-best-practices' — and follow it.");
            $opts['allowedTools'] = trim(($opts['allowedTools'] ?? '').',Skill,Read', ',');
        }

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
            $process->setWorkingDirectory($remotionSkills ? base_path() : sys_get_temp_dir());
            $process->setTimeout($opts['timeout'] ?? 300);

            try {
                $process->run();
            } catch (\Throwable $e) {
                $lastError = 'process: '.$e->getMessage();
                $this->claudeBackoff($i, $attempts);

                continue;
            }

            if (! $process->isSuccessful()) {
                $lastError = 'exit '.$process->getExitCode().': '.$this->cliFailureReason($process);
                $this->claudeBackoff($i, $attempts);

                continue;
            }

            $envelope = json_decode($process->getOutput(), true) ?: [];

            if (($envelope['is_error'] ?? false) || ! empty($envelope['api_error_status']) || ! isset($envelope['result'])) {
                $lastError = 'envelope: '.$this->cliFailureReason($process);
                $this->claudeBackoff($i, $attempts);

                continue;
            }

            return $envelope;
        }

        throw new RuntimeException("Claude CLI failed after {$attempts} attempt(s) — {$lastError}");
    }

    /**
     * Why a `claude` CLI run failed, in one line.
     *
     * The CLI reports failures INSIDE its JSON envelope (stderr is usually empty),
     * and the useful fields sit past the long `usage` block — so a blind prefix of
     * the raw output truncates mid-word and tells you nothing. Pull the fields that
     * actually say what happened, and only fall back to raw output if it is not JSON.
     */
    private function cliFailureReason(Process $process): string
    {
        if ($stderr = trim($process->getErrorOutput())) {
            return substr($stderr, 0, 400);
        }

        $out = trim($process->getOutput());
        $envelope = json_decode($out, true);
        if (! is_array($envelope)) {
            return $out !== '' ? substr($out, 0, 400) : 'no output';
        }

        // `result` carries the CLI's own error text when is_error is set.
        $parts = array_filter([
            $envelope['api_error_status'] ?? null,
            $envelope['subtype'] ?? null,
            isset($envelope['stop_reason']) ? 'stop_reason='.$envelope['stop_reason'] : null,
            is_string($envelope['result'] ?? null) ? substr($envelope['result'], 0, 400) : null,
        ]);

        return $parts ? implode(' · ', $parts) : substr($out, 0, 400);
    }

    /**
     * Auth headers for the Anthropic Messages API.
     *
     * There are TWO kinds of Anthropic credential and they do not share a header.
     * An API key (`sk-ant-api…`) goes on `x-api-key`. An OAuth access token
     * (`sk-ant-oat…`, what an authenticated Claude subscription / `claude
     * setup-token` hands you) goes on `Authorization: Bearer` with the oauth beta
     * flag — sent as `x-api-key` it is rejected 401 "API key is invalid".
     *
     * That 401 is why this mattered: the provider chain treated it as a normal
     * failure, retried three times and quietly moved on to GPT. Every SFX and
     * background in the library was written by the fallback model, which is what
     * the hardcoded colours, invented param names and non-JSON replies were.
     *
     * @return array<string,string>
     */
    private function cabecalhosAnthropic(string $key): array
    {
        if (str_starts_with($key, 'sk-ant-oat')) {
            return [
                'Authorization' => 'Bearer '.$key,
                'anthropic-version' => '2023-06-01',
                'anthropic-beta' => 'oauth-2025-04-20',
            ];
        }

        return ['x-api-key' => $key, 'anthropic-version' => '2023-06-01'];
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
            // Clips get their own model key — these calls write code, the
            // aggregator's summarise news. Set CLIPS_ANTHROPIC_MODEL empty to
            // defer to the shared aggregation model instead.
            'model' => (string) (config('contentmachine.clips.anthropic_model')
                ?: config('contentmachine.aggregation.anthropic_model', 'claude-opus-4-8')),
            'max_tokens' => (int) config('contentmachine.clips.max_tokens', 16000),
            // Adaptive thinking, explicitly. Omitting `thinking` means the model
            // does NOT think — that is the default on the Opus 4.x family, and it
            // was how every SFX/background component got written: one pass, no
            // reasoning, on a task that has to satisfy a layout contract across
            // three frames. (`budget_tokens` is gone on these models; adaptive
            // replaces it, and the model decides the depth itself.)
            'thinking' => ['type' => 'adaptive'],
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
                    ->withHeaders($this->cabecalhosAnthropic($this->chaveLlm('claude')))
                    ->post('https://api.anthropic.com/v1/messages', $payload);
            } catch (\Throwable $e) {
                $lastError = 'http: '.$e->getMessage();
                $this->claudeBackoff($i, $attempts);

                continue;
            }

            // 401/403 is a CONFIGURATION error, never a transient one. Retrying it
            // burns the attempts and the wait, and the chain then hands the job to
            // GPT — the failure mode that filled the library with GPT-written
            // effects while the studio reported itself as running on Claude. Fail
            // straight through with a message that names the actual problem.
            if (in_array($r->status(), [401, 403], true)) {
                throw new RuntimeException(sprintf(
                    'Claude API rejected the credential (HTTP %d). The stored key starts "%s…" — an OAuth '
                    .'token (sk-ant-oat…) authenticates as a Bearer token, an API key (sk-ant-api…) on '
                    .'x-api-key. Check the Anthropic key in Settings. Response: %s',
                    $r->status(),
                    substr($this->chaveLlm('claude'), 0, 11),
                    substr((string) $r->body(), 0, 160),
                ));
            }

            if (! $r->successful()) {
                $lastError = 'status '.$r->status().': '.substr((string) $r->body(), 0, 200);
                $this->claudeBackoff($i, $attempts);

                continue;
            }

            // A max_tokens cut comes back as HTTP 200 with half a response. Passing
            // that on as success hands the caller truncated JSON, which surfaces as
            // "the model did not return valid JSON" — the wrong error, on the wrong
            // layer. Retrying hits the same ceiling at the same place, so fail
            // straight through to the next provider instead of burning the attempts.
            if ($r->json('stop_reason') === 'max_tokens') {
                throw new RuntimeException(
                    'Claude API truncated the response at max_tokens='.$payload['max_tokens'].
                    ' — raise CLIPS_MAX_TOKENS.'
                );
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
        return $this->runChatCompletions(
            'Tensorix',
            (string) config('services.tensorx.base_url', 'https://api.tensorx.ai/v1'),
            $this->chaveLlm('tensorx'),
            (string) config('services.tensorx.model', 'deepseek/deepseek-r1-0528'),
            $user,
            $system,
            $opts,
        );
    }

    /**
     * One OpenAI-compatible chat call (GPT, Tensorix/DeepSeek), retrying transient
     * failures, returned in the CLI's envelope shape ({result:string}).
     *
     * @param  array{maxTurns?:int,allowedTools?:string,timeout?:int}  $opts
     * @return array{result:string}
     */
    private function runChatCompletions(string $nome, string $baseUrl, string $key, string $model, string $user, ?string $system, array $opts): array
    {
        $attempts = max(1, (int) config('contentmachine.clips.claude_attempts', 3));
        $base = rtrim($baseUrl, '/');

        $payload = [
            'model' => $model,
            'messages' => array_values(array_filter([
                ($system !== null && $system !== '') ? ['role' => 'system', 'content' => $system] : null,
                ['role' => 'user', 'content' => $user],
            ])),
        ];

        $lastError = 'no detail';

        for ($i = 1; $i <= $attempts; $i++) {
            try {
                $r = Http::timeout($opts['timeout'] ?? 300)
                    ->withToken($key)
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

            // Same truncation trap as the Anthropic path, under the OpenAI name.
            if ($r->json('choices.0.finish_reason') === 'length') {
                throw new RuntimeException("{$nome} truncated the response (finish_reason=length) — its output ceiling is too low for this prompt.");
            }

            $texto = (string) $r->json('choices.0.message.content', '');
            if (trim($texto) !== '') {
                return ['result' => $texto];
            }

            $lastError = 'empty response';
            $this->claudeBackoff($i, $attempts);
        }

        throw new RuntimeException("{$nome} API failed after {$attempts} attempt(s) — {$lastError}");
    }
}
