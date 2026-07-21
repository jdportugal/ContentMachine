<?php

namespace App\Services\Aggregation;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

/**
 * Cliente de geração de texto via LLM, com CADEIA de fornecedores: tenta cada
 * um por ordem e usa o primeiro que devolver texto. Assim, se o CLI do Claude
 * falhar (ex.: sem login no contexto do servidor), cai para uma API em vez de
 * degradar para heurística.
 *
 * Fornecedores:
 *   - 'claude-cli' → `claude -p` (grátis; reutiliza a sessão, mas só funciona
 *     onde o `claude` esteja autenticado — pouco fiável a partir de um servidor).
 *   - 'anthropic'  → API da Anthropic (chave ANTHROPIC_API_KEY) — Claude fiável.
 *   - 'openai' / 'gemini' → via API (requer chave).
 *
 * config `contentmachine.aggregation.llm_provider`:
 *   'auto' (cadeia por disponibilidade) | um nome específico | 'none' (desligado).
 * Nunca lança — em falha devolve null.
 */
class LlmClient
{
    /** @var array<string,?string> cache de resolução de binários */
    private static array $binCache = [];

    /** Último fornecedor que produziu texto (para rotular a saída). */
    private ?string $ultimoFornecedor = null;

    public function disponivel(): bool
    {
        return $this->fornecedores() !== [];
    }

    /** Nome do fornecedor que produziu o último texto (ou o primeiro disponível). */
    public function fornecedorAtivo(): ?string
    {
        return $this->ultimoFornecedor ?? ($this->fornecedores()[0] ?? null);
    }

    /**
     * Gera texto. Tenta os fornecedores por ordem até um devolver algo.
     *
     * @param  bool  $comFerramentas  Permite ao Claude (CLI) usar pesquisa/leitura web.
     */
    public function texto(string $prompt, bool $comFerramentas = false): ?string
    {
        foreach ($this->fornecedores() as $fornecedor) {
            try {
                $texto = match ($fornecedor) {
                    'claude-cli' => $this->claudeCli($prompt, $comFerramentas),
                    'anthropic' => $this->anthropic((string) config('services.anthropic.key'), $prompt),
                    'openai' => $this->openai((string) config('services.openai.key'), $prompt),
                    'gemini' => $this->gemini((string) config('services.gemini.key'), $prompt),
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
     * Cadeia ordenada de fornecedores a tentar, filtrada por disponibilidade.
     *
     * @return array<int,string>
     */
    private function fornecedores(): array
    {
        $escolha = (string) config('contentmachine.aggregation.llm_provider', 'auto');

        $ordem = match ($escolha) {
            'none' => [],
            'auto' => ['claude-cli', 'anthropic', 'openai', 'gemini'],
            default => [$escolha],
        };

        return array_values(array_filter($ordem, fn (string $f) => match ($f) {
            'claude-cli' => $this->claudeBin() !== null,
            'anthropic' => filled(config('services.anthropic.key')),
            'openai' => filled(config('services.openai.key')),
            'gemini' => filled(config('services.gemini.key')),
            default => false,
        }));
    }

    /** Corre o CLI do Claude Code em modo não-interativo (prompt via stdin). */
    private function claudeCli(string $prompt, bool $comFerramentas = false): ?string
    {
        $bin = $this->claudeBin();
        if ($bin === null) {
            return null;
        }

        $args = [$bin, '-p', '--output-format', 'text'];
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

    /** API da Anthropic (Messages). Devolve o texto ou null. */
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

    private function openai(string $chave, string $prompt): ?string
    {
        if (blank($chave)) {
            return null;
        }

        $r = Http::timeout(120)->withToken($chave)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => (string) config('contentmachine.aggregation.openai_model', 'gpt-4o-mini'),
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'temperature' => 0.4,
            ]);

        return $r->successful() ? (trim((string) $r->json('choices.0.message.content')) ?: null) : null;
    }

    private function gemini(string $chave, string $prompt): ?string
    {
        if (blank($chave)) {
            return null;
        }

        $modelo = (string) config('contentmachine.aggregation.gemini_model', 'gemini-1.5-flash');
        $r = Http::timeout(120)
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$modelo}:generateContent?key={$chave}", [
                'contents' => [['parts' => [['text' => $prompt]]]],
            ]);

        return $r->successful() ? (trim((string) $r->json('candidates.0.content.parts.0.text')) ?: null) : null;
    }

    /**
     * Ambiente para o subprocesso do Claude: parte do ambiente actual (para não
     * perder as variáveis da sessão que o autenticam) e garante HOME/PATH.
     *
     * @return array<string,string>
     */
    private function ambiente(): array
    {
        $env = [];
        foreach ($_SERVER as $k => $val) {
            if (is_string($val) && (str_starts_with((string) $k, 'CLAUDE') || in_array($k, ['HOME', 'PATH', 'USER', 'SHELL', 'ANTHROPIC_API_KEY'], true))) {
                $env[$k] = $val;
            }
        }

        $home = getenv('HOME') ?: ($env['HOME'] ?? '');
        if ($home === '' && function_exists('posix_getpwuid')) {
            $home = posix_getpwuid(posix_getuid())['dir'] ?? '';
        }
        if ($home !== '') {
            $env['HOME'] = $home;
        }
        if (empty($env['PATH']) && ($p = getenv('PATH')) !== false) {
            $env['PATH'] = $p;
        }

        return $env;
    }

    /** Caminho absoluto do binário `claude`, ou null se indisponível (com cache). */
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
