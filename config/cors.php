<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Somente o frontend React (memory-spark) pode chamar esta API a partir
    | do navegador. Configure FRONTEND_URLS no .env com uma lista separada
    | por vírgulas (ex: dev + produção). Nunca use "*" aqui: os endpoints
    | usam token Bearer (Sanctum), e uma origem coringa permitiria que
    | qualquer site de terceiros fizesse requisições autenticadas em nome
    | do usuário via JavaScript.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(array_map(
        'trim',
        explode(',', env('FRONTEND_URLS', 'http://localhost:8080,http://127.0.0.1:8080'))
    )),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'Authorization', 'Accept', 'X-Requested-With'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Bearer token via Authorization header, não cookie de sessão -> não
    // precisamos de credentials (cookies) cross-origin.
    'supports_credentials' => false,

];
