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
        // Nº de publicações recentes a recolher por rede.
        'limite' => (int) env('MONITORING_LIMITE', 12),
        // Redes que NÃO expõem métricas públicas (ex.: Instagram esconde gostos/
        // visualizações a quem não tem sessão). Mostramos só miniaturas + datas.
        'sem_metricas' => ['instagram'],
        // Actores Apify para as redes não-YouTube (YouTube usa yt-dlp, grátis).
        // Requer APIFY_TOKEN. Sobreponha os actores por env se usar outros.
        'apify' => [
            'instagram' => env('APIFY_ACTOR_INSTAGRAM', 'apify~instagram-scraper'),
            'tiktok' => env('APIFY_ACTOR_TIKTOK', 'clockworks~tiktok-scraper'),
            'linkedin' => env('APIFY_ACTOR_LINKEDIN', ''), // sem actor fiável por defeito
        ],
    ],

    // Metadados de apresentação por plataforma (label, cor de acento, glifo).
    'plataformas_meta' => [
        'youtube' => ['label' => 'YouTube',   'cor' => '#FF5C7A', 'glifo' => '▶'],
        'instagram' => ['label' => 'Instagram', 'cor' => '#C77DFF', 'glifo' => '◉'],
        'tiktok' => ['label' => 'TikTok',    'cor' => '#4DE0E0', 'glifo' => '♪'],
        'linkedin' => ['label' => 'LinkedIn',  'cor' => '#5A7BFF', 'glifo' => '▮'],
    ],

    'news' => [
        'driver' => env('NEWS_DRIVER', 'fake'),
        'fontes' => ['youtube', 'reddit', 'twitter', 'tiktok'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Agregador de média (yt-dlp, metadados-only) + LLM (geração de texto)
    |--------------------------------------------------------------------------
    | Recolhe conteúdo recente dos canais configurados (YouTube, TikTok,
    | Instagram, LinkedIn) SEM descarregar vídeo — apenas metadados e legendas —
    | e gere a cadeia de fornecedores de LLM (App\Services\Aggregation\LlmClient),
    | por defeito o CLI do Claude Code, sem chave de API.
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
        'timeout' => (int) env('AGGREGATION_TIMEOUT', 45), // fail-fast por chamada yt-dlp (canais pendurados falham depressa)
        'extractor_args' => [
            'youtube.com' => 'youtube:player_client=android',
            'youtu.be' => 'youtube:player_client=android',
        ],
        'openai_model' => env('AGGREGATION_OPENAI_MODEL', 'gpt-4o-mini'),
        'gemini_model' => env('AGGREGATION_GEMINI_MODEL', 'gemini-1.5-flash'),
        'anthropic_model' => env('AGGREGATION_ANTHROPIC_MODEL', 'claude-opus-4-8'),
        'anthropic_max_tokens' => (int) env('AGGREGATION_ANTHROPIC_MAX_TOKENS', 8000),

        // Fornecedor de LLM para a redação/tópicos:
        //   'auto'       → usa o CLI do Claude se existir, senão OpenAI/Gemini/Anthropic
        //   'claude-cli' → corre o CLI do Claude Code (reutiliza a tua sessão, sem chave de API)
        //   'openai' | 'gemini' | 'anthropic' → via API (requer chave)
        //   'none'       → desligado (heurística)
        'llm_provider' => env('LLM_PROVIDER', 'auto'),
        'claude_cli_bin' => env('CLAUDE_CLI_BIN', 'claude'),
        'claude_cli_model' => env('CLAUDE_CLI_MODEL', ''),
        'claude_cli_timeout' => (int) env('CLAUDE_CLI_TIMEOUT', 240),
        // Permite ao Claude usar pesquisa/leitura web para ir buscar contexto às
        // fontes ao redigir o guião. Torna a geração mais lenta. CLAUDE_CLI_WEB=false desliga.
        'claude_cli_web' => (bool) env('CLAUDE_CLI_WEB', true),

        // Gerar um resumo de conteúdo por vídeo (via LLM) na agregação.
        // Torna a recolha mais lenta (uma chamada por vídeo); desligue com RESUMOS_VIDEO=false.
        'gerar_resumos' => (bool) env('RESUMOS_VIDEO', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Publicações (oficina de peças para redes sociais)
    |--------------------------------------------------------------------------
    | Registo declarativo dos TIPOS de peça. Cada tipo é um "gabarito" que a
    | oficina genérica (App\Livewire\Publicacoes\Oficina) sabe compor, planear
    | com IA e desenhar em imagem — acrescentar um formato é só acrescentar uma
    | entrada aqui. Inspirado nos PostTemplate do AdsMaker.
    |
    |   formato: 'single' (um corpo) | 'carousel' (vários cartões)
    |   proporcao: relação da imagem (1:1, 4:5, 9:16)
    |   cartoes: { min, max } (para carrosséis)
    |   gabarito: identificador do desenho SVG (ver SvgSlideRenderer)
    |   plano_prompt: orientação dada à IA ao redigir a peça
    |
    | render_driver: 'svg' (offline, determinístico) | 'kie' (kie.ai, requer chave)
    */
    'publicacoes' => [
        'render_driver' => env('PUBLICACOES_RENDER', 'svg'),

        // Resoluções (proporção) disponíveis na oficina. A predefinição de cada
        // tipo vem do seu campo 'proporcao'; o utilizador pode trocar antes de gerar.
        'proporcoes' => [
            '1:1' => 'Quadrado · 1:1',
            '4:5' => 'Retrato · 4:5',
            '9:16' => 'Vertical / story · 9:16',
            '16:9' => 'Horizontal · 16:9',
        ],

        'tipos' => [
            'post' => [
                'label' => 'Posts de página única',
                'glifo' => '❦',
                'descricao' => 'Peças quadradas — «Sabia que…», anúncios, avisos.',
                'formato' => 'single',
                'proporcao' => '1:1',
                'cartoes' => ['min' => 1, 'max' => 1],
                'gabarito' => 'quadrado',
                'plataforma_padrao' => 'instagram',
                'plano_prompt' => 'Uma peça única e autossuficiente. Título curto e uma legenda de 2 a 4 frases, tom sóbrio e culto, sem emojis.',
            ],
            'citacao' => [
                'label' => 'Citações',
                'glifo' => '❝',
                'descricao' => 'Uma frase em destaque com a sua atribuição.',
                'formato' => 'single',
                'proporcao' => '1:1',
                'cartoes' => ['min' => 1, 'max' => 1],
                'gabarito' => 'citacao',
                'plataforma_padrao' => 'instagram',
                'plano_prompt' => 'Uma citação memorável. O título é a frase citada (curta, ≤ 140 caracteres); a legenda indica o autor e uma linha de contexto.',
            ],
            'dica' => [
                'label' => 'Dicas rápidas',
                'glifo' => '✦',
                'descricao' => 'Uma dica prática — «Sabia que…», um truque, um princípio.',
                'formato' => 'single',
                'proporcao' => '4:5',
                'cartoes' => ['min' => 1, 'max' => 1],
                'gabarito' => 'dica',
                'plataforma_padrao' => 'instagram',
                'plano_prompt' => 'Uma dica acionável. Título com o benefício; legenda com o passo concreto em 2 a 3 frases.',
            ],
            'carrossel' => [
                'label' => 'Carrosséis',
                'glifo' => '☰',
                'descricao' => 'Sequência de vários cartões — capa, conteúdo, despedida.',
                'formato' => 'carousel',
                'proporcao' => '4:5',
                'cartoes' => ['min' => 2, 'max' => 10],
                'gabarito' => 'capa-conteudo',
                'plataforma_padrao' => 'instagram',
                'plano_prompt' => 'Um carrossel didático. O primeiro cartão é a capa (promessa clara); os seguintes desenvolvem uma ideia cada; o último remata com síntese ou apelo. Cada cartão: título curto + 1 a 2 frases.',
            ],
            'lista' => [
                'label' => 'Listas numeradas',
                'glifo' => '≡',
                'descricao' => 'N termos, passos ou princípios, um por cartão.',
                'formato' => 'carousel',
                'proporcao' => '4:5',
                'cartoes' => ['min' => 3, 'max' => 8],
                'gabarito' => 'lista',
                'plataforma_padrao' => 'linkedin',
                'plano_prompt' => 'Uma lista numerada. Capa com o tema («5 termos para começar»); cada cartão seguinte é um item numerado com título + uma frase de explicação.',
            ],
            'resumo-semana' => [
                'label' => 'Resumo da semana',
                'glifo' => '☙',
                'descricao' => 'As notícias de IA da semana, em cartões — «Esta semana em IA».',
                'formato' => 'carousel',
                'proporcao' => '4:5',
                'cartoes' => ['min' => 3, 'max' => 6],
                'gabarito' => 'capa-conteudo',
                'plataforma_padrao' => 'linkedin',
                'plano_prompt' => 'Um resumo noticioso semanal, tom «Esta semana em IA»: capa com a data/semana; cada cartão traz uma novidade com título factual + uma frase de contexto; último cartão remata com um olhar para a frente.',
            ],
        ],
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

    /*
    |--------------------------------------------------------------------------
    | Sistema de Design (guia de marca para o CONTEÚDO gerado)
    |--------------------------------------------------------------------------
    | Ficheiro Markdown, editável na app e legível no Obsidian, que descreve a
    | identidade visual/verbal a aplicar ao conteúdo GERADO (clips animados e
    | publicações). É injetado nos prompts LLM desses geradores. Distinto do
    | design da própria interface da app (IATECA-design-system.md).
    */
    'design_system' => [
        'path' => env('DESIGN_SYSTEM_PATH', base_path('vault/design-system.md')),
    ],
];
