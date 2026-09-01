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
        'token' => env('POSTMARK_TOKEN'),
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
    | Flashcard Content Service (Python + Neo4j)
    |--------------------------------------------------------------------------
    |
    | Microsserviço interno responsável pelo conteúdo e relações dos
    | flashcards. Nunca é chamado diretamente pelo React - só o Laravel
    | fala com ele, autenticado por um token de serviço fixo (não é o
    | token Sanctum do usuário).
    |
    */

    'flashcard_service' => [
        'url' => env('FLASHCARD_SERVICE_URL', 'http://127.0.0.1:5000'),
        'token' => env('FLASHCARD_SERVICE_TOKEN'),
        'timeout' => env('FLASHCARD_SERVICE_TIMEOUT', 10),
    ],

];
