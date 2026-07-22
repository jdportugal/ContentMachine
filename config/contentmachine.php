<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Vault (o "cérebro" / memória — Obsidian)
    |--------------------------------------------------------------------------
    | Pasta de ficheiros Markdown (.md com frontmatter YAML) que serve de
    | base de conhecimento. Montada como volume no Docker.
    */
    'vault' => [
        'path' => env('VAULT_PATH', base_path('vault')),
        'folders' => [
            'monitorizacao' => 'monitorizacao',
            'rascunhos' => 'rascunhos',
            'noticias' => 'noticias',
            'publicacoes' => 'publicacoes',
            'clips' => 'clips',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Drivers
    |--------------------------------------------------------------------------
    | 'fake' usa dados simulados (sem chaves de API). Os drivers reais são
    | stubs prontos a ligar (Apify, TubeLab, Gemini, etc.) via config/services.
    */
    'monitoring' => [
        'driver' => env('MONITORING_DRIVER', 'fake'),
        'plataformas' => ['youtube', 'instagram', 'tiktok', 'linkedin'],
    ],

    // Metadados de apresentação por plataforma (label, cor de acento, glifo).
    'plataformas_meta' => [
        'youtube' => ['label' => 'YouTube',   'cor' => '#d76a5a', 'glifo' => '▶'],
        'instagram' => ['label' => 'Instagram', 'cor' => '#c85a9c', 'glifo' => '◉'],
        'tiktok' => ['label' => 'TikTok',    'cor' => '#2dbab4', 'glifo' => '♪'],
        'linkedin' => ['label' => 'LinkedIn',  'cor' => '#1f7a7a', 'glifo' => '▮'],
    ],

    'news' => [
        'driver' => env('NEWS_DRIVER', 'fake'),
        'fontes' => ['youtube', 'reddit', 'twitter', 'tiktok'],
    ],

    /*
    |--------------------------------------------------------------------------
    | LLM (geração de texto: seleção de clips por IA, tópicos, redação)
    |--------------------------------------------------------------------------
    | Cadeia de fornecedores via App\Services\Aggregation\LlmClient. Por defeito
    | usa o CLI do Claude Code (sem chave de API), reutilizando a tua sessão.
    */
    'aggregation' => [
        'openai_model' => env('AGGREGATION_OPENAI_MODEL', 'gpt-4o-mini'),
        'gemini_model' => env('AGGREGATION_GEMINI_MODEL', 'gemini-1.5-flash'),
        'anthropic_model' => env('AGGREGATION_ANTHROPIC_MODEL', 'claude-opus-4-8'),
        'anthropic_max_tokens' => (int) env('AGGREGATION_ANTHROPIC_MAX_TOKENS', 8000),

        // 'auto' → CLI do Claude se existir, senão OpenAI/Gemini/Anthropic por chave.
        // 'claude-cli' | 'openai' | 'gemini' | 'anthropic' | 'none'.
        'llm_provider' => env('LLM_PROVIDER', 'auto'),
        'claude_cli_bin' => env('CLAUDE_CLI_BIN', 'claude'),
        'claude_cli_model' => env('CLAUDE_CLI_MODEL', ''),
        'claude_cli_timeout' => (int) env('CLAUDE_CLI_TIMEOUT', 240),
        'claude_cli_web' => (bool) env('CLAUDE_CLI_WEB', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Scoring (padrão head-of-content)
    |--------------------------------------------------------------------------
    | Pesos por plataforma para o cálculo do índice de desempenho (0–100).
    */
    'scoring' => [
        'pesos' => [
            'youtube' => ['likes' => 1.0, 'comentarios' => 3.0, 'partilhas' => 2.0, 'guardados' => 1.5],
            'instagram' => ['likes' => 1.0, 'comentarios' => 2.5, 'partilhas' => 3.0, 'guardados' => 4.0],
            'tiktok' => ['likes' => 1.0, 'comentarios' => 2.0, 'partilhas' => 4.0, 'guardados' => 3.0],
            'linkedin' => ['likes' => 1.0, 'comentarios' => 3.5, 'partilhas' => 3.0, 'guardados' => 1.0],
        ],
    ],
];
