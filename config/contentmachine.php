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

    /*
    |--------------------------------------------------------------------------
    | Clips Animados (estúdio de animação)
    |--------------------------------------------------------------------------
    | Pipeline de geração de clips: transcrição (Whisper), planeamento de
    | animações (GPT + style md), render (Remotion) e composição (ffmpeg).
    | Driver 'fake' corre sem chaves; 'api' liga os serviços reais.
    */
    'clips' => [
        'driver' => env('CLIPS_DRIVER', 'fake'),
        // Planeador de animações: 'claude' (CLI/subscrição) ou 'openai' (API).
        'planner' => env('CLIPS_PLANNER', 'claude'),
        'claude_binary' => env('CLIPS_CLAUDE_BINARY', 'claude'),
        // Nº de tentativas do CLI Claude (falhas transitórias: sobrecarga da API, etc.).
        'claude_attempts' => (int) env('CLIPS_CLAUDE_ATTEMPTS', 3),
        // Pesquisa profunda (web) do tópico antes do planeamento.
        'research' => (bool) env('CLIPS_RESEARCH', true),
        'width' => (int) env('CLIPS_WIDTH', 1080),
        'height' => (int) env('CLIPS_HEIGHT', 1920),
        'fps' => (int) env('CLIPS_FPS', 30),
        'voice_id' => env('ELEVENLABS_VOICE_ID', 'EXAVITQu4vr4xnSDxMaL'),
        'openai_model' => env('CLIPS_OPENAI_MODEL', 'gpt-4o'),
        // Sugestão de idioma para o Whisper ('' = deteção automática, para o
        // idioma seguir a fala; defina p.ex. 'pt' só se quiser forçar).
        'transcribe_language' => env('CLIPS_TRANSCRIBE_LANGUAGE', ''),
        'remotion_path' => base_path('remotion'),
        'style_md' => base_path('vault/estilo-animacao.md'),
        'disk' => env('CLIPS_DISK', 'local'),
    ],
];
