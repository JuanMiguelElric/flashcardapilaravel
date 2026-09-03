<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AssinaturaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ConviteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FlashcardController;
use App\Http\Controllers\InstituicaoController;
use App\Http\Controllers\PlanoSelecionadoController;
use App\Http\Controllers\PlanosController;
use App\Http\Controllers\RelatorioController;
use App\Http\Controllers\TurmaController;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');

// Pública de propósito - o Mercado Pago chama direto, sem token Sanctum
// de usuário. Autenticada por assinatura HMAC (MERCADOPAGO_WEBHOOK_SECRET),
// não por auth:sanctum - ver AssinaturaController::assertAssinaturaValida.
Route::post('/webhooks/mercadopago', [AssinaturaController::class, 'webhook']);

Route::middleware(['auth:sanctum'])->group(function () {
    // Representa o usuário autenticado, independente do role - a
    // restrição de role fica só nas rotas que realmente precisam dela.
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/categoria/index', [CategoriaController::class, 'index']);
    Route::post('/categoria', [CategoriaController::class, 'store']);
    Route::get('/categoria/{categoria}', [CategoriaController::class, 'show']);
    Route::put('/categoria/{categoria}', [CategoriaController::class, 'update']);
    Route::delete('/categoria/{categoria}', [CategoriaController::class, 'destroy']);

    //FUNÇÃO para trazer todos os cards
    Route::get('/flashcard/index', [FlashcardController::class, 'index']);

    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Qualquer usuário autenticado escolhe o próprio plano - distinto da
    // administração de planos abaixo (role:admin).
    Route::post('/plano/selecionar', [PlanoSelecionadoController::class, 'store']);
    Route::post('/assinatura/checkout', [AssinaturaController::class, 'checkout']);

    // Domínio Institucional - criar instituição exige plano Institucional
    // ativo (checado em InstituicaoService::criar), sem role dedicado:
    // qualquer usuário autenticado pode ser dono/professor/aluno conforme
    // o vínculo real (owner_user_id, instituicao_professores, turma_alunos).
    Route::get('/instituicao', [InstituicaoController::class, 'index']);
    Route::post('/instituicao', [InstituicaoController::class, 'store']);
    Route::post('/instituicao/{instituicao}/professores', [InstituicaoController::class, 'convidarProfessor']);
    Route::post('/instituicao/{instituicao}/turmas', [TurmaController::class, 'store']);

    Route::get('/turma', [TurmaController::class, 'index']);
    Route::get('/turma/{turma}/alunos', [TurmaController::class, 'alunos']);
    Route::post('/turma/{turma}/alunos', [TurmaController::class, 'convidarAluno']);
    Route::get('/turma/{turma}/alunos/{aluno}/relatorio', [RelatorioController::class, 'doAluno']);

    Route::post('/convites/professor/{convite}/aceitar', [ConviteController::class, 'aceitarProfessor']);
    Route::post('/convites/aluno/{convite}/aceitar', [ConviteController::class, 'aceitarAluno']);

    Route::middleware(['role:admin'])->group(function () {
        Route::get('/admin-dashboard', function () {
            return response()->json(['message' => 'Welcome Admin']);
        });

        Route::get('/plano', [PlanosController::class, 'index']);
        Route::post('/plano', [PlanosController::class, 'store']);
        Route::get('/plano/{plano}', [PlanosController::class, 'show']);
        Route::put('/plano/{plano}', [PlanosController::class, 'update']);
        Route::delete('/plano/{plano}', [PlanosController::class, 'destroy']);
    });

    Route::middleware(['role:client'])->group(function () {
        Route::post('/flashcard', [FlashcardController::class, 'store'])->name('flashcard.store');
        Route::put('/flashcard/{flashcard}', [FlashcardController::class, 'update']);
        Route::delete('/flashcard/{flashcard}', [FlashcardController::class, 'destroy']);
    });
});
