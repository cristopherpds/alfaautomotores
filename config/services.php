<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    /*
     * WhatsApp Cloud API de Meta. Un solo número: el del local.
     *
     * `verify_token` va en la URL del webhook (`/webhooks/whatsapp/{token}`)
     * y en el handshake de Meta; `app_secret` verifica la firma de cada POST.
     */
    'whatsapp' => [
        'token' => env('WHATSAPP_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'waba_id' => env('WHATSAPP_WABA_ID'),
        'app_secret' => env('WHATSAPP_APP_SECRET'),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
        'graph_version' => env('WHATSAPP_GRAPH_VERSION', 'v25.0'),
    ],

    /*
     * Los modelos del agente de WhatsApp en OpenRouter. La clave va en
     * `config/ai.php` (`OPENROUTER_API_KEY`). Todo se puede pisar desde el
     * panel (Ajustes → Conexiones). El modelo tiene que aceptar herramientas e
     * imágenes. El de transcripción va a `audio/transcriptions`, que sólo
     * acepta modelos de voz a texto (`openai/whisper-1`): un modelo de chat
     * ahí responde «does not exist».
     */
    'openrouter' => [
        'modelo' => env('OPENROUTER_MODEL', 'google/gemini-2.5-flash'),
        'modelo_transcripcion' => env('OPENROUTER_TRANSCRIPTION_MODEL', 'openai/whisper-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
