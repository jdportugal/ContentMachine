<?php

namespace App\Services\Aggregation;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

/**
 * Cliente mínimo para geração de texto via LLM.
 *
 * Fornecedores suportados (config `contentmachine.aggregation.llm_provider`):
 *   - 'claude-cli' → corre o CLI do Claude Code (`claude -p`), reutilizando a
 *     sessão/subscrição já autenticada — SEM chave de API nem faturação por token.
 *   - 'openai' / 'gemini' → via API (requer chave em config/services).
 *   - 'auto' → CLI do Claude se existir, senão OpenAI, senão Gemini.
 *   - 'none' → desligado (a app degrada para heurística).
 *
 * Nunca lança — em falha devolve null.
 */
class LlmClient
{
    /** @var array<string,?string> cache de resolução de binários */
    private static array $binCache = [];

    public function disponivel(): bool
    {
        return $this->fornecedor() !== null;
    }

    /** Nome do fornecedor efectivo (para rotular a saída), ou null. */
    public function fornecedorAtivo(): ?string
    {
        return $this->fornecedor();
    }

    /**
     * Gera texto a partir de um prompt. Devolve null se indisponível ou em falha.
     *
     * @param  bool  $comFerramentas  Permite ao Claude (CLI) usar pesquisa/leitura web
     *                                para ir buscar contexto às fontes. Ignorado por OpenAI/Gemini.
     */
    public function texto(string $prompt, bool $comFerramentas = false): ?string
    {
        try {
            return match ($this->fornecedor()) {
                'claude-cli' => $this->claudeCli($prompt, $comFerramentas),
                'openai' => $this->openai((string) config('services.openai.key'), $prompt),
                'gemini' => $this->gemini((string) config('services.gemini.key'), $prompt),
                default => null,
            };
        } catch (\Throwable) {
            return null;
        }
    }

    /** Determina o fornecedor efectivo, respeitando a config e a disponibilidade. */
    private function fornecedor(): ?string
    {
        $escolha = (string) config('contentmachine.aggregation.llm_provider', 'auto');

        return match ($escolha) {
            'none' => null,
            'claude-cli' => $this->claudeBin() !== null ? 'claude-cli' : null,
            'openai' => filled(config('services.openai.key')) ? 'openai' : null,
            'gemini' => filled(config('services.gemini.key')) ? 'gemini' : null,
            default => match (true) { // 'auto'
                $this->claudeBin() !== null => 'claude-cli',
                filled(config('services.openai.key')) => 'openai',
                filled(config('services.gemini.key')) => 'gemini',
                default => null,
            },
        };
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

        // Ferramentas de pesquisa/leitura web (para ir buscar contexto às fontes).
        if ($comFerramentas && (bool) config('contentmachine.aggregation.claude_cli_web', true)) {
            $args[] = '--allowedTools';
            $args[] = 'WebSearch';
            $args[] = 'WebFetch';
            $timeout = max($timeout, 600); // a pesquisa web acrescenta latência
        }

        // Corre num directório neutro para não puxar contexto de projecto.
        $r = Process::path(sys_get_temp_dir())
            ->timeout($timeout)
            ->input($prompt)
            ->run($args);

        return $r->successful() ? (trim($r->output()) ?: null) : null;
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

        // Resolve pelo PATH do shell de login (inclui ~/.local/bin, etc.).
        $r = Process::run(['bash', '-lc', 'command -v '.escapeshellarg($bin)]);
        $caminho = trim($r->output());

        return self::$binCache[$bin] = ($r->successful() && $caminho !== '' ? $caminho : null);
    }

    private function openai(string $chave, string $prompt): ?string
    {
        if (blank($chave)) {
            return null;
        }

        $r = Http::timeout(90)->withToken($chave)
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
        $r = Http::timeout(90)
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$modelo}:generateContent?key={$chave}", [
                'contents' => [['parts' => [['text' => $prompt]]]],
            ]);

        return $r->successful() ? (trim((string) $r->json('candidates.0.content.parts.0.text')) ?: null) : null;
    }
}
