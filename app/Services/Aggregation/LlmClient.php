<?php

namespace App\Services\Aggregation;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

/**
 * Text generation client via LLM, with a CHAIN of providers: it tries each
 * one in order and uses the first that returns text. This way, if the Claude CLI
 * fails (e.g. not logged in in the server context), it falls back to an API instead of
 * degrading to a heuristic.
 *
 * Providers:
 *   - 'claude-cli' → `claude -p` (free; reuses the session, but only works
 *     where `claude` is authenticated — unreliable from a server).
 *   - 'anthropic'  → Anthropic API (ANTHROPIC_API_KEY key) — reliable Claude.
 *   - 'openai' / 'gemini' → via API (requires a key).
 *
 * config `contentmachine.aggregation.llm_provider`:
 *   'auto' (chain by availability) | a specific name | 'none' (disabled).
 * Never throws — on failure returns null.
 */
class LlmClient
{
    /**
     * Variables that mark an in-progress Claude Code session. Passed to
     * `claude -p` they would make it enter "child-session" mode and fail auth, so
     * they are removed from the subprocess (via `env -u`).
     */
    private const MARCADORES_SESSAO = [
        'CLAUDECODE',
        'CLAUDE_CODE_ENTRYPOINT',
        'CLAUDE_CODE_EXECPATH',
        'CLAUDE_CODE_SESSION_ID',
        'CLAUDE_CODE_CHILD_SESSION',
        'CLAUDE_PID',
        'CLAUDE_EFFORT',
        'AI_AGENT',
    ];

    /** @var array<string,?string> binary resolution cache */
    private static array $binCache = [];

    /** Last provider that produced text (to label the output). */
    private ?string $ultimoFornecedor = null;

    public function disponivel(): bool
    {
        return $this->fornecedores() !== [];
    }

    /** Name of the provider that produced the last text (or the first available). */
    public function fornecedorAtivo(): ?string
    {
        return $this->ultimoFornecedor ?? ($this->fornecedores()[0] ?? null);
    }

    /**
     * Generates text. Tries the providers in order until one returns something.
     *
     * @param  bool  $comFerramentas  Allows Claude (CLI) to use web search/reading.
     * @param  bool  $json  Ask for a JSON-only reply (pinned where the API supports it).
     */
    public function texto(string $prompt, bool $comFerramentas = false, bool $json = false): ?string
    {
        foreach ($this->fornecedores() as $fornecedor) {
            try {
                $texto = match ($fornecedor) {
                    'claude-cli' => $this->claudeCli($prompt, $comFerramentas),
                    'anthropic' => $this->anthropic((string) config('services.anthropic.key'), $prompt),
                    'openai' => $this->openai((string) config('services.openai.key'), $prompt, $json),
                    'gemini' => $this->gemini((string) config('services.gemini.key'), $prompt, $json),
                    'tensorx' => $this->tensorx((string) config('services.tensorx.key'), $prompt),
                    default => null,
                };
            } catch (\Throwable) {
                $texto = null;
            }

            if ($texto !== null && $texto !== '') {
                $this->ultimoFornecedor = $fornecedor;

                return $texto;
            }
        }

        return null;
    }

    /**
     * The house order: CLAUDE → GPT → DEEPSEEK (Tensorix) → Gemini. Every provider
     * with a key stays in the chain, so a configured provider is always used and a
     * failing one falls through to the next instead of dropping to the heuristic.
     *
     * @return array<int,string>
     */
    private const ORDEM = ['anthropic', 'claude-cli', 'openai', 'tensorx', 'gemini'];

