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
    | ContentMachine — content integrations (all to be filled in)
    |--------------------------------------------------------------------------
    | Empty keys by default; the app runs with the 'fake' drivers. Fill them
    | in the .env when you want to wire up the real drivers.
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

    // Clip Generator — LOCAL, standalone engine (ffmpeg + Whisper), with no
    // external API. Reimplements the logic of the Flask "ShortsCreator" service.
    'shorts' => [
        'ffmpeg' => env('FFMPEG_BINARY', 'ffmpeg'),
        'ffprobe' => env('FFPROBE_BINARY', 'ffprobe'),
        'python' => env('WHISPER_PYTHON', 'python3'),
        'whisper_model' => env('WHISPER_MODEL', 'tiny'),
        'transcribe_script' => base_path('scripts/transcribe.py'),
        'fonts_path' => resource_path('fonts'),
    ],

    'elevenlabs' => [
        'key' => env('ELEVENLABS_API_KEY'),
    ],

    'youtube' => [
        'key' => env('YOUTUBE_API_KEY'),
    ],

    'reddit' => [
        'client_id' => env('REDDIT_CLIENT_ID'),
        'client_secret' => env('REDDIT_CLIENT_SECRET'),
    ],

    // kie.ai — image generation (Nano Banana models). Optional: with no key,
    // posts are drawn in SVG (driver 'svg').
    'kie' => [
        'key' => env('KIE_API_KEY'),
        'base_url' => env('KIE_BASE_URL', 'https://api.kie.ai'),
        'image_model' => env('KIE_IMAGE_MODEL', 'nano-banana-2'),
        // Model with the best text rendering — used on the cards (text-heavy).
        'text_model' => env('KIE_TEXT_IMAGE_MODEL', 'nano-banana-pro'),
        // File upload host (references for image→image editing).
        'file_base_url' => env('KIE_FILE_BASE_URL', 'https://kieai.redpandaai.co'),
    ],

];
