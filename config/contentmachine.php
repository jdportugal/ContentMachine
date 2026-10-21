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
        'default_name' => 'Imocena',
        'default_language' => 'pt',                                // narration, prompts and UI copy of the one project
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
        // Networks that do NOT expose public metrics — we show only thumbnails +
        // dates for these. Empty by default: the Apify actors DO return likes/
        // views for Instagram (the old yt-dlp path was the one that could not).
        'sem_metricas' => array_filter(explode(',', (string) env('MONITORING_SEM_METRICAS', ''))),
        // Apify actors for the non-YouTube networks (YouTube uses yt-dlp, free).
        // Requires APIFY_TOKEN. Override the actors per env if you use others.
        'apify' => [
            'instagram' => env('APIFY_ACTOR_INSTAGRAM', 'apify~instagram-scraper'),
            // Profile details (followers, total posts) — the post rows don't carry them.
            'instagram_profile' => env('APIFY_ACTOR_INSTAGRAM_PROFILE', 'apify~instagram-profile-scraper'),
            'tiktok' => env('APIFY_ACTOR_TIKTOK', 'clockworks~tiktok-scraper'),
            'linkedin' => env('APIFY_ACTOR_LINKEDIN', ''), // no reliable actor by default
            // Only used as a FALLBACK: YouTube is collected with yt-dlp, and this
            // takes over when YouTube blocks it ("Sign in to confirm you're not a bot").
            'youtube' => env('APIFY_ACTOR_YOUTUBE', 'streamers~youtube-scraper'),
        ],
    ],

    // Presentation metadata per platform (label, accent color, glyph).
    'plataformas_meta' => [
        'youtube' => ['label' => 'YouTube',   'cor' => '#A93A48', 'glifo' => '▶'],
        'instagram' => ['label' => 'Instagram', 'cor' => '#7A4A8C', 'glifo' => '◉'],
        'tiktok' => ['label' => 'TikTok',    'cor' => '#2C6E6E', 'glifo' => '♪'],
        'linkedin' => ['label' => 'LinkedIn',  'cor' => '#2F5A85', 'glifo' => '▮'],
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
        // How many days back a normal run may reach. Channels post at very
        // different rates, and without this a quiet channel's months-old video
        // arrives as if it were today's news. 0 = no window (keep the latest N
        // per channel, however old).
        'dias_recentes' => (int) env('AGGREGATION_RECENT_DAYS', 7),
        'sub_langs' => ['pt', 'en'],
        'timeout' => (int) env('AGGREGATION_TIMEOUT', 45), // fail-fast per yt-dlp call (hung channels fail fast)
        // Collect YouTube through the Apify actor instead of local yt-dlp. One
        // API call per channel rather than one subprocess per video, so a run
        // over many channels finishes in minutes and no dead handle stalls it.
        // false (or no actor/token) falls back to yt-dlp.
        'youtube_via_apify' => (bool) env('AGGREGATION_YOUTUBE_VIA_APIFY', true),
        'extractor_args' => [
            'youtube.com' => 'youtube:player_client=android',
            'youtu.be' => 'youtube:player_client=android',
        ],
        'openai_model' => env('AGGREGATION_OPENAI_MODEL', 'gpt-4o-mini'),
        'gemini_model' => env('AGGREGATION_GEMINI_MODEL', 'gemini-1.5-flash'),
        'anthropic_model' => env('AGGREGATION_ANTHROPIC_MODEL', 'claude-opus-5'),
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
    // Unit prices (USD) used by the cost ledger. Adjust to your real plans.
    'custos' => [
        'kie_imagem' => env('CUSTO_KIE_IMAGEM', 0.04),          // per generated image
        'kie_video' => env('CUSTO_KIE_VIDEO', 0.40),            // per generated video (5s)
        'elevenlabs_1k_chars' => env('CUSTO_TTS_1K', 0.30),     // per 1k synthesized chars
        'apify_busca' => env('CUSTO_APIFY_BUSCA', 0.01),        // per image-search run
        // fal.ai Sync Lipsync 2 Pro, billed per MINUTE of the finished video
        // (fal's own endpoint billing: unit "minutes", price 5). Assumed
        // pro-rated by the second — correct this against a real invoice if fal
        // turns out to round up to whole minutes.
        'fal_lipsync_min' => env('CUSTO_FAL_LIPSYNC_MIN', 5.00),
        // Refuse a lip-sync whose video is longer than this. At $5/minute an
        // accidental five-minute upload is $25, so the ceiling is a money guard,
        // not a model limit — v2/pro documents none.
        'fal_lipsync_max_seconds' => (int) env('FAL_LIPSYNC_MAX_SECONDS', 120),
        // USD per 1M tokens [input, output], per LlmClient provider.
        'llm_mtok' => [
            'anthropic' => [3.0, 15.0],
            'openai' => [0.15, 0.60],
            'tensorx' => [0.50, 2.00],
            'gemini' => [0.10, 0.40],
        ],
    ],

    'clips' => [
        'driver' => env('CLIPS_DRIVER', 'fake'),
        // Animation planner: 'claude' (CLI/subscription) or 'openai' (API).
        'planner' => env('CLIPS_PLANNER', 'claude'),
        // Output ceiling for the clip/SFX LLM calls. Separate from aggregation's
        // 8000: these prompts return a whole TSX component wrapped in JSON, which
        // is several times a news summary and silently truncates at that ceiling.
        // Raised from 16000 when adaptive thinking was turned on: thinking tokens
        // count against this ceiling, so the old value left a whole TSX component
        // barely any room and truncated (which surfaces as the explicit
        // "raise CLIPS_MAX_TOKENS" error). llm_timeout below is already 600s, so
        // the longer response has time to arrive.
        'max_tokens' => (int) env('CLIPS_MAX_TOKENS', 32000),
        // The Anthropic model for the clip/SFX calls, SEPARATE from aggregation's.
        // Writing a Remotion component that lays itself out across three frames is
        // a harder task than summarising a news item, and these calls are few —
        // so they get the current top model while the aggregator keeps its own.
        'anthropic_model' => env('CLIPS_ANTHROPIC_MODEL', 'claude-opus-5'),
        // How long ONE call to the model may take. Writing a whole component is a
        // minutes-long response, and a ceiling below that times out and retries
        // from scratch — three times — turning one slow generation into three.
        'llm_timeout' => (int) env('CLIPS_LLM_TIMEOUT', 600),
        // How long ONE Remotion render may take. Not a free number: it has to stay
        // below the worker's --timeout, which stays below the queue's retry_after
        // (see config/queue.php) — a render still going when retry_after passes is
        // handed to a second worker, and two of them then write the same file.
        // Raising this means raising all three, in that order.
        'render_timeout' => (int) env('CLIPS_RENDER_TIMEOUT', 3000),
        // Frames painted in parallel. 0 = one per core. Each one in flight is
        // another headless Chrome tab, so lower it if the host runs out of memory
        // before it runs out of cores.
        'render_concurrency' => (int) env('CLIPS_RENDER_CONCURRENCY', 0),
        'claude_binary' => env('CLIPS_CLAUDE_BINARY', 'claude'),
        // Number of Claude CLI attempts (transient failures: API overload, etc.).
        'claude_attempts' => (int) env('CLIPS_CLAUDE_ATTEMPTS', 3),
        // Primary LLM for planning/metadata/research: 'claude' (default) or
        // 'tensorx' (tensorx.ai, OpenAI-compatible) to fully replace it. Either way,
        // a configured Tensorix key is used as an automatic fallback if Claude fails.
        'llm_primary' => env('CLIPS_LLM_PRIMARY', 'claude'),
        // Deep (web) research of the topic before planning.
        'research' => (bool) env('CLIPS_RESEARCH', true),
        'width' => (int) env('CLIPS_WIDTH', 1080),
        'height' => (int) env('CLIPS_HEIGHT', 1920),
        'fps' => (int) env('CLIPS_FPS', 30),
        'voice_id' => env('ELEVENLABS_VOICE_ID', 'EXAVITQu4vr4xnSDxMaL'),
        'openai_model' => env('CLIPS_OPENAI_MODEL', 'gpt-4o'),
        // gpt-4o's completion ceiling; CLIPS_MAX_TOKENS above it is a 400.
        'openai_max_tokens' => (int) env('CLIPS_OPENAI_MAX_TOKENS', 16384),
        // Language hint for Whisper ('' = automatic detection, so the language
        // follows the speech; set e.g. 'pt' only if you want to force it).
        'transcribe_language' => env('CLIPS_TRANSCRIBE_LANGUAGE', ''),
        'remotion_path' => base_path('remotion'),
        // Scroll-captures of websites, cached by url+size+length. Outside the
        // vault: they are re-fetchable, and they are large.
        'site_captures' => storage_path('app/clips/site-captures'),
        'style_md' => base_path('vault/estilo-animacao.md'),
        'disk' => env('CLIPS_DISK', 'local'),
        // Auto-generate on-brand images (Nano Banana / kie.ai) for scenes the
        // planner marks with `generate`. Needs the kie key; off = skipped cleanly.
        'generate_images' => (bool) env('CLIPS_GENERATE_IMAGES', true),
        'image_max' => (int) env('CLIPS_IMAGE_MAX', 8),        // per clip, to bound cost/time
        // How much clip one generated image has to earn. The planner is told to be
        // visually dense and the bare-scene filler tops up whatever is left, so
        // without a per-length budget a 25-second clip asked for as many pictures
        // as a three-minute one — seven paid generations to approve on a short.
        // Lower = more images. image_max above is still the hard ceiling.
        'image_every_seconds' => (int) env('CLIPS_IMAGE_EVERY_SECONDS', 12),
        'image_aspect' => env('CLIPS_IMAGE_ASPECT', '9:16'),   // portrait clips
        // Search the web for the REAL image a suggestion asks for (a person, a
        // logo, a product, a page) before falling back to a generated one. Off =
        // suggestions arrive empty and are generated/uploaded as before.
        'fetch_images' => (bool) env('CLIPS_FETCH_IMAGES', true),
        // A picture whose long edge is under this looks awful fullscreen in a
        // portrait frame, so it is rejected rather than fetched.
        'fetch_min_width' => (int) env('CLIPS_FETCH_MIN_WIDTH', 600),
        'fetch_max_bytes' => (int) env('CLIPS_FETCH_MAX_BYTES', 10485760),
        // Fallback image search when the model's own web search names nothing.
        // Any Apify image-search actor; empty disables the fallback.
        'image_search_actor' => env('APIFY_ACTOR_IMAGE_SEARCH', 'hooli~google-images-scraper'),
        'image_style' => env('CLIPS_IMAGE_STYLE', 'cinematic, photographic, high detail, dramatic lighting, vertical composition, no text or watermarks'),
        // Transcription engine: 'auto' (OpenAI when a key is set, else local
        // Whisper), 'openai' or 'local'. The per-step binding in Settings wins.
        'transcriber' => env('CLIPS_TRANSCRIBER', 'auto'),
        // Time budget for local Whisper (the model size is services.shorts.whisper_model,
        // shared with the Shorts pipeline and editable in Settings → AI & Engine).
        'whisper_timeout' => (int) env('WHISPER_TIMEOUT', 1800),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pipeline steps → API key
    |--------------------------------------------------------------------------
    | Every step that calls a paid provider is listed here so Settings can offer
    | a per-step key picker: with two OpenAI keys you can bill the animation plan
    | to one and the transcription to the other. `kind` decides which providers
    | are offered; an unbound step keeps the old behaviour (provider chain +
    | that provider's default key).
    |
    | `passos_resolvidos` is filled in at runtime by SettingsOverlay — never here.
    */
    'passos' => [
        'noticias_escrita' => ['label' => 'News · writing', 'kind' => 'llm'],
        'noticias_dicas' => ['label' => 'News · tool-tip scripts', 'kind' => 'llm'],
        'noticias_topico' => ['label' => 'News · topic scripts', 'kind' => 'llm'],
        'publicacoes_plano' => ['label' => 'Posts · planning', 'kind' => 'llm'],
        'shorts_selecao' => ['label' => 'Shorts · clip selection', 'kind' => 'llm'],
        'clips_pesquisa' => ['label' => 'Clips · research', 'kind' => 'llm'],
        'clips_plano' => ['label' => 'Clips · animation plan', 'kind' => 'llm'],
        'clips_metadados' => ['label' => 'Clips · title & metadata', 'kind' => 'llm'],
        'vfx_site' => ['label' => 'VFX · which site to film', 'kind' => 'llm'],
        'clips_transcricao' => ['label' => 'Clips · transcription', 'kind' => 'stt'],
        'clips_voz' => ['label' => 'Clips · voiceover', 'kind' => 'tts'],
        'clips_sfx' => ['label' => 'Clips · sound effects', 'kind' => 'tts'],
        'clips_imagens' => ['label' => 'Clips · scene images', 'kind' => 'image'],
        'publicacoes_cartoes' => ['label' => 'Posts · card rendering', 'kind' => 'image'],
    ],

    // Providers each kind of step may be pinned to. 'local' is not an API key —
    // it is local Whisper (scripts/transcribe.py), usable with no key at all.
    'passos_fornecedores' => [
        'llm' => ['anthropic', 'openai', 'gemini', 'tensorx'],
        'stt' => ['openai', 'local'],
        'tts' => ['elevenlabs'],
        'image' => ['kie'],
    ],

    // step => ['provider' => …, 'key' => …]. Written by SettingsOverlay at boot.
    'passos_resolvidos' => [],

    /*
    |--------------------------------------------------------------------------
    | Design System (brand guide for the GENERATED CONTENT)
    |--------------------------------------------------------------------------
    | Markdown file, editable in the app and readable in Obsidian, that describes
    | the visual/verbal identity to apply to the GENERATED content (animated clips
    | and posts). It is injected into those generators' LLM prompts. Distinct from
    | the design of the app's own interface (Brand-Machine-design-system.md).
    */
    'design_system' => [
        'path' => env('DESIGN_SYSTEM_PATH', base_path('vault/design-system.md')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Self-update (Docker deploy)
    |--------------------------------------------------------------------------
    | Powers the "Check for updates" button. `version` is baked into the image at
    | build time (the git sha). The check compares the running image's tag digest
    | against `:latest` on GHCR (anonymous, public). The update itself is performed
    | by a Watchtower sidecar reached over the compose network — the installer sets
    | WATCHTOWER_URL + WATCHTOWER_TOKEN. Empty watchtower_url → the update button is
    | hidden (e.g. local/dev), but the version check still works.
    */
    /*
    |--------------------------------------------------------------------------
    | Queue workers (Settings → Workers)
    |--------------------------------------------------------------------------
    | The queues the app dispatches to, and how to run a worker for each. This is
    | the ONLY source of manageable queue names — the Settings panel starts and
    | stops workers by running a process, so a name that did not come from this
    | whitelist must never reach that command line.
    |
    | `php_binary` matters: under PHP-FPM, PHP_BINARY is the *php-fpm* binary,
    | which cannot run artisan. Set WORKER_PHP_BINARY to the CLI php when the
    | Start button reports that it could not launch.
    |
    | Managing workers only works where the web process and the workers share a
    | host and a process namespace. In the Docker deploy they are separate
    | containers, so the panel shows status as unavailable rather than lying.
    */
    'workers' => [
        'php_binary' => env('WORKER_PHP_BINARY', ''),
        'timeout' => (int) env('WORKER_TIMEOUT', 1800),
        'tries' => (int) env('WORKER_TRIES', 3),
        'queues' => [
            'default' => 'Clips, plans, renders — the main pipeline',
            'previews' => 'SFX and background preview renders',
            'media' => 'Publicações card images (kie.ai)',
        ],
    ],

    /*
    | Imocena runs ONE project: every request and job uses it (SetActiveProject),
    | whatever else the registry holds. Its style, music and end card shape every
    | reel. Leave it without an agent in Resources: a project agent replaces the
    | listing's, and each reel must carry the listing's own agent. Empty = the
    | first project in the registry.
    */
    'project' => env('IMOCENA_PROJECT', ''),

    /*
    | Free-video leads from the public landing page.
    */
    'leads' => [
        'per_hour' => (int) env('LEADS_PER_HOUR', 5),   // form submissions per IP
        'watermark' => env('LEADS_WATERMARK', 'IMOCENA · VÍDEO GRÁTIS'),   // across the free videos
    ],

    /*
    | Listing → clip importer (Animated Clips · "From a listing"). Voices are
    | European-Portuguese ElevenLabs voices, picked by the agent's gender.
    | Stability is the Creative (0) / Natural (0.5) / Robust (1) setting.
    | `takes` > 1 (was 3 on v3): Simão drifted between European and Brazilian accents
    | take to take on v3 (same settings as the website, where takes were redone by
    | hand), so the importer narrates several and the user picks one by ear.
    */
    'real_estate' => [
        'voices' => [
            // Simão on v4 sounds better than on v3 (which drifted Brazilian unless the text was in
            // paragraphs, ElevenLabsVoiceoverService::paragraphs). LISTING_MODEL_MALE=eleven_v3 to go back.
            'm' => ['voice_id' => env('LISTING_VOICE_MALE', 'SgbHvexvdPuvqzNjvbPM'), 'model' => env('LISTING_MODEL_MALE', 'eleven_v4'), 'stability' => 0.5, 'takes' => 1],
            'f' => ['voice_id' => env('LISTING_VOICE_FEMALE', 'MTAejQCmgXMzsj4Dgq5O'), 'model' => env('LISTING_MODEL_FEMALE', 'eleven_v4')],
        ],
        'music' => env('LISTING_MUSIC', 'upbeat-corporate.mp3'),
        'music_volume' => 0.1,
        // The music alone over a beat-synced opening, before the voice (it eases down to music_volume).
        'opening_music_volume' => 0.7,
        // Walkthrough → reel: the Kling first+last-frame model that modernises
        // each room, and how many rooms get one (each is a paid video).
        'property' => [
            'video_model' => env('PROPERTY_VIDEO_MODEL', 'kling-3.0/video'),
            'max_rooms' => (int) env('PROPERTY_MAX_ROOMS', 5),
            // Kling's own audio: the sounds of the new things arriving, mixed under the music.
            'sound' => (bool) env('PROPERTY_KLING_SOUND', true),
        ],
    ],

    'update' => [
        'version' => env('APP_VERSION', 'dev'),
        'image' => env('APP_IMAGE', 'ghcr.io/jdportugal/contentmachine'),
        'watchtower_url' => env('WATCHTOWER_URL'),
        'watchtower_token' => env('WATCHTOWER_TOKEN'),
    ],
];
