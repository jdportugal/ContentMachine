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
    /**
     * Variáveis que marcam uma sessão do Claude Code em curso. Passadas a
     * `claude -p` fá-lo-iam entrar em modo "sessão-filha" e falhar auth, por isso
     * são removidas do subprocesso (via `env -u`).
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

        // Corre via `env -u …` para REMOVER os marcadores de sessão do Claude
        // Code antes de invocar o `claude`. O Symfony Process reinjeta o ambiente
        // herdado (getDefaultEnv), pelo que omití-los no ->env() não basta; se o
        // subprocesso herdar CLAUDECODE/CLAUDE_CODE_*, o `claude -p` entra em modo
        // "sessão-filha" e falha com "Not logged in". Com `env -u` são removidos
        // à força e o `claude` autentica-se pelas credenciais em disco (~/.claude).
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
     * Ambiente para o subprocesso do `claude -p`: passa o ambiente REAL do
     * processo (via getenv(), que funciona em CLI e na SAPI web — ao contrário
     * de $_SERVER, que sob `php artisan serve` não expõe variáveis de ambiente)
     * MAS remove os marcadores de sessão do Claude Code E os segredos alheios.
     *
     * O `claude` autentica-se pelas credenciais em disco (~/.claude, via HOME).
     * Se herdar CLAUDECODE / CLAUDE_CODE_* de uma sessão-pai do Claude Code,
     * entra em modo "sessão-filha" e falha com "Not logged in". Removê-los deixa
     * o subprocesso correr como uma invocação de topo normal — funciona tanto
     * dentro como fora de uma sessão do Claude Code.
     *
     * IMPORTANTE (segurança): esta redação corre com `--allowedTools WebSearch
     * WebFetch` sobre material NÃO fiável (transcrições de vídeos de terceiros).
     * Uma injeção de prompt escondida nesse material poderia levar o agente a
     * ler variáveis de ambiente e exfiltrá-las via WebFetch. Por isso removemos
     * do subprocesso TODAS as chaves de outros fornecedores (OpenAI, Gemini,
     * kie.ai, ElevenLabs, Apify, YouTube, Reddit…), a APP_KEY e as credenciais
     * de base de dados — nada disso é preciso para correr o `claude`. Só a
     * própria autenticação da Anthropic (ANTHROPIC_*) é preservada.
     *
     * @return array<string,string>
     */
    private function ambiente(): array
    {
        $env = [];

        $todas = getenv();
        if (is_array($todas)) {
            foreach ($todas as $k => $val) {
                $k = (string) $k;
                if (is_string($val) && ! $this->ehMarcadorSessao($k) && ! $this->ehSegredoAlheio($k)) {
                    $env[$k] = $val;
                }
            }
        }

        // Dados do utilizador (para fallback de HOME/USER/LOGNAME).
        $pw = function_exists('posix_getpwuid') && function_exists('posix_getuid')
            ? (posix_getpwuid(posix_getuid()) ?: [])
            : [];

        // Garante HOME + PATH e, crucialmente, USER/LOGNAME/SHELL: o `claude`
        // precisa deles para aceder às credenciais (Keychain no macOS). Sob a SAPI
        // cli-server (php artisan serve) o Symfony Process não os propaga, o que
        // deixava o `claude` "Not logged in" mesmo com HOME definido.
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

    /** Variáveis que marcam uma sessão Claude Code em curso (a remover do subprocesso). */
    private function ehMarcadorSessao(string $chave): bool
    {
        return in_array($chave, self::MARCADORES_SESSAO, true) || str_starts_with($chave, 'CLAUDE_CODE');
    }

    /**
     * Segredo que NÃO pertence ao `claude` e não deve entrar no subprocesso.
     *
     * O `claude` só precisa de HOME/PATH/USER/… (reinjetados em ambiente()) e,
     * quando não há sessão em disco, da sua própria chave ANTHROPIC_*. Tudo o
     * resto que pareça uma credencial (chaves de outros fornecedores, tokens,
     * segredos, palavras-passe, APP_KEY) é retido para não ficar ao alcance de
     * um agente com ferramentas web a processar conteúdo não fiável.
     */
    private function ehSegredoAlheio(string $chave): bool
    {
        // A autenticação da própria Anthropic é legítima neste subprocesso.
        if (str_starts_with($chave, 'ANTHROPIC_')) {
            return false;
        }

        return (bool) preg_match(
            '/(API_KEY|_TOKEN$|_SECRET|SECRET_|PASSWORD|_KEY$|CLIENT_ID)/i',
            $chave
        );
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
