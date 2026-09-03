<?php


use App\Exceptions\FlashcardServiceException;
use App\Exceptions\MercadoPagoException;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;


return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->group('api', [
            //EnsureFrontendRequestsAreStateful::class, // Required for Sanctum
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ]);
        $middleware->alias([
            'role' => RoleMiddleware::class,  //register new role middleware
        ]);
        // HandleCors já faz parte do middleware global padrão do Laravel 11
        // (Illuminate\Foundation\Configuration\Middleware::getGlobalMiddleware)
        // e lê config/cors.php automaticamente - não precisa ser adicionado aqui.
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // O serviço de flashcards (Python) fora do ar/instável nunca deve
        // vazar detalhes internos (URL, stack trace) para o React.
        $exceptions->render(function (FlashcardServiceException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Serviço de flashcards indisponível no momento. Tente novamente em instantes.',
                ], 502);
            }
        });

        $exceptions->render(function (MercadoPagoException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Serviço de pagamento indisponível no momento. Tente novamente em instantes.',
                ], 502);
            }
        });
    })->create();
