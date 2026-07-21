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
    | Agregador de média (yt-dlp, modo metadados-only)
    |--------------------------------------------------------------------------
    | Recolhe conteúdo recente dos canais configurados (YouTube, TikTok,
    | Instagram, LinkedIn) SEM descarregar vídeo — apenas metadados e legendas.
    |
    | 'ytdlp_cmd' é o comando base: 'yt-dlp' (Docker) ou 'python3 -m yt_dlp'
    | (instalação local via pip --user). 'extractor_args' força, por plataforma,
    | argumentos de extractor — o cliente 'android' do YouTube evita o erro
    | recorrente "The page needs to be reloaded".
    */
    'aggregation' => [
        'ytdlp_cmd' => env('YTDLP_CMD', 'yt-dlp'),
        'limite_por_canal' => (int) env('AGGREGATION_LIMIT', 5),
        'sub_langs' => ['pt', 'en'],
        'timeout' => (int) env('AGGREGATION_TIMEOUT', 120),
        'extractor_args' => [
            'youtube.com' => 'youtube:player_client=android',
            'youtu.be' => 'youtube:player_client=android',
        ],
        'openai_model' => env('AGGREGATION_OPENAI_MODEL', 'gpt-4o-mini'),
        'gemini_model' => env('AGGREGATION_GEMINI_MODEL', 'gemini-1.5-flash'),

        // Fornecedor de LLM para a redação/tópicos:
        //   'auto'       → usa o CLI do Claude se existir, senão OpenAI/Gemini
        //   'claude-cli' → corre o CLI do Claude Code (reutiliza a tua sessão, sem chave de API)
        //   'openai' | 'gemini' → via API (requer chave)
        //   'none'       → desligado (heurística)
        'llm_provider' => env('LLM_PROVIDER', 'auto'),
        'claude_cli_bin' => env('CLAUDE_CLI_BIN', 'claude'),
        'claude_cli_model' => env('CLAUDE_CLI_MODEL', ''),
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