    /** @return array<int,string> */
    private function fornecedores(): array
    {
        $escolha = (string) config('contentmachine.aggregation.llm_provider', 'auto');
        if ($escolha === 'none') {
            return [];
        }

        // An explicitly configured provider goes FIRST — the rest stay behind it as
        // fallback, so choosing one never means "nothing else may answer".
        $ordem = self::ORDEM;
        if ($escolha !== '' && $escolha !== 'auto') {
            $ordem = array_merge([$escolha], array_values(array_diff($ordem, [$escolha])));
        }

        // The Claude API and the `claude` CLI are the same provider: with a key, use
        // the API (the CLI needs an interactive session, absent on a server).
        if (filled(config('services.anthropic.key')) && $escolha !== 'claude-cli') {
            $ordem = array_values(array_diff($ordem, ['claude-cli']));
        }

        return array_values(array_filter($ordem, fn (string $f) => match ($f) {
            'claude-cli' => $this->claudeBin() !== null,
            'anthropic' => filled(config('services.anthropic.key')),
            'openai' => filled(config('services.openai.key')),
            'gemini' => filled(config('services.gemini.key')),
            'tensorx' => filled(config('services.tensorx.key')),
            default => false,
        }));
    }

    /** Runs the Claude Code CLI in non-interactive mode (prompt via stdin). */
    private function claudeCli(string $prompt, bool $comFerramentas = false): ?string
    {
        $bin = $this->claudeBin();
        if ($bin === null) {
            return null;
        }

        // Run via `env -u …` to REMOVE the Claude Code session markers
        // before invoking `claude`. Symfony Process re-injects the inherited
        // environment (getDefaultEnv), so omitting them in ->env() is not enough; if the
        // subprocess inherits CLAUDECODE/CLAUDE_CODE_*, `claude -p` enters
        // "child-session" mode and fails with "Not logged in". With `env -u` they are removed
        // by force and `claude` authenticates via the on-disk credentials (~/.claude).
        $args = ['/usr/bin/env'];
        foreach (self::MARCADORES_SESSAO as $marcador) {
            $args[] = '-u';
            $args[] = $marcador;
        }
        array_push($args, $bin, '-p', '--output-format', 'text');
        $modelo = (string) config('contentmachine.aggregation.claude_cli_model', '');
        if ($modelo !== '') {
            $args[] = '--model';
            $args[] = $modelo;
        }

        $timeout = (int) config('contentmachine.aggregation.claude_cli_timeout', 240);

        if ($comFerramentas && (bool) config('contentmachine.aggregation.claude_cli_web', true)) {
            $args[] = '--allowedTools';
            $args[] = 'WebSearch';
            $args[] = 'WebFetch';
            $timeout = max($timeout, 600);
        }

        $r = Process::path(sys_get_temp_dir())
            ->timeout($timeout)
            ->env($this->ambiente())
            ->input($prompt)
            ->run($args);

        return $r->successful() ? (trim($r->output()) ?: null) : null;
    }

    /** Anthropic API (Messages). Returns the text or null. */
    private function anthropic(string $chave, string $prompt): ?string
    {
        if (blank($chave)) {
            return null;
        }

        $r = Http::timeout(120)
            ->withHeaders(['x-api-key' => $chave, 'anthropic-version' => '2023-06-01'])
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => (string) config('contentmachine.aggregation.anthropic_model', 'claude-opus-4-8'),
                'max_tokens' => (int) config('contentmachine.aggregation.anthropic_max_tokens', 8000),
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

        return $r->successful() ? (trim((string) $r->json('content.0.text')) ?: null) : null;
    }

    private function openai(string $chave, string $prompt, bool $json = false): ?string
    {
        if (blank($chave)) {
            return null;
        }

        $r = Http::timeout(120)->withToken($chave)
            ->post('https://api.openai.com/v1/chat/completions', array_filter([
                'model' => (string) config('contentmachine.aggregation.openai_model', 'gpt-4o-mini'),
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'temperature' => 0.4,
                'response_format' => $json ? ['type' => 'json_object'] : null,
            ]));

        return $r->successful() ? (trim((string) $r->json('choices.0.message.content')) ?: null) : null;
    }

