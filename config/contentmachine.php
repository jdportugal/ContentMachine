<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Vault (the "brain" / memory — Obsidian)
    |--------------------------------------------------------------------------
    | Folder of Markdown files (.md with YAML frontmatter) that serves as the
    | knowledge base. Mounted as a volume in Docker.
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
    | Projects (workspaces)
    |--------------------------------------------------------------------------
    | Each project is its own vault directory with its own settings, design
    | system and language. The active project (per session) repoints the vault.
    | No database — the registry is a JSON file. `default_vault` is the legacy
    | single vault, adopted as the first project; it is NEVER mutated (unlike
    | `vault.path`, which the SetActiveProject middleware overwrites per request).
    */
    'projects' => [
        'root' => env('PROJECTS_PATH', base_path('vaults')),      // where NEW project vaults live
        'registry' => storage_path('app/projects.json'),          // the project list (slug/name/path/language)
        'default_vault' => env('VAULT_PATH', base_path('vault')), // stable path of the seeded default project
        'default_name' => 'IATECA',
    ],

    /*
    |--------------------------------------------------------------------------
    | Settings — shared vs per-project
    |--------------------------------------------------------------------------
    | All operational settings are per-project (in each project's vault) EXCEPT
    | the API keys, which are shared across every project and stored here (outside
    | any vault). Everything else — channels, sources, profiles, model config —
    | stays per-project.
    */
    'settings' => [
        'keys_path' => storage_path('app/settings-keys.json'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Drivers
    |--------------------------------------------------------------------------
    | 'fake' uses simulated data (no API keys). The real drivers are stubs
    | ready to wire up (Apify, TubeLab, Gemini, etc.) via config/services.
    */
    'monitoring' => [
        'driver' => env('MONITORING_DRIVER', 'fake'),
        'plataformas' => ['youtube', 'instagram', 'tiktok', 'linkedin'],
        // Number of recent posts to collect per network.
        'limite' => (int) env('MONITORING_LIMITE', 12),
        // Networks that do NOT expose public metrics (e.g. Instagram hides likes/
        // views from logged-out visitors). We show only thumbnails + dates.
        'sem_metricas' => ['instagram'],
        // Apify actors for the non-YouTube networks (YouTube uses yt-dlp, free).
        // Requires APIFY_TOKEN. Override the actors per env if you use others.
        'apify' => [
            'instagram' => env('APIFY_ACTOR_INSTAGRAM', 'apify~instagram-scraper'),
            'tiktok' => env('APIFY_ACTOR_TIKTOK', 'clockworks~tiktok-scraper'),
            'linkedin' => env('APIFY_ACTOR_LINKEDIN', ''), // no reliable actor by default
        ],
    ],

    // Presentation metadata per platform (label, accent color, glyph).
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
    | Media aggregator (yt-dlp, metadata-only) + LLM (text generation)
    |--------------------------------------------------------------------------
    | Collects recent content from the configured channels (YouTube, TikTok,
    | Instagram, LinkedIn) WITHOUT downloading video — only metadata and captions —
    | and drives the chain of LLM providers (App\Services\Aggregation\LlmClient),
    | by default the Claude Code CLI, with no API key.
    |
    | 'ytdlp_cmd' is the base command: 'yt-dlp' (Docker) or 'python3 -m yt_dlp'
    | (local install via pip --user). 'extractor_args' forces, per platform,
    | extractor arguments — the YouTube 'android' client avoids the recurring
    | "The page needs to be reloaded" error.
    */
    'aggregation' => [
        'ytdlp_cmd' => env('YTDLP_CMD', 'yt-dlp'),
        'limite_por_canal' => (int) env('AGGREGATION_LIMIT', 5),
        'sub_langs' => ['pt', 'en'],
        'timeout' => (int) env('AGGREGATION_TIMEOUT', 45), // fail-fast per yt-dlp call (hung channels fail fast)
        'extractor_args' => [
            'youtube.com' => 'youtube:player_client=android',
            'youtu.be' => 'youtube:player_client=android',
        ],
        'openai_model' => env('AGGREGATION_OPENAI_MODEL', 'gpt-4o-mini'),
        'gemini_model' => env('AGGREGATION_GEMINI_MODEL', 'gemini-1.5-flash'),
        'anthropic_model' => env('AGGREGATION_ANTHROPIC_MODEL', 'claude-opus-4-8'),
        'anthropic_max_tokens' => (int) env('AGGREGATION_ANTHROPIC_MAX_TOKENS', 8000),

        // LLM provider for the copywriting/topics:
        //   'auto'       → uses the Claude CLI if present, otherwise OpenAI/Gemini/Anthropic
        //   'claude-cli' → runs the Claude Code CLI (reuses your session, no API key)
        //   'openai' | 'gemini' | 'anthropic' → via API (requires a key)
        //   'none'       → disabled (heuristic)
        'llm_provider' => env('LLM_PROVIDER', 'auto'),
        'claude_cli_bin' => env('CLAUDE_CLI_BIN', 'claude'),
        'claude_cli_model' => env('CLAUDE_CLI_MODEL', ''),
        'claude_cli_timeout' => (int) env('CLAUDE_CLI_TIMEOUT', 240),
        // Lets Claude use web search/reading to pull context from the sources
        // while writing the script. Makes generation slower. CLAUDE_CLI_WEB=false disables it.
        'claude_cli_web' => (bool) env('CLAUDE_CLI_WEB', true),

        // Generate a content summary per video (via LLM) during aggregation.
        // Makes collection slower (one call per video); disable with RESUMOS_VIDEO=false.
        'gerar_resumos' => (bool) env('RESUMOS_VIDEO', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Posts (workshop for social-media pieces)
    |--------------------------------------------------------------------------
    | Declarative registry of the piece TYPES. Each type is a "template" that the
    | generic workshop (App\Livewire\Publicacoes\Oficina) knows how to compose, plan
    | with AI and render to an image — adding a format is just adding an entry
    | here. Inspired by AdsMaker's PostTemplate.
    |
    |   formato: 'single' (one body) | 'carousel' (several cards)
    |   proporcao: image ratio (1:1, 4:5, 9:16)
    |   cartoes: { min, max } (for carousels)
    |   gabarito: identifier of the SVG design (see SvgSlideRenderer)
    |   plano_prompt: guidance given to the AI when writing the piece
    |
    | render_driver: 'svg' (offline, deterministic) | 'kie' (kie.ai, requires a key)
    */
    'publicacoes' => [
        'render_driver' => env('PUBLICACOES_RENDER', 'svg'),

        // Resolutions (ratio) available in the workshop. Each type's default comes
        // from its 'proporcao' field; the user can switch before generating.
        'proporcoes' => [
            '1:1' => 'Square · 1:1',
            '4:5' => 'Portrait · 4:5',
            '9:16' => 'Vertical / story · 9:16',
            '16:9' => 'Landscape · 16:9',
        ],

        'tipos' => [
            'post' => [
                'label' => 'Single-page posts',
                'glifo' => '❦',
                'descricao' => 'Square pieces — "Did you know…", announcements, notices.',
                'formato' => 'single',
                'proporcao' => '1:1',
                'cartoes' => ['min' => 1, 'max' => 1],
                'gabarito' => 'quadrado',
                'plataforma_padrao' => 'instagram',
                'plano_prompt' => 'A single, self-contained piece. Short title and a caption of 2 to 4 sentences, sober and cultured tone, no emojis.',
            ],
            'citacao' => [
                'label' => 'Quotes',
                'glifo' => '❝',
                'descricao' => 'A highlighted sentence with its attribution.',
                'formato' => 'single',
                'proporcao' => '1:1',
                'cartoes' => ['min' => 1, 'max' => 1],
                'gabarito' => 'citacao',
                'plataforma_padrao' => 'instagram',
                'plano_prompt' => 'A memorable quote. The title is the quoted sentence (short, ≤ 140 characters); the caption gives the author and a line of context.',
            ],
            'dica' => [
                'label' => 'Quick tips',
                'glifo' => '✦',
                'descricao' => 'A practical tip — "Did you know…", a trick, a principle.',
                'formato' => 'single',
                'proporcao' => '4:5',
                'cartoes' => ['min' => 1, 'max' => 1],
                'gabarito' => 'dica',
                'plataforma_padrao' => 'instagram',
                'plano_prompt' => 'An actionable tip. Title with the benefit; caption with the concrete step in 2 to 3 sentences.',
            ],
            'carrossel' => [
                'label' => 'Carousels',
                'glifo' => '☰',
                'descricao' => 'Sequence of several cards — cover, content, sign-off.',
                'formato' => 'carousel',
                'proporcao' => '4:5',
                'cartoes' => ['min' => 2, 'max' => 10],
                'gabarito' => 'capa-conteudo',
                'plataforma_padrao' => 'instagram',
                'plano_prompt' => 'A didactic carousel. The first card is the cover (clear promise); the following ones each develop one idea; the last wraps up with a synthesis or call to action. Each card: short title + 1 to 2 sentences.',
            ],
            'lista' => [
                'label' => 'Numbered lists',
                'glifo' => '≡',
                'descricao' => 'N terms, steps or principles, one per card.',
                'formato' => 'carousel',
                'proporcao' => '4:5',
                'cartoes' => ['min' => 3, 'max' => 8],
                'gabarito' => 'lista',
                'plataforma_padrao' => 'linkedin',
                'plano_prompt' => 'A numbered list. Cover with the theme ("5 terms to get started"); each following card is a numbered item with a title + one sentence of explanation.',
            ],
            'resumo-semana' => [
                'label' => 'Week in review',
                'glifo' => '☙',
                'descricao' => 'The week\'s AI news, in cards — "This week in AI".',
                'formato' => 'carousel',
                'proporcao' => '4:5',
                'cartoes' => ['min' => 3, 'max' => 6],
                'gabarito' => 'capa-conteudo',
                'plataforma_padrao' => 'linkedin',
                'plano_prompt' => 'A weekly news roundup, "This week in AI" tone: cover with the date/week; each card brings a piece of news with a factual title + one sentence of context; the last card wraps up with a look ahead.',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Scoring (head-of-content standard)
    |--------------------------------------------------------------------------
    | Per-platform weights for computing the performance index (0–100).
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
    | Animated Clips (animation studio)
    |--------------------------------------------------------------------------
    | Clip generation pipeline: transcription (Whisper), animation planning
    | (GPT + style md), render (Remotion) and composition (ffmpeg).
    | The 'fake' driver runs without keys; 'api' wires up the real services.
    */
    'clips' => [
        'driver' => env('CLIPS_DRIVER', 'fake'),
        // Animation planner: 'claude' (CLI/subscription) or 'openai' (API).
        'planner' => env('CLIPS_PLANNER', 'claude'),
        'claude_binary' => env('CLIPS_CLAUDE_BINARY', 'claude'),
        // Number of Claude CLI attempts (transient failures: API overload, etc.).
        'claude_attempts' => (int) env('CLIPS_CLAUDE_ATTEMPTS', 3),
        // Deep (web) research of the topic before planning.
        'research' => (bool) env('CLIPS_RESEARCH', true),
        'width' => (int) env('CLIPS_WIDTH', 1080),
        'height' => (int) env('CLIPS_HEIGHT', 1920),
        'fps' => (int) env('CLIPS_FPS', 30),
        'voice_id' => env('ELEVENLABS_VOICE_ID', 'EXAVITQu4vr4xnSDxMaL'),
        'openai_model' => env('CLIPS_OPENAI_MODEL', 'gpt-4o'),
        // Language hint for Whisper ('' = automatic detection, so the language
        // follows the speech; set e.g. 'pt' only if you want to force it).
        'transcribe_language' => env('CLIPS_TRANSCRIBE_LANGUAGE', ''),
        'remotion_path' => base_path('remotion'),
        'style_md' => base_path('vault/estilo-animacao.md'),
        'disk' => env('CLIPS_DISK', 'local'),
        // Auto-generate on-brand images (Nano Banana / kie.ai) for scenes the
        // planner marks with `generate`. Needs the kie key; off = skipped cleanly.
        'generate_images' => (bool) env('CLIPS_GENERATE_IMAGES', true),
        'image_max' => (int) env('CLIPS_IMAGE_MAX', 6),        // per clip, to bound cost/time
        'image_aspect' => env('CLIPS_IMAGE_ASPECT', '9:16'),   // portrait clips
        'image_style' => env('CLIPS_IMAGE_STYLE', 'cinematic, photographic, high detail, dramatic lighting, vertical composition, no text or watermarks'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Design System (brand guide for the GENERATED CONTENT)
    |--------------------------------------------------------------------------
    | Markdown file, editable in the app and readable in Obsidian, that describes
    | the visual/verbal identity to apply to the GENERATED content (animated clips
    | and posts). It is injected into those generators' LLM prompts. Distinct from
    | the design of the app's own interface (IATECA-design-system.md).
    */
    'design_system' => [
        'path' => env('DESIGN_SYSTEM_PATH', base_path('vault/design-system.md')),
    ],
];
