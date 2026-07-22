<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | ContentMachine — integrações de conteúdo (todas por preencher)
    |--------------------------------------------------------------------------
    | Chaves vazias por defeito; a app corre com os drivers 'fake'. Preencha
    | no .env quando quiser ligar os drivers reais.
    */
    'apify' => [
        'token' => env('APIFY_TOKEN'),
        'base_url' => env('APIFY_BASE_URL', 'https://api.apify.com'),
    ],

    'tubelab' => [
        'token' => env('TUBELAB_TOKEN'),
    ],

    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
    ],

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
    ],

    'anthropic' => [
        'key' => env('ANTHROPIC_API_KEY'),
    ],

    // Gerador de Clips — motor LOCAL e independente (ffmpeg + Whisper), sem API
    // externa. Reimplementa a lógica do serviço Flask "ShortsCreator".
    'shorts' => [
        'ffmpeg' => env('FFMPEG_BINARY', 'ffmpeg'),
        'ffprobe' => env('FFPROBE_BINARY', 'ffprobe'),
        'python' => env('WHISPER_PYTHON', 'python3'),
        'whisper_model' => env('WHISPER_MODEL', 'tiny'),
        'transcribe_script' => base_path('scripts/transcribe.py'),
        'fonts_path' => resource_path('fonts'),
    ],

    'youtube' => [
        'key' => env('YOUTUBE_API_KEY'),
    ],

    'reddit' => [
        'client_id' => env('REDDIT_CLIENT_ID'),
        'client_secret' => env('REDDIT_CLIENT_SECRET'),
    ],

    // kie.ai — geração de imagens (modelos Nano Banana). Opcional: sem chave,
    // as publicações são desenhadas em SVG (driver 'svg').
    'kie' => [
        'key' => env('KIE_API_KEY'),
        'base_url' => env('KIE_BASE_URL', 'https://api.kie.ai'),
        'image_model' => env('KIE_IMAGE_MODEL', 'nano-banana-2'),
        // Modelo com melhor rendição de texto — usado nos cartões (texto-intensivos).
        'text_model' => env('KIE_TEXT_IMAGE_MODEL', 'nano-banana-pro'),
        // Host de carregamento de ficheiros (referências para edição imagem→imagem).
        'file_base_url' => env('KIE_FILE_BASE_URL', 'https://kieai.redpandaai.co'),
    ],

];