    /**
     * Tensorix (tensorx.ai) — the OpenAI-compatible gateway the clip pipeline
     * already uses. Kept in this chain too, so a deploy whose only key is Tensorix
     * still WRITES the news (instead of silently degrading to the heuristic).
     * No web search here: it fills in from the transcripts and cited sources.
     */
    private function tensorx(string $chave, string $prompt): ?string
    {
        if (blank($chave)) {
            return null;
        }

        $base = rtrim((string) config('services.tensorx.base_url', 'https://api.tensorx.ai/v1'), '/');
        $r = Http::timeout(300)->withToken($chave)
            ->post($base.'/chat/completions', [
                'model' => (string) config('services.tensorx.model', 'deepseek/deepseek-r1-0528'),
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);

        return $r->successful() ? (trim((string) $r->json('choices.0.message.content')) ?: null) : null;
    }

    private function gemini(string $chave, string $prompt, bool $json = false): ?string
    {
        if (blank($chave)) {
            return null;
        }

        $modelo = (string) config('contentmachine.aggregation.gemini_model', 'gemini-1.5-flash');
        $r = Http::timeout(120)
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$modelo}:generateContent?key={$chave}", array_filter([
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => $json ? ['responseMimeType' => 'application/json'] : null,
            ]));

        return $r->successful() ? (trim((string) $r->json('candidates.0.content.parts.0.text')) ?: null) : null;
    }

    /**
     * Environment for the `claude -p` subprocess: passes the REAL process
     * environment (via getenv(), which works in CLI and in the web SAPI — unlike
     * $_SERVER, which under `php artisan serve` does not expose environment variables)
     * BUT removes the Claude Code session markers.
     *
     * `claude` authenticates via the on-disk credentials (~/.claude, via HOME).
     * If it inherits CLAUDECODE / CLAUDE_CODE_* from a parent Claude Code session,
     * it enters "child-session" mode and fails with "Not logged in". Removing them lets
     * the subprocess run as a normal top-level invocation — it works both
     * inside and outside a Claude Code session.
     *
     * @return array<string,string>
     */
    private function ambiente(): array
    {
        $env = [];

        $todas = getenv();
        if (is_array($todas)) {
            foreach ($todas as $k => $val) {
                if (is_string($val) && ! $this->ehMarcadorSessao((string) $k)) {
                    $env[$k] = $val;
                }
            }
        }

        // User data (for HOME/USER/LOGNAME fallback).
        $pw = function_exists('posix_getpwuid') && function_exists('posix_getuid')
            ? (posix_getpwuid(posix_getuid()) ?: [])
            : [];

        // Ensures HOME + PATH and, crucially, USER/LOGNAME/SHELL: `claude`
        // needs them to access the credentials (Keychain on macOS). Under the
        // cli-server SAPI (php artisan serve) Symfony Process does not propagate them, which
        // left `claude` "Not logged in" even with HOME set.
        $garantir = [
            'HOME' => getenv('HOME') ?: ($env['HOME'] ?? ($pw['dir'] ?? '')),
            'PATH' => getenv('PATH') ?: ($env['PATH'] ?? '/usr/local/bin:/usr/bin:/bin'),
            'USER' => getenv('USER') ?: ($env['USER'] ?? ($pw['name'] ?? '')),
            'LOGNAME' => getenv('LOGNAME') ?: ($env['LOGNAME'] ?? ($pw['name'] ?? '')),
            'SHELL' => getenv('SHELL') ?: ($env['SHELL'] ?? ($pw['shell'] ?? '/bin/sh')),
        ];
        foreach ($garantir as $chave => $valor) {
            if ((string) $valor !== '') {
                $env[$chave] = (string) $valor;
            }
        }

        return $env;
    }

    /** Variables that mark an in-progress Claude Code session (to remove from the subprocess). */
    private function ehMarcadorSessao(string $chave): bool
    {
        return in_array($chave, self::MARCADORES_SESSAO, true) || str_starts_with($chave, 'CLAUDE_CODE');
    }

    /** Absolute path of the `claude` binary, or null if unavailable (cached). */
    private function claudeBin(): ?string
    {
        $bin = (string) config('contentmachine.aggregation.claude_cli_bin', 'claude');

        if (array_key_exists($bin, self::$binCache)) {
            return self::$binCache[$bin];
        }

        if (str_contains($bin, '/')) {
            return self::$binCache[$bin] = (is_executable($bin) ? $bin : null);
        }

        $r = Process::run(['bash', '-lc', 'command -v '.escapeshellarg($bin)]);
        $caminho = trim($r->output());

        return self::$binCache[$bin] = ($r->successful() && $caminho !== '' ? $caminho : null);
    }
}
