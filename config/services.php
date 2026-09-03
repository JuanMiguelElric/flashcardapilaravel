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

        // Retry só para falhas transitórias (timeout/conexão recusada ou
        // 5xx do Python) - nunca para 4xx (erro definitivo: validação,
        // ownership, etc.). Seguro porque a escrita no Python é
        // idempotente (MERGE por flashcard_id): reenviar a mesma
        // requisição não duplica nada.
        'retry_times' => env('FLASHCARD_SERVICE_RETRY_TIMES', 3),
        'retry_delay_ms' => env('FLASHCARD_SERVICE_RETRY_DELAY_MS', 150),
    ],

    /*
    |--------------------------------------------------------------------------
    | Mercado Pago (assinaturas / preapproval)
    |--------------------------------------------------------------------------
    |
    | Gateway de pagamento real dos planos Premium/Institucional. Usa a API
    | de Assinaturas (preapproval) - cobrança recorrente, não pagamento
    | avulso - porque o cancelamento mantém acesso até o fim do período já
    | pago (decisão de produto), o que só faz sentido com uma assinatura.
    | access_token/webhook_secret precisam ser preenchidos com credenciais
    | reais (sandbox ou produção) para o fluxo funcionar de ponta a ponta.
    |
    */

    'mercadopago' => [
        'base_url' => env('MERCADOPAGO_BASE_URL', 'https://api.mercadopago.com'),
        'access_token' => env('MERCADOPAGO_ACCESS_TOKEN'),
        'webhook_secret' => env('MERCADOPAGO_WEBHOOK_SECRET'),
        'timeout' => env('MERCADOPAGO_TIMEOUT', 10),
        'retry_times' => env('MERCADOPAGO_RETRY_TIMES', 3),
        'retry_delay_ms' => env('MERCADOPAGO_RETRY_DELAY_MS', 150),
    ],

];
